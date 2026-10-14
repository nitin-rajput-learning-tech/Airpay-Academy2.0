<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Stage B parity metrics, and the standalone tool that takes the baseline on the SOURCE.
 *
 * ONE file, two uses:
 *
 *  1. Standalone. Copy this single file to the restored 4.1.2 copy, or to any Moodle 4.x/5.x box, and run it with the
 *     PHP that box already has (7.4 to 8.4). It needs only the box's own config.php and the mysqli extension. It does
 *     not load Moodle, and it needs no Sentientia plugin on the database: the baseline of the SOURCE is taken before
 *     any Sentientia code exists there (ADR-032 "Parity hooks" 1, migration plan 4a).
 *
 *       php source_baseline.php --config=/path/to/config.php --baseline=/safe/place/live-baseline.json
 *       php source_baseline.php --config=/path/to/config.php --compare=/safe/place/live-baseline.json
 *       php source_baseline.php --config=/path/to/config.php              (print the metrics only)
 *
 *     --config defaults to the nearest config.php above this file. It also runs --compare on a copy that has no
 *     Sentientia plugin yet, for the checkpoint after hop 1 (Moodle 4.5 core only): the metrics and the BizLMS legacy
 *     tables must still match the source there. It never writes to the database: the session is read-only, every
 *     statement is a SELECT or SHOW (checked in code), and all of it is read from one consistent snapshot. It reads
 *     config.php as data (it never runs it), so a setting built by a function other than getenv() needs the explicit
 *     options (--dbhost, --dbname, --dbuser, --dbpass-env=VARIABLE, --prefix, --dbport, --dbsocket).
 *
 *  2. Library. cli/migration_parity_check.php defines SENTIENTIA_PARITY_LIBRARY_ONLY and requires this file, so the
 *     Sentientia target computes every number with the SAME code that took the baseline. A number cannot differ
 *     because two copies of a query drifted apart.
 *
 * Every metric is version aware. Moodle 4.3 replaced scorm_scoes_track by scorm_attempt, scorm_scoes_value and
 * scorm_element (the upgrade step is mod/scorm 2023042401 to 2023042403, and it drops the old table), so the SCORM
 * numbers are LOGICAL: attempts are distinct (user, scorm, attempt) triples, tracks are the stored elements, and the
 * checksum hashes (user, scorm, sco, attempt, element, value, timemodified) from whichever layout the database has. The
 * same data gives the same numbers on 4.1.2 and on 5.x. A table or column a version does not have is left out of both
 * the baseline and the comparison, never an error. The baseline records which layout it read.
 *
 * The metrics are versioned (metrics::VERSION, written as tool.metrics): a comparison REFUSES a baseline of another version, exit 3,
 * because the checksums it lacks would otherwise go unchecked. The baseline also names the exact file that took it (tool.sha256, this
 * file with every CR removed): a comparison with another file is refused the same way, so a change that adds a checksum without
 * bumping the version cannot pass quietly. Take the baseline again with the tool that compares it.
 *
 * JSON format 2 (format 1, written by the earlier tool, is still read: it has counts, checksums and nothing else):
 *   counts       integer metrics (users per tenant, courses, enrolments, role assignments, SCORM attempts, ...)
 *   aggregates   value-level sums kept as exact decimal strings (the grade sum)
 *   checksums    per table {rows, crc, cols}: SUM(CRC32(CONCAT_WS(...))) over the listed columns, the SCORM one included
 *   legacy       the 22 BizLMS plugins' tables that exist: {count, maxid, crc, columns}, a CRC over ALL columns and ALL rows
 *                (no row cap), the shape parity::legacy_fingerprints() returns
 *   legacy_other tables with a legacy prefix that no inventory names (informational: drift there is unproven, not failed)
 *   core         what the import's reviewed core writes need to be explained (see class core and ADR-032)
 *
 * Exit codes of --compare, the same as migration_parity_check.php (import_bizlms.php shares 0, 1 and 2):
 *   0 parity, 1 drift, 2 counts match but something could not be checked (not proven), 3 the tool could not run, or refused
 *   (a baseline of another metrics version, or taken by another version of this file).
 *
 * PHP 7.4 syntax only in this file: no match, no union types, no named arguments, no str_contains.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform\parity;

if (PHP_SAPI !== 'cli') {
    exit('This tool runs from the command line only.');
}

/**
 * What the metrics need from a database. Two implementations: mysqli_database (standalone, no Moodle) and moodle_db
 * (the Sentientia target). SQL uses {table} placeholders for prefixed tables and never contains a colon or a question
 * mark, which Moodle's own driver would read as parameters.
 */
interface database {
    /** @return string[] Every table name, without the prefix. */
    public function tables(): array;

    /**
     * @param string $table Table name without prefix.
     * @return bool
     */
    public function table_exists(string $table): bool;

    /**
     * @param string $table Table name without prefix.
     * @return string[] Column names in table order.
     */
    public function columns(string $table): array;

    /**
     * @param string $sql
     * @return string|null The first column of the first row; null for no row or a NULL value.
     */
    public function scalar(string $sql): ?string;

    /**
     * @param string $sql
     * @return array<int, array<string, string|null>>
     */
    public function rows(string $sql): array;

    /** @return string 'mysql' for MySQL and MariaDB (the only families that have CRC32), anything else otherwise. */
    public function family(): string;

    /** @return string Server version text. */
    public function server(): string;
}

/**
 * The standalone connection: mysqli, read-only, one consistent snapshot.
 */
final class mysqli_database implements database {
    /** @var \mysqli */
    private $link;
    /** @var string */
    private $prefix;
    /** @var string[]|null */
    private $tablecache = null;
    /** @var array<string, string[]> */
    private $columncache = [];
    /** @var bool The session is one consistent snapshot. */
    public $snapshot = false;
    /** @var bool The session cannot write. */
    public $readonly = false;

    /**
     * @param array{host: string, user: string, pass: string, name: string, prefix: string, port: int, socket: string, flags: int} $c
     */
    public function __construct(array $c) {
        if (!class_exists('\mysqli')) {
            throw new \RuntimeException('The mysqli extension is not loaded in this PHP.');
        }
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->prefix = $c['prefix'];
        $link = mysqli_init();
        $host = $c['host'];
        if (strncmp($host, 'p:', 2) === 0) {
            $host = substr($host, 2);
        }
        $ok = @$link->real_connect($host, $c['user'], $c['pass'], $c['name'], $c['port'] > 0 ? $c['port'] : null,
            $c['socket'] !== '' ? $c['socket'] : null, $c['flags']);
        if (!$ok) {
            // The message names the server error, never the credentials.
            throw new \RuntimeException('Cannot connect to the database (error ' . (int) $link->connect_errno . ': '
                . $link->connect_error . ').');
        }
        $this->link = $link;
        $this->link->set_charset('utf8mb4');
        // Belt and braces: the code below only reads, and the session cannot write either.
        $this->readonly = $this->run('SET SESSION TRANSACTION READ ONLY', true);
        $this->snapshot = $this->run('START TRANSACTION WITH CONSISTENT SNAPSHOT', true);
    }

    public function __destruct() {
        if ($this->link instanceof \mysqli) {
            @$this->link->query('ROLLBACK');
            @$this->link->close();
        }
    }

    /**
     * @param string $sql
     * @param bool $soft Return false instead of throwing.
     * @return bool
     */
    private function run(string $sql, bool $soft = false): bool {
        $result = @$this->link->query($sql);
        if ($result === false) {
            if ($soft) {
                return false;
            }
            throw new \RuntimeException('Statement failed (' . $this->link->errno . '): ' . $this->link->error);
        }
        if ($result instanceof \mysqli_result) {
            $result->free();
        }
        return true;
    }

    /**
     * Replace {table} by the prefixed name and refuse anything that is not a read.
     *
     * @param string $sql
     * @return string
     */
    private function expand(string $sql): string {
        if (!preg_match('/^\s*(SELECT|SHOW)\b/i', $sql)) {
            throw new \RuntimeException('This tool only reads: refused statement.');
        }
        $prefix = $this->prefix;
        return preg_replace_callback('/\{([A-Za-z][A-Za-z0-9_]*)\}/', static function (array $m) use ($prefix): string {
            return '`' . $prefix . $m[1] . '`';
        }, $sql);
    }

    public function tables(): array {
        if ($this->tablecache === null) {
            $this->tablecache = [];
            $result = $this->link->query('SHOW TABLES');
            while ($result && ($row = $result->fetch_row())) {
                if ($this->prefix === '' || strncmp($row[0], $this->prefix, strlen($this->prefix)) === 0) {
                    $this->tablecache[] = substr($row[0], strlen($this->prefix));
                }
            }
            if ($result) {
                $result->free();
            }
        }
        return $this->tablecache;
    }

    public function table_exists(string $table): bool {
        return in_array($table, $this->tables(), true);
    }

    public function columns(string $table): array {
        if (!isset($this->columncache[$table])) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $table)) {
                throw new \RuntimeException('Not a plain table name: ' . $table);
            }
            $result = $this->link->query('SHOW COLUMNS FROM `' . $this->prefix . $table . '`');
            $columns = [];
            while ($result && ($row = $result->fetch_row())) {
                $columns[] = $row[0];
            }
            if ($result) {
                $result->free();
            }
            $this->columncache[$table] = $columns;
        }
        return $this->columncache[$table];
    }

    public function scalar(string $sql): ?string {
        $result = $this->link->query($this->expand($sql));
        if ($result === false) {
            throw new \RuntimeException('Query failed (' . $this->link->errno . '): ' . $this->link->error);
        }
        $row = $result->fetch_row();
        $result->free();
        return $row === null || $row === false ? null : ($row[0] === null ? null : (string) $row[0]);
    }

    public function rows(string $sql): array {
        $result = $this->link->query($this->expand($sql));
        if ($result === false) {
            throw new \RuntimeException('Query failed (' . $this->link->errno . '): ' . $this->link->error);
        }
        $out = [];
        while ($row = $result->fetch_assoc()) {
            $out[] = $row;
        }
        $result->free();
        return $out;
    }

    public function family(): string {
        return 'mysql';
    }

    public function server(): string {
        return (string) $this->scalar('SELECT VERSION()');
    }
}

/**
 * The same interface over Moodle's $DB, for the Sentientia target. Only used inside a Moodle CLI script.
 */
final class moodle_db implements database {
    /** @var \moodle_database */
    private $db;

    /**
     * @param \moodle_database $db
     */
    public function __construct($db) {
        $this->db = $db;
    }

    public function tables(): array {
        return array_values(array_keys($this->db->get_tables()));
    }

    public function table_exists(string $table): bool {
        return $this->db->get_manager()->table_exists($table);
    }

    public function columns(string $table): array {
        return array_keys($this->db->get_columns($table));
    }

    public function scalar(string $sql): ?string {
        $value = $this->db->get_field_sql($sql);
        return $value === false || $value === null ? null : (string) $value;
    }

    public function rows(string $sql): array {
        $out = [];
        $set = $this->db->get_recordset_sql($sql);
        foreach ($set as $record) {
            $out[] = array_map(static function ($v) {
                return $v === null ? null : (string) $v;
            }, (array) $record);
        }
        $set->close();
        return $out;
    }

    public function family(): string {
        return $this->db->get_dbfamily();
    }

    public function server(): string {
        $info = $this->db->get_server_info();
        return (string) ($info['version'] ?? ($info['description'] ?? ''));
    }
}

/**
 * Small SQL helpers shared by the metric classes.
 */
final class sql {
    /** The value a NULL takes in a checksum: CONCAT_WS would skip it, and (a, NULL, b) must differ from (a, b, NULL). */
    public const NULLTOKEN = '~NULL~';

    /**
     * @param string $name
     * @return string
     */
    public static function identifier(string $name): string {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            throw new \RuntimeException('Not a plain SQL identifier: ' . $name);
        }
        return $name;
    }

    /**
     * CONCAT_WS(0x1f, IFNULL(col, sentinel), ...), the row text every checksum hashes.
     *
     * @param string[] $columns
     * @param string $alias Table alias, or ''.
     * @param string[] $rounded Columns to ROUND to 5 places first (floats do not print identically on every engine).
     * @return string
     */
    public static function row_text(array $columns, string $alias = '', array $rounded = []): string {
        $prefix = $alias === '' ? '' : $alias . '.';
        $parts = [];
        foreach ($columns as $column) {
            self::identifier($column);
            $parts[] = "IFNULL({$prefix}`{$column}`, '" . self::NULLTOKEN . "')";
        }
        foreach ($rounded as $column) {
            self::identifier($column);
            $parts[] = "IFNULL(ROUND({$prefix}`{$column}`, 5), '" . self::NULLTOKEN . "')";
        }
        return 'CONCAT_WS(0x1f, ' . implode(', ', $parts) . ')';
    }

    /**
     * @param database $db
     * @param string $table
     * @param string $where Optional WHERE body.
     * @return int
     */
    public static function count(database $db, string $table, string $where = ''): int {
        return (int) $db->scalar('SELECT COUNT(1) FROM {' . self::identifier($table) . '} t'
            . ($where === '' ? '' : ' WHERE ' . $where));
    }
}

/**
 * The parity metrics: counts, value aggregates and per-table checksums, version aware.
 */
final class metrics {
    /**
     * Bumped when a metric is added or its definition changes. Written to the baseline as tool.metrics, and a comparison REFUSES
     * (exit 3) a baseline of another version (baseline_problem()): a baseline that lacks a metric this tool computes would pass
     * without it being checked.
     *
     * 3: the BizLMS user and course substrate (password hashes, every open_* column), course_modules, course_sections, grade_items,
     *    course_completion_criteria and the certificate templates and issue columns the first sets left out.
     * 4: the core section holds enrol as an UPDATE table (status and timemodified writable: the enrolments importer switches off
     *    the BizLMS instances it proved safe to switch off, owner decision CRS-01), and the baseline carries the sha256 of the
     *    tool that took it (tool.sha256), which a comparison checks against its own file. A version 3 baseline holds enrol as an
     *    insert-only table with status fixed, so it would fail a clean import: take it again.
     */
    public const VERSION = 4;

    /**
     * Checksummed tables and the columns hashed. Deliberately explicit: adding a column to a schema must not silently
     * change the checksum of an old baseline. A column a database does not have is dropped from the list on that
     * database, and the list actually used is stored with the checksum so a difference is explainable.
     *
     * The first eight entries are the format 1 set, unchanged, so a format 1 baseline still compares.
     */
    private const CHECKSUMS = [
        'user' => ['id', 'username', 'email', 'firstname', 'lastname', 'open_path', 'suspended', 'deleted', 'auth'],
        'course' => ['id', 'shortname', 'fullname', 'category', 'visible', 'startdate', 'enddate'],
        'course_categories' => ['id', 'name', 'parent', 'visible'],
        'user_enrolments' => ['id', 'enrolid', 'userid', 'status', 'timestart', 'timeend'],
        'course_completions' => ['id', 'userid', 'course', 'timecompleted'],
        'course_modules_completion' => ['id', 'coursemoduleid', 'userid', 'completionstate'],
        'quiz_attempts' => ['id', 'quiz', 'userid', 'attempt', 'state'],
        'badge_issued' => ['id', 'badgeid', 'userid', 'dateissued'],
        // Format 2 additions.
        'role_assignments' => ['id', 'roleid', 'contextid', 'userid', 'component', 'itemid'],
        'enrol' => ['id', 'enrol', 'status', 'courseid', 'roleid'],
        // No 'archived': the BizLMS table of 4.1.2 has none and the stock plugin's upgrade adds it, so a column both
        // sides have is all that can be compared (found 2026-10-07 on the April copy: the only drift of a real hop).
        'tool_certificate_issues' => ['id', 'userid', 'code', 'timecreated', 'expires', 'courseid', 'moduleid',
            'moduletype'],
        'forum_posts' => ['id', 'discussion', 'parent', 'userid', 'created', 'modified'],
    ];

    /**
     * Metrics version 3: columns and tables the sets above leave out. Each entry is a checksum of its own, under its own key,
     * [table read, columns], so the entries above and a baseline that holds only them are untouched. The column lists are
     * explicit for the same reason as above and follow three rules, checked on the April 2026 copy (the tables of the 4.1.2 dump
     * against the same data after 4.1.2 to 4.5.10 to 5.1.3): every list below hashed identically on both, except course_modules,
     * which differs by exactly one row, a mod_survey activity the Moodle 5.0 upgrade deleted (without it the two hash alike):
     *   - only columns that Moodle 4.1 and 5.x both have (the 5.x ones, such as course.enableaitools or course_sections.component,
     *     would make the "now" side hash a column the baseline never saw);
     *   - nothing an upgrade or a cron run rewrites by itself (timemodified, sortorder, needsupdate, the course cache revision,
     *     course_sections.sequence, which follows the activities);
     *   - nothing the import writes: the eight course.open_* columns of core::WRITES are out, and so is every `theme` column
     *     (step 07 of the rehearsal kit clears theme overrides).
     * The BizLMS substrate the plan names (password hashes, the open_* user and course columns) is here; course_modules is here too,
     * and the Moodle 5.0 upgrade deletes the activities of mod_survey and mod_chat when their code is not on disk, so this is where
     * such a loss shows (see the NOTE compare_metrics prints).
     *
     * @var array<string, array{0: string, 1: string[]}>
     */
    private const MORE = [
        'user_bizlms' => ['user', ['id', 'password', 'idnumber', 'institution', 'department', 'open_supervisorid',
            'open_employeeid', 'open_usermodified', 'open_designation', 'open_state', 'open_jobfunction', 'open_group',
            'open_qualification', 'open_location', 'open_team', 'open_client', 'open_supervisorempid', 'open_band',
            'open_hrmsrole', 'open_zone', 'open_region', 'open_grade', 'open_positionid', 'open_domainid', 'open_states',
            'open_district', 'open_subdistrict', 'open_village', 'open_joindate', 'open_dateofbirth', 'gender',
            'open_employmenttype', 'open_prefix', 'open_orgactive', 'open_educationlevel', 'open_fieldwork',
            'open_jobtitle', 'open_company', 'open_paymentinfo', 'open_privacypolicy', 'open_termscondition',
            'open_countryid']],
        'course_bizlms' => ['course', ['id', 'idnumber', 'format', 'lang', 'enablecompletion', 'open_certificateid',
            'open_path', 'open_categoryid', 'approvalreqd', 'selfenrol', 'open_securecourse', 'open_hrmsrole',
            'open_location', 'open_module', 'open_coursetype', 'open_group', 'open_designation', 'price_status',
            'courseprice']],
        'course_modules' => ['course_modules', ['id', 'course', 'module', 'instance', 'section', 'idnumber', 'added',
            'score', 'indent', 'visible', 'visibleoncoursepage', 'visibleold', 'groupmode', 'groupingid', 'completion',
            'completiongradeitemnumber', 'completionview', 'completionexpected', 'completionpassgrade',
            'showdescription', 'availability', 'deletioninprogress', 'downloadcontent', 'lang']],
        // No 'name': an upgrade or a first visit fills a section's empty name with its default (April: one section went from NULL to
        // 'Topic 1' between 4.1.2 and 5.1.3), and the name carries no learner data.
        'course_sections' => ['course_sections', ['id', 'course', 'section', 'summaryformat', 'visible',
            'availability']],
        'grade_items' => ['grade_items', ['id', 'courseid', 'categoryid', 'itemname', 'itemtype', 'itemmodule',
            'iteminstance', 'itemnumber', 'idnumber', 'gradetype', 'grademax', 'grademin', 'scaleid', 'outcomeid',
            'gradepass', 'multfactor', 'plusfactor', 'hidden', 'locked']],
        'course_completion_criteria' => ['course_completion_criteria', ['id', 'course', 'criteriatype', 'module',
            'moduleinstance', 'courseinstance', 'enrolperiod', 'timeend', 'gradepass', 'role']],
        'tool_certificate_templates' => ['tool_certificate_templates', ['id', 'name', 'contextid', 'shared',
            'timecreated', 'costcenter', 'open_path']],
        // The columns of tool_certificate_issues the first entry above leaves out (no 'archived': 4.1.2 has none).
        'tool_certificate_issues_more' => ['tool_certificate_issues', ['id', 'templateid', 'emailed', 'data',
            'component']],
    ];

    /** Tables whose float columns are rounded before hashing (grades are floats), with their plain columns. */
    private const ROUNDED = [
        'grade_grades' => ['id', 'itemid', 'userid'],
    ];

    /** Float columns of those tables. */
    private const ROUNDED_COLUMNS = ['rawgrade', 'finalgrade'];

    /** The three tenants BizLMS has, by the first segment of user.open_path. */
    public const TENANTS = [1 => 'airpay', 77 => 'public', 177 => 'zeea'];

    /**
     * Everything the baseline's counts, aggregates and checksums contain.
     *
     * @param database $db
     * @return array{counts: array<string, int>, aggregates: array<string, string>, checksums: array, layout: array<string, string|string[]>, notes: string[]}
     */
    public static function collect(database $db): array {
        $notes = [];
        $counts = self::counts($db, $notes);
        $layout = ['scorm' => self::scorm_layout($db)];
        if ($db->table_exists('modules')) {
            // The module types this release has: compare_metrics() names the ones the baseline had and this release lacks
            // (the Moodle 5.0 upgrade uninstalls mod_survey and mod_chat, and deletes their activities, when their code is gone).
            $modules = [];
            foreach ($db->rows('SELECT t.name AS name FROM {modules} t ORDER BY t.name') as $row) {
                $modules[] = (string) $row['name'];
            }
            $layout['modules'] = $modules;
        }
        self::scorm_counts($db, $layout['scorm'], $counts, $notes);
        return [
            'counts' => $counts,
            'aggregates' => self::aggregates($db),
            'checksums' => self::checksums($db, $layout['scorm']),
            'layout' => $layout,
            'notes' => $notes,
        ];
    }

    /**
     * @param database $db
     * @param string[] $notes
     * @return array<string, int>
     */
    private static function counts(database $db, array &$notes): array {
        $c = [];
        $has = static function (string $table) use ($db): bool {
            return $db->table_exists($table);
        };

        if ($has('user')) {
            $userwhere = 't.deleted = 0';
            if (in_array('open_path', $db->columns('user'), true)) {
                $known = [];
                foreach (self::TENANTS as $root => $label) {
                    // The node itself and everything below it, and nothing that merely starts with the same digits.
                    $in = "(t.open_path LIKE '/{$root}/%' OR t.open_path = '/{$root}')";
                    $c["users_tenant_{$label}"] = sql::count($db, 'user', "{$userwhere} AND {$in}");
                    $known[] = $in;
                }
                // Users with no tenant, or in a tree that is none of the three. Without it a user who moved out of the
                // known tenants (a truncated open_path) would only show as a smaller bucket.
                $c['users_tenant_other'] = sql::count($db, 'user',
                    "{$userwhere} AND (t.open_path IS NULL OR NOT (" . implode(' OR ', $known) . '))');
            } else {
                $notes[] = 'user.open_path is absent: tenant counts left out';
            }
            $c['users_total_active'] = sql::count($db, 'user', $userwhere);
            $c['users_suspended'] = sql::count($db, 'user', $userwhere . ' AND t.suspended = 1');
        }

        $simple = [
            'courses' => 'course',
            'course_categories' => 'course_categories',
            'enrolments' => 'user_enrolments',
            'enrol_instances' => 'enrol',
            'role_assignments' => 'role_assignments',
            'completions' => 'course_completions',
            'module_completions' => 'course_modules_completion',
            'quiz_attempts' => 'quiz_attempts',
            'badges_issued' => 'badge_issued',
            'grade_grades' => 'grade_grades',
            'forum_posts' => 'forum_posts',
            // Metrics version 3: the activities, their sections, the grade items, the completion criteria, the certificate templates.
            'course_modules' => 'course_modules',
            'course_sections' => 'course_sections',
            'grade_items' => 'grade_items',
            'course_completion_criteria' => 'course_completion_criteria',
            'cert_templates' => 'tool_certificate_templates',
        ];
        foreach ($simple as $key => $table) {
            if ($has($table)) {
                $c[$key] = sql::count($db, $table);
            }
        }
        // Value level counts: a recompute that changes states without changing row counts moves these.
        if ($has('course_completions') && in_array('timecompleted', $db->columns('course_completions'), true)) {
            $c['completions_done'] = sql::count($db, 'course_completions', 't.timecompleted IS NOT NULL');
        }
        if ($has('course_modules_completion')) {
            $c['module_completions_done'] = sql::count($db, 'course_modules_completion', 't.completionstate > 0');
        }
        if ($has('grade_grades') && in_array('finalgrade', $db->columns('grade_grades'), true)) {
            $c['grades_final'] = sql::count($db, 'grade_grades', 't.finalgrade IS NOT NULL');
        }

        // Certificates: tool_certificate issues if installed (the customer cert stack).
        foreach (['tool_certificate_issues', 'customcert_issues'] as $table) {
            if ($has($table)) {
                $c["cert_{$table}"] = sql::count($db, $table);
            }
        }

        // Sentientia product tables that carry user data worth proving intact. Absent on the source, so they only ever
        // show as NEW on the target.
        foreach (['local_sentientia_courses_remind_sent' => 'remind_audit',
                  'local_sentientia_feature_flags' => 'feature_flag_rows'] as $table => $key) {
            if ($has($table)) {
                $c[$key] = sql::count($db, $table);
            }
        }
        return $c;
    }

    /**
     * Which SCORM tables this database has: 'track' (Moodle before 4.3: scorm_scoes_track), 'value' (4.3 and later:
     * scorm_attempt + scorm_scoes_value + scorm_element), 'both' (an upgrade that stopped between its steps) or 'none'.
     *
     * @param database $db
     * @return string
     */
    public static function scorm_layout(database $db): string {
        $track = $db->table_exists('scorm_scoes_track');
        $value = $db->table_exists('scorm_attempt') && $db->table_exists('scorm_scoes_value')
            && $db->table_exists('scorm_element');
        if ($track && $value) {
            return 'both';
        }
        return $track ? 'track' : ($value ? 'value' : 'none');
    }

    /**
     * The logical SCORM counts: attempts (distinct user, scorm, attempt) and stored track elements. The 4.3 upgrade turns
     * each distinct triple into one scorm_attempt row and each scorm_scoes_track row into one scorm_scoes_value row.
     *
     * @param database $db
     * @param string $layout
     * @param array<string, int> $counts
     * @param string[] $notes
     * @return void
     */
    private static function scorm_counts(database $db, string $layout, array &$counts, array &$notes): void {
        if ($layout === 'track') {
            $counts['scorm_attempts'] = (int) $db->scalar(
                'SELECT COUNT(1) FROM (SELECT 1 FROM {scorm_scoes_track} GROUP BY userid, scormid, attempt) x');
            $counts['scorm_tracks'] = sql::count($db, 'scorm_scoes_track');
        } else if ($layout === 'value') {
            $counts['scorm_attempts'] = sql::count($db, 'scorm_attempt');
            $counts['scorm_tracks'] = sql::count($db, 'scorm_scoes_value');
        } else if ($layout === 'both') {
            $notes[] = 'scorm_scoes_track and scorm_scoes_value both exist (a 4.3 upgrade that did not finish): '
                . 'SCORM metrics left out';
        }
    }

    /**
     * @param database $db
     * @return array<string, string>
     */
    private static function aggregates(database $db): array {
        $out = [];
        if ($db->table_exists('grade_grades') && in_array('finalgrade', $db->columns('grade_grades'), true)) {
            // Exact: finalgrade is DECIMAL, SUM of a decimal is a decimal, and the cast fixes the number of places.
            $out['grade_finalgrade_sum'] = (string) $db->scalar(
                'SELECT CAST(COALESCE(SUM(t.finalgrade), 0) AS DECIMAL(24,4)) FROM {grade_grades} t'
                . ' WHERE t.finalgrade IS NOT NULL');
        }
        return $out;
    }

    /**
     * @param database $db
     * @param string $scormlayout
     * @return array<string, array{rows: int, crc: ?string, cols: string[]}>
     */
    private static function checksums(database $db, string $scormlayout): array {
        $mysql = $db->family() === 'mysql';
        $out = [];
        // [table read, wanted columns, whether the float columns are rounded] per key. The keys of the first two sets are the
        // table names; those of metrics version 3 name what they add.
        $plan = [];
        foreach (self::CHECKSUMS as $table => $wanted) {
            $plan[$table] = [$table, $wanted, false];
        }
        foreach (self::ROUNDED as $table => $wanted) {
            $plan[$table] = [$table, $wanted, true];
        }
        foreach (self::MORE as $key => $spec) {
            $plan[$key] = [$spec[0], $spec[1], false];
        }
        foreach ($plan as $key => [$table, $wanted, $isrounded]) {
            if (!$db->table_exists($table)) {
                continue;
            }
            $existing = $db->columns($table);
            $use = array_values(array_intersect($wanted, $existing));
            $rounded = $isrounded ? array_values(array_intersect(self::ROUNDED_COLUMNS, $existing)) : [];
            if (!$use) {
                continue;
            }
            $rows = sql::count($db, $table);
            $crc = null;
            if ($mysql) {
                $crc = (string) $db->scalar('SELECT COALESCE(SUM(CRC32(' . sql::row_text($use, '', $rounded)
                    . ')), 0) FROM {' . sql::identifier($table) . '}');
            }
            $out[$key] = ['rows' => $rows, 'crc' => $crc, 'cols' => array_merge($use, $rounded)];
        }

        // SCORM track data, from whichever layout the database has. One logical row per stored element.
        $scorm = null;
        if ($scormlayout === 'track') {
            $scorm = ['from' => '{scorm_scoes_track} t',
                'text' => 'CONCAT_WS(0x1f, t.userid, t.scormid, t.scoid, t.attempt, t.element, t.value, t.timemodified)'];
        } else if ($scormlayout === 'value') {
            // STRAIGHT_JOIN: read the values in id order and look the other two up by primary key. Left to itself the optimiser
            // starts from the 600 elements and probes the values by element: 67 s instead of 2 s on the April copy.
            $scorm = ['from' => '{scorm_scoes_value} v STRAIGHT_JOIN {scorm_attempt} a ON a.id = v.attemptid'
                . ' STRAIGHT_JOIN {scorm_element} e ON e.id = v.elementid',
                'text' => 'CONCAT_WS(0x1f, a.userid, a.scormid, v.scoid, a.attempt, e.element, v.value, v.timemodified)'];
        }
        if ($scorm !== null) {
            $rows = (int) $db->scalar('SELECT COUNT(1) FROM ' . $scorm['from']);
            $crc = $mysql ? (string) $db->scalar('SELECT COALESCE(SUM(CRC32(' . $scorm['text'] . ')), 0) FROM '
                . $scorm['from']) : null;
            $out['scorm_tracks'] = ['rows' => $rows, 'crc' => $crc,
                'cols' => ['userid', 'scormid', 'scoid', 'attempt', 'element', 'value', 'timemodified']];
        }
        return $out;
    }

    /**
     * Why this tool cannot compare a baseline, or null. A baseline taken with another metrics version holds other checksums
     * than this tool computes: those it lacks would go unchecked without a word (a pass that proves less than it says), and
     * those it has may mean something else. So the comparison refuses (exit 3), and the baseline has to be taken again with
     * this tool. A format 1 baseline (no tool section at all) predates the versions and is compared as it always was.
     *
     * @param array $base A baseline document.
     * @return string|null
     */
    public static function baseline_problem(array $base): ?string {
        if (!isset($base['tool']['metrics'])) {
            return null;
        }
        $was = (int) $base['tool']['metrics'];
        if ($was !== self::VERSION) {
            return "the baseline was taken with metrics version {$was}, and this tool is metrics version " . self::VERSION
                . ': the two compute different checksums. Take the baseline again with this tool, or compare with the tool that took it.';
        }
        // The same version number is not the same code: a change that adds a checksum without bumping VERSION would compare
        // only the checksums the baseline holds and ignore the new ones. The baseline names the exact file that took it.
        $sha = isset($base['tool']['sha256']) ? (string) $base['tool']['sha256'] : '';
        if ($sha === '') {
            return 'the baseline does not say which tool took it (no tool.sha256), so it cannot be shown that this tool computes'
                . ' the same numbers. Take the baseline again with this tool.';
        }
        $mine = self::tool_sha256();
        if (!hash_equals($mine, strtolower($sha))) {
            return 'the baseline was taken by another version of cli/source_baseline.php (sha256 ' . substr($sha, 0, 12)
                . ', this file is ' . substr($mine, 0, 12) . '): the same metrics version, but not the same code. Compare with the'
                . ' file that took the baseline, or take the baseline again with this one.';
        }
        return null;
    }

    /**
     * The sha256 of this file with every carriage return removed, so that a copy that crossed a CRLF boundary (a Windows
     * checkout, a zip) hashes like the original. `tr -d '\r' < cli/source_baseline.php | sha256sum` gives the same value.
     *
     * @return string Lower-case hex, or '' when the file cannot be read.
     */
    public static function tool_sha256(): string {
        $code = @file_get_contents(__FILE__);
        if ($code === false) {
            return '';
        }
        return hash('sha256', str_replace("\r", '', $code));
    }

    /**
     * The tenant cross-foot: the buckets add up to the active users.
     *
     * NOT an independent proof. users_tenant_other is the complement of the three tenant buckets (collect() counts it as "no
     * open_path, or none of the three trees"), so the sum equals users_total_active by construction and this can only fail if
     * the counting SQL itself is wrong. What catches a user who drops out of a tenant is the per-bucket comparison with the
     * baseline, users_tenant_other included: the bucket that lost the user and the one that gained it both drift.
     *
     * @param array<string, int> $counts
     * @return string|null A problem, or null when it holds or the counts are not there.
     */
    public static function cross_foot(array $counts): ?string {
        $keys = ['users_tenant_airpay', 'users_tenant_public', 'users_tenant_zeea', 'users_tenant_other',
            'users_total_active'];
        foreach ($keys as $key) {
            if (!isset($counts[$key])) {
                return null;
            }
        }
        $sum = $counts['users_tenant_airpay'] + $counts['users_tenant_public'] + $counts['users_tenant_zeea']
            + $counts['users_tenant_other'];
        if ($sum !== $counts['users_total_active']) {
            return "tenant buckets add up to {$sum}, active users are {$counts['users_total_active']}";
        }
        return null;
    }
}

/**
 * The BizLMS legacy tables: fingerprints, discovery and comparison.
 *
 * The fingerprint is the one cli/import_bizlms.php and parity::legacy_fingerprints() use (fingerprint::table()): count,
 * MAX(id) and SUM(CRC32(CONCAT_WS(0x1f, IFNULL(col, sentinel)...))) over ALL columns sorted by name. A test holds the
 * two implementations to the same result on the same table.
 */
final class legacy {
    /**
     * The tables of the 22 purchased BizLMS plugins, without prefix. A copy of legacy_tables::KNOWN: this file cannot
     * load the plugin, and a test fails when the two lists differ.
     */
    public const KNOWN = [
        'block_request_comments', 'block_request_config', 'block_request_records', 'block_trending_modules',
        'local_bc_completion_criteria', 'local_bc_level_comp_bk', 'local_bc_level_completions',
        'local_bcl_cmplt_criteria', 'local_biz_cart_credits', 'local_biz_cart_history', 'local_biz_cart_id',
        'local_biz_cart_invoices', 'local_biz_cart_ledger', 'local_certificate', 'local_certification',
        'local_challenge', 'local_classroom', 'local_classroom_attendance', 'local_classroom_categories',
        'local_classroom_completion', 'local_classroom_courses', 'local_classroom_sessions',
        'local_classroom_test_score', 'local_classroom_trainerfb', 'local_classroom_trainers',
        'local_classroom_users', 'local_classroom_waitlist', 'local_comment', 'local_costcenter',
        'local_costcenter_permissions', 'local_course_levels', 'local_course_types', 'local_coursedetails',
        'local_courseerrors', 'local_custom_category', 'local_dashboardcourses', 'local_domains',
        'local_email_logs', 'local_emaillogs', 'local_eval_completedtmp', 'local_eval_sitecourse_map',
        'local_eval_valuetmp', 'local_evaluation_completed', 'local_evaluation_item',
        'local_evaluation_template', 'local_evaluation_users', 'local_evaluation_value', 'local_evaluations',
        'local_filters', 'local_groups', 'local_interested_skills', 'local_learningplan',
        'local_learningplan_approval', 'local_learningplan_courses', 'local_learningplan_user', 'local_like',
        'local_location_institutes', 'local_location_room', 'local_logs', 'local_moduleconfig',
        'local_notification_info', 'local_notification_strings', 'local_notification_type',
        'local_onlinetests', 'local_org_dept_roles', 'local_plan_course_status', 'local_positions',
        'local_program', 'local_program_completions_bk', 'local_program_level_courses', 'local_program_levels',
        'local_program_test_score', 'local_program_trainerfb', 'local_program_trainers', 'local_program_users',
        'local_rating', 'local_ratings_likes', 'local_recompletion_cc', 'local_recompletion_cc_cc',
        'local_recompletion_cmc', 'local_recompletion_config', 'local_recompletion_ltia',
        'local_recompletion_qa', 'local_recompletion_qg', 'local_recompletion_qr', 'local_recompletion_qr_bool',
        'local_recompletion_qr_date', 'local_recompletion_qr_m', 'local_recompletion_qr_other',
        'local_recompletion_qr_rank', 'local_recompletion_qr_single', 'local_recompletion_qr_text',
        'local_recompletion_sst', 'local_request_comments', 'local_request_config', 'local_request_form_data',
        'local_request_formfields', 'local_request_records', 'local_skill', 'local_skill_categories',
        'local_skillmatrix', 'local_syncerrors', 'local_tag_mapping', 'local_tags', 'local_transcript_history',
        'local_uniquelogins', 'local_userdata', 'local_userssyncdata',
    ];

    /** Name prefixes of tables that may be legacy. */
    public const PREFIXES = ['local_', 'block_', 'paygw_'];

    /** Prefix of Sentientia's own tables, never legacy. */
    public const SENTIENTIA_PREFIX = 'local_sentientia_';

    /**
     * Stock Moodle tables that carry a legacy prefix (core blocks and the PayPal gateway). They belong to core and
     * change in every upgrade, so they are never legacy.
     */
    public const STOCK = [
        'block_instances', 'block_positions', 'block_recent_activity', 'block_recentlyaccesseditems',
        'block_rss_client', 'paygw_paypal',
    ];

    /**
     * Known BizLMS tables that exist in this database.
     *
     * @param database $db
     * @return string[] Sorted.
     */
    public static function known_present(database $db): array {
        $have = array_flip($db->tables());
        $out = [];
        foreach (self::KNOWN as $table) {
            if (isset($have[$table])) {
                $out[] = $table;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Tables with a legacy prefix that no BizLMS inventory names and that are not Sentientia's or stock Moodle's.
     * Third-party plugins' tables (a reporting block, a payment gateway of its own) land here. Informational: their
     * owner may be a plugin that is installed on the target, so a change there is reported as unproven, not as failed.
     *
     * @param database $db
     * @return string[] Sorted.
     */
    public static function other_present(database $db): array {
        $known = array_flip(self::KNOWN);
        $stock = array_flip(self::STOCK);
        $out = [];
        foreach ($db->tables() as $table) {
            if (isset($known[$table]) || isset($stock[$table])
                    || strncmp($table, self::SENTIENTIA_PREFIX, strlen(self::SENTIENTIA_PREFIX)) === 0) {
                continue;
            }
            foreach (self::PREFIXES as $prefix) {
                if (strncmp($table, $prefix, strlen($prefix)) === 0) {
                    $out[] = $table;
                    break;
                }
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Count, max id, CRC over all columns (sorted by name) and the column list of one table. No row cap.
     *
     * @param database $db
     * @param string $table
     * @return array{count: int, maxid: int, crc: ?string, columns: string[]}
     */
    public static function fingerprint(database $db, string $table): array {
        sql::identifier($table);
        $columns = $db->columns($table);
        sort($columns);
        $count = (int) $db->scalar('SELECT COUNT(1) FROM {' . $table . '} t');
        $maxid = in_array('id', $columns, true) ? (int) $db->scalar('SELECT MAX(t.id) FROM {' . $table . '} t') : 0;
        $crc = null;
        if ($db->family() === 'mysql' && $columns) {
            $crc = (string) $db->scalar('SELECT COALESCE(SUM(CRC32(' . sql::row_text($columns, 't')
                . ')), 0) FROM {' . $table . '} t');
        }
        return ['count' => $count, 'maxid' => $maxid, 'crc' => $crc, 'columns' => $columns];
    }

    /**
     * @param database $db
     * @param string[] $tables
     * @return array<string, array> Fingerprints of the tables that exist.
     */
    public static function fingerprints(database $db, array $tables): array {
        $have = array_flip($db->tables());
        $out = [];
        foreach ($tables as $table) {
            if (isset($have[$table])) {
                $out[$table] = self::fingerprint($db, $table);
            }
        }
        return $out;
    }

    /**
     * A mirror of parity::compare_fingerprints() for runs that have no Sentientia code. Same rules: a missing table is
     * drift, a column change is drift, a skipped CRC is never a pass.
     *
     * @param array $baseline
     * @param array $current
     * @return array{drift: string[], missing: string[], skipped: string[], new: string[]}
     */
    public static function compare(array $baseline, array $current): array {
        $out = ['drift' => [], 'missing' => [], 'skipped' => [], 'new' => []];
        foreach ($baseline as $table => $was) {
            if (!isset($current[$table])) {
                $out['missing'][] = $table;
                continue;
            }
            $now = $current[$table];
            if ((int) $was['count'] !== (int) $now['count'] || (int) $was['maxid'] !== (int) $now['maxid']
                    || array_values((array) $was['columns']) !== array_values((array) $now['columns'])) {
                $out['drift'][] = "{$table} rows {$was['count']}->{$now['count']} maxid {$was['maxid']}->{$now['maxid']}";
                continue;
            }
            if ($was['crc'] === null || $now['crc'] === null) {
                $out['skipped'][] = $table;
                continue;
            }
            if ((string) $was['crc'] !== (string) $now['crc']) {
                $out['drift'][] = "{$table} crc {$was['crc']}->{$now['crc']}";
            }
        }
        foreach (array_diff_key($current, $baseline) as $table => $unused) {
            $out['new'][] = $table;
        }
        return $out;
    }

    /**
     * A mirror of parity::comparison_problems(): drift and a missing table are hard (exit 1), a skipped CRC and a table
     * the baseline does not know are unproven (exit 2).
     *
     * @param array{drift: string[], missing: string[], skipped: string[], new: string[]} $comparison
     * @return array{hard: string[], unproven: string[]}
     */
    public static function problems(array $comparison): array {
        $hard = [];
        $unproven = [];
        foreach ($comparison['drift'] as $line) {
            $hard[] = 'legacy_table_changed:' . $line;
        }
        foreach ($comparison['missing'] as $table) {
            $hard[] = 'legacy_table_missing:' . $table;
        }
        foreach ($comparison['skipped'] as $table) {
            $unproven[] = 'legacy_table_crc_skipped:' . $table;
        }
        foreach ($comparison['new'] as $table) {
            $unproven[] = 'legacy_table_not_in_the_baseline:' . $table;
        }
        return ['hard' => $hard, 'unproven' => $unproven];
    }

    /**
     * The "other" tables compared as soft: any difference is unproven, never failed.
     *
     * @param array $baseline
     * @param array $current
     * @return string[] Unproven lines.
     */
    public static function other_unproven(array $baseline, array $current): array {
        $cmp = self::compare($baseline, $current);
        $out = [];
        foreach ($cmp['drift'] as $line) {
            $out[] = 'other_table_changed:' . $line;
        }
        foreach ($cmp['missing'] as $table) {
            $out[] = 'other_table_missing:' . $table;
        }
        foreach ($cmp['skipped'] as $table) {
            $out[] = 'other_table_crc_skipped:' . $table;
        }
        return $out;
    }
}

/**
 * The core tables the import may write (registry::CORE_WRITES_ALLOWED), and what it takes to explain every change the
 * import made to them (ADR-032 "Parity hooks" 4, Stage B gate 4).
 *
 * The baseline stores, for each of them that exists:
 *  - insert tables (user_enrolments, role_assignments): count, MAX(id) and one CRC over the fixed columns of
 *    every row. After the import the rows with id <= that MAX(id) must still hash to it (nothing old changed or went),
 *    and the rows above it must be exactly the rows the import's own map names as imported into that table.
 *  - update tables (course, tag_instance, enrol): the same, plus a hash per row of the fixed columns and one per writable
 *    column. After the import a row may differ from the baseline in a writable column only, and only a row and a column the
 *    import's own ledger names. An update table may ALSO be inserted into (enrol is): the rows above MAX(id) are always held
 *    to the rows the map names, whatever the mode.
 *
 * The fixed column lists are explicit for the reason given at metrics::CHECKSUMS.
 */
final class core {
    /** Most rows of an update table whose per-row hashes the baseline keeps. Above it the table is checked in aggregate. */
    public const ROW_LIMIT = 300000;

    /** Rows per page when per-row hashes are read. */
    private const PAGE = 5000;

    /**
     * @var array<string, array{mode: string, fixed: string[], writable: string[]}>
     */
    public const WRITES = [
        'user_enrolments' => ['mode' => 'insert', 'writable' => [],
            'fixed' => ['id', 'enrolid', 'userid', 'status', 'timestart', 'timeend', 'modifierid', 'timecreated',
                'timemodified']],
        // enrol is both: the enrolments importer INSERTs the manual instance a course lacks (G6), and (owner decision CRS-01) it
        // UPDATEs the status of a BizLMS instance it proved safe to switch off, together with timemodified. So it is held as an
        // 'update' table: every old row must keep every other column, and may differ in these two only when the importer's trail
        // (local_sentientia_courses_enroloff) names the row. The new rows are still the rows the import's map says it inserted.
        'enrol' => ['mode' => 'update', 'writable' => ['status', 'timemodified'],
            'fixed' => ['id', 'enrol', 'courseid', 'sortorder', 'name', 'enrolperiod', 'enrolstartdate',
                'enrolenddate', 'expirynotify', 'expirythreshold', 'notifyall', 'password', 'cost', 'currency',
                'roleid', 'customint1', 'customint2', 'customint3', 'customint4', 'customint5', 'customint6',
                'customint7', 'customint8', 'customchar1', 'customchar2', 'customchar3', 'customdec1', 'customdec2',
                'timecreated']],
        'role_assignments' => ['mode' => 'insert', 'writable' => [],
            'fixed' => ['id', 'roleid', 'contextid', 'userid', 'timemodified', 'modifierid', 'component', 'itemid',
                'sortorder']],
        // The course_lookups fill writes these eight empty open_* columns and nothing else, and never course.timemodified.
        'course' => ['mode' => 'update',
            'writable' => ['open_cost', 'open_coursecompletiondays', 'open_coursecreator', 'open_identifiedas',
                'open_requestcourseid', 'open_skill', 'open_level', 'open_points'],
            'fixed' => ['id', 'category', 'shortname', 'fullname', 'idnumber', 'format', 'visible', 'startdate',
                'enddate', 'open_path', 'open_categoryid', 'open_certificateid', 'approvalreqd', 'selfenrol',
                'open_securecourse', 'open_coursetype', 'price_status']],
        // The course_tags remap moves a tag instance from the BizLMS area to the core course area: component, itemtype.
        'tag_instance' => ['mode' => 'update', 'writable' => ['component', 'itemtype'],
            'fixed' => ['id', 'tagid', 'itemid', 'contextid', 'tiuserid', 'ordering', 'timecreated']],
    ];

    /**
     * What the baseline keeps of the core tables.
     *
     * @param database $db
     * @return array<string, array>
     */
    public static function baseline(database $db): array {
        $out = [];
        foreach (self::WRITES as $table => $spec) {
            if (!$db->table_exists($table)) {
                continue;
            }
            $existing = $db->columns($table);
            $fixed = array_values(array_intersect($spec['fixed'], $existing));
            $writable = array_values(array_intersect($spec['writable'], $existing));
            if (!in_array('id', $fixed, true)) {
                continue;
            }
            $entry = [
                'mode' => $spec['mode'],
                'count' => sql::count($db, $table),
                'maxid' => (int) $db->scalar('SELECT MAX(t.id) FROM {' . $table . '} t'),
                'crc' => null,
                'fixed' => $fixed,
                'writable' => $writable,
            ];
            if ($db->family() === 'mysql') {
                $entry['crc'] = (string) $db->scalar('SELECT COALESCE(SUM(CRC32(' . sql::row_text($fixed, 't')
                    . ')), 0) FROM {' . $table . '} t');
                if ($spec['mode'] === 'update') {
                    if ($entry['count'] <= self::ROW_LIMIT) {
                        $entry['rows'] = self::row_hashes($db, $table, $fixed, $writable);
                    } else {
                        $entry['rows_omitted'] = true;
                    }
                }
            }
            $out[$table] = $entry;
        }
        return $out;
    }

    /**
     * Per-row hashes, one string per row: "id|fixedcrc|w1,w2,..." (a NULL writable value hashes like any other).
     *
     * @param database $db
     * @param string $table
     * @param string[] $fixed
     * @param string[] $writable
     * @return string[]
     */
    private static function row_hashes(database $db, string $table, array $fixed, array $writable): array {
        $out = [];
        foreach (self::pages($db, $table, $fixed, $writable, 0, null) as $row) {
            $out[] = $row['id'] . '|' . $row['f'] . '|' . implode(',', $row['w']);
        }
        return $out;
    }

    /**
     * Page through a table in id order, yielding the hashes of each row.
     *
     * @param database $db
     * @param string $table
     * @param string[] $fixed
     * @param string[] $writable
     * @param int $after Rows with id above this.
     * @param int|null $upto Rows with id at or below this, or null for all.
     * @return \Generator
     */
    private static function pages(database $db, string $table, array $fixed, array $writable, int $after, ?int $upto): \Generator {
        $select = 't.id AS id, CRC32(' . sql::row_text($fixed, 't') . ') AS f';
        foreach ($writable as $i => $column) {
            $select .= ', CRC32(IFNULL(t.`' . sql::identifier($column) . "`, '" . sql::NULLTOKEN . "')) AS w{$i}";
        }
        do {
            $rows = $db->rows('SELECT ' . $select . ' FROM {' . $table . '} t WHERE t.id > ' . (int) $after
                . ($upto === null ? '' : ' AND t.id <= ' . (int) $upto) . ' ORDER BY t.id LIMIT ' . self::PAGE);
            foreach ($rows as $row) {
                $w = [];
                foreach ($writable as $i => $unused) {
                    $w[] = (string) $row["w{$i}"];
                }
                $after = (int) $row['id'];
                yield ['id' => $after, 'f' => (string) $row['f'], 'w' => $w];
            }
        } while (count($rows) === self::PAGE);
    }

    /**
     * Read the current state of every core table the baseline holds, measured against the baseline's own boundaries.
     *
     * @param database $db
     * @param array<string, array> $base The baseline's core section.
     * @return array<string, array> Evidence per table for evaluate().
     */
    public static function evidence(database $db, array $base): array {
        $out = [];
        $mysql = $db->family() === 'mysql';
        foreach ($base as $table => $b) {
            if (!$db->table_exists($table)) {
                $out[$table] = ['missing' => true];
                continue;
            }
            $existing = $db->columns($table);
            $lost = array_diff(array_merge((array) $b['fixed'], (array) $b['writable']), $existing);
            if ($lost) {
                $out[$table] = ['columns_missing' => array_values($lost)];
                continue;
            }
            $maxid = (int) $b['maxid'];
            $e = [
                'count' => sql::count($db, $table),
                'old_count' => sql::count($db, $table, 't.id <= ' . $maxid),
                'old_crc' => null,
                'new_ids' => [],
                'changed' => [],
                'fixed_changed' => [],
                'removed' => [],
            ];
            if ($mysql) {
                $e['old_crc'] = (string) $db->scalar('SELECT COALESCE(SUM(CRC32(' . sql::row_text((array) $b['fixed'], 't')
                    . ')), 0) FROM {' . $table . '} t WHERE t.id <= ' . $maxid);
            }
            // Rows added after the baseline, by id.
            $after = $maxid;
            do {
                $rows = $db->rows('SELECT t.id AS id FROM {' . $table . '} t WHERE t.id > ' . (int) $after
                    . ' ORDER BY t.id LIMIT 20000');
                foreach ($rows as $row) {
                    $after = (int) $row['id'];
                    $e['new_ids'][] = $after;
                }
            } while (count($rows) === 20000);

            if ($b['mode'] === 'update' && $mysql) {
                if (!isset($b['rows'])) {
                    $e['rows_omitted'] = true;
                } else {
                    $e = array_merge($e, self::row_diff($db, $table, $b));
                }
            }
            $out[$table] = $e;
        }
        return $out;
    }

    /**
     * Compare per-row hashes of an update table with the baseline's, for the rows the baseline knew.
     *
     * @param database $db
     * @param string $table
     * @param array $b The baseline entry.
     * @return array{changed: array<int, string[]>, fixed_changed: int[], removed: int[]}
     */
    private static function row_diff(database $db, string $table, array $b): array {
        $base = [];
        foreach ((array) $b['rows'] as $line) {
            $parts = explode('|', $line, 3);
            $base[(int) $parts[0]] = [$parts[1], $parts[2] === '' ? [] : explode(',', $parts[2])];
        }
        $changed = [];
        $fixedchanged = [];
        foreach (self::pages($db, $table, (array) $b['fixed'], (array) $b['writable'], 0, (int) $b['maxid']) as $row) {
            if (!isset($base[$row['id']])) {
                // An id at or below the baseline's maximum that the baseline did not have.
                $changed[$row['id']] = ['(a row the baseline did not have)'];
                continue;
            }
            [$f, $w] = $base[$row['id']];
            if ((string) $f !== $row['f']) {
                $fixedchanged[] = $row['id'];
            }
            foreach ((array) $b['writable'] as $i => $column) {
                if ((string) ($w[$i] ?? '') !== $row['w'][$i]) {
                    $changed[$row['id']][] = $column;
                }
            }
            unset($base[$row['id']]);
        }
        return ['changed' => $changed, 'fixed_changed' => $fixedchanged, 'removed' => array_keys($base)];
    }

    /**
     * Decide whether every change to the core tables is explained.
     *
     * Pure: the baseline's core section, the evidence read from the current database, and what the import's own records
     * say it wrote.
     *
     * @param array<string, array> $base
     * @param array<string, array> $evidence
     * @param array<string, array{inserted?: int[], changed?: array<int, string[]>}> $expected From the import's map and ledgers.
     * @return array{hard: string[], unproven: string[], lines: string[]}
     */
    public static function evaluate(array $base, array $evidence, array $expected): array {
        $hard = [];
        $unproven = [];
        $lines = [];
        foreach ($base as $table => $b) {
            $e = $evidence[$table] ?? null;
            if ($e === null || !empty($e['missing'])) {
                $hard[] = "core_table_missing:{$table}";
                continue;
            }
            if (!empty($e['columns_missing'])) {
                $hard[] = "core_columns_missing:{$table}:" . implode(',', $e['columns_missing']);
                continue;
            }
            $want = $expected[$table] ?? [];

            // 1. Nothing the baseline had has changed or gone.
            if ((int) $e['old_count'] !== (int) $b['count']) {
                $hard[] = "core_rows_changed:{$table}: rows with an old id {$b['count']}->{$e['old_count']}";
            }
            if ($b['crc'] === null || $e['old_crc'] === null) {
                $unproven[] = "core_crc_skipped:{$table}";
            } else if ((string) $b['crc'] !== (string) $e['old_crc']) {
                $hard[] = "core_rows_changed:{$table}: crc of the old rows {$b['crc']}->{$e['old_crc']}"
                    . ($b['mode'] === 'update' ? '' : ' (the import only inserts into this table)');
            }

            // 2. The rows added are exactly the rows the import says it inserted.
            $inserted = array_map('intval', (array) ($want['inserted'] ?? []));
            $added = array_map('intval', (array) $e['new_ids']);
            $unexplained = array_values(array_diff($added, $inserted));
            $absent = array_values(array_diff($inserted, $added));
            if ($unexplained) {
                $hard[] = "core_rows_added_not_in_the_import:{$table}: " . count($unexplained) . ' (ids '
                    . self::sample($unexplained) . ')';
            }
            if ($absent) {
                $hard[] = "core_rows_in_the_import_not_found:{$table}: " . count($absent) . ' (ids '
                    . self::sample($absent) . ')';
            }

            // 3. Update tables: only the writable columns of the rows the ledger names.
            $changedtxt = '';
            if ($b['mode'] === 'update') {
                if (!empty($e['rows_omitted'])) {
                    $unproven[] = "core_rows_not_compared:{$table}";
                } else {
                    foreach ((array) $e['fixed_changed'] as $id) {
                        $hard[] = "core_fixed_column_changed:{$table}:id=" . (int) $id;
                    }
                    foreach ((array) $e['removed'] as $id) {
                        $hard[] = "core_row_removed:{$table}:id=" . (int) $id;
                    }
                    $wantchanged = (array) ($want['changed'] ?? []);
                    $gotchanged = (array) $e['changed'];
                    foreach ($gotchanged as $id => $columns) {
                        $named = $wantchanged[$id] ?? null;
                        if ($named === null) {
                            $hard[] = "core_row_changed_not_in_the_import:{$table}:id={$id} columns " . implode(',', $columns);
                            continue;
                        }
                        $extra = array_diff($columns, (array) $named);
                        if ($extra) {
                            $hard[] = "core_column_changed_not_in_the_import:{$table}:id={$id} columns " . implode(',', $extra);
                        }
                        $unchanged = array_diff((array) $named, $columns);
                        if ($unchanged) {
                            $hard[] = "core_ledger_names_an_unchanged_column:{$table}:id={$id} columns "
                                . implode(',', $unchanged);
                        }
                    }
                    foreach (array_diff_key($wantchanged, $gotchanged) as $id => $columns) {
                        $hard[] = "core_ledger_names_an_unchanged_row:{$table}:id={$id}";
                    }
                    $changedtxt = ', ' . count($gotchanged) . ' row(s) changed in ' . implode('/', (array) $b['writable']);
                }
            }
            $lines[] = sprintf('  %-16s rows %d->%d (+%d inserted by the import)%s', $table, (int) $b['count'],
                (int) $e['count'], count($added), $changedtxt);
        }
        return ['hard' => $hard, 'unproven' => $unproven, 'lines' => $lines];
    }

    /**
     * @param int[] $ids
     * @return string The first ten, then "and N more".
     */
    private static function sample(array $ids): string {
        sort($ids);
        $head = array_slice($ids, 0, 10);
        return implode(',', $head) . (count($ids) > 10 ? ' and ' . (count($ids) - 10) . ' more' : '');
    }
}

/**
 * Build, compare and print.
 */
final class baseline {
    /**
     * The whole baseline document.
     *
     * @param database $db
     * @param array{wwwroot?: string, release?: string, version?: string, tool?: string} $meta
     * @param callable|null $progress function (string $phase, float $seconds): void
     * @param string[] $parts Which parts to read: 'metrics', 'legacy' (the BizLMS tables and the other legacy-prefix tables)
     *        and 'core'. A comparison reads only the metrics here and fingerprints the baseline's own tables by name.
     * @return array
     */
    public static function build(database $db, array $meta, ?callable $progress = null,
                                 array $parts = ['metrics', 'legacy', 'core']): array {
        $timings = [];
        $time = static function (string $phase, callable $fn) use (&$timings, $progress) {
            $start = microtime(true);
            $result = $fn();
            $timings[$phase] = round(microtime(true) - $start, 2);
            if ($progress !== null) {
                $progress($phase, $timings[$phase]);
            }
            return $result;
        };

        $m = $time('metrics', static function () use ($db) {
            return metrics::collect($db);
        });
        $known = [];
        $other = [];
        if (in_array('legacy', $parts, true)) {
            $known = $time('legacy', static function () use ($db) {
                return legacy::fingerprints($db, legacy::known_present($db));
            });
            $other = $time('legacy_other', static function () use ($db) {
                return legacy::fingerprints($db, legacy::other_present($db));
            });
        }
        $core = [];
        if (in_array('core', $parts, true)) {
            $core = $time('core', static function () use ($db) {
                return core::baseline($db);
            });
        }

        return [
            'format' => 2,
            'captured_at' => time(),
            'wwwroot' => (string) ($meta['wwwroot'] ?? ''),
            'release' => (string) ($meta['release'] ?? ''),
            'version' => (string) ($meta['version'] ?? ''),
            'dbfamily' => $db->family(),
            'dbserver' => $db->server(),
            'tool' => ['name' => (string) ($meta['tool'] ?? 'source_baseline.php'), 'metrics' => metrics::VERSION,
                'sha256' => metrics::tool_sha256(), 'php' => PHP_VERSION],
            'layout' => $m['layout'],
            'notes' => $m['notes'],
            'timings' => $timings,
            'counts' => $m['counts'],
            'aggregates' => $m['aggregates'],
            'checksums' => $m['checksums'],
            'legacy' => $known,
            'legacy_other' => $other,
            'core' => $core,
        ];
    }

    /**
     * Compare counts, aggregates and checksums of a baseline with the current snapshot, printing a line per metric.
     *
     * $explained lets the post-import mode account for what the import itself added: counts that grew by a known amount,
     * and checksummed tables whose whole-table CRC is replaced by the core check.
     *
     * @param array $base A baseline document.
     * @param array $now The current document (build()).
     * @param callable $out function (string $line): void
     * @param array{counts?: array<string, int>, skip_checksums?: string[]} $explained
     * @return array{drift: int, skipped: int}
     */
    public static function compare_metrics(array $base, array $now, callable $out, array $explained = []): array {
        $drift = 0;
        $skipped = 0;
        $addcounts = (array) ($explained['counts'] ?? []);

        foreach ((array) $base['counts'] as $key => $expected) {
            $got = $now['counts'][$key] ?? null;
            $plus = (int) ($addcounts[$key] ?? 0);
            if ($got !== null && $got === (int) $expected + $plus) {
                $out(sprintf('  MATCH %-24s %d%s', $key, $got,
                    $plus > 0 ? " (baseline {$expected} + {$plus} the import inserted)" : ''));
            } else {
                $out(sprintf('  DRIFT %-24s expected %s got %s', $key, var_export((int) $expected + $plus, true),
                    var_export($got, true)));
                $drift++;
            }
        }
        // New metrics present now but absent from the baseline are informational.
        foreach (array_diff_key((array) $now['counts'], (array) $base['counts']) as $key => $value) {
            $out(sprintf('  NEW   %-24s %d (not in baseline)', $key, $value));
        }
        // Module types the baseline had and this release does not: the Moodle 5.0 upgrade uninstalls mod_survey and mod_chat when
        // their code is not on disk, and uninstalling a module type deletes every activity of it (course_modules, their
        // completion rows and the instance tables). Informational: the drift of the counts above is the verdict, this says why.
        if (isset($base['layout']['modules'], $now['layout']['modules'])) {
            $gone = array_values(array_diff((array) $base['layout']['modules'], (array) $now['layout']['modules']));
            if ($gone) {
                $out('  NOTE  module type(s) the baseline had and this release does not have: ' . implode(', ', $gone)
                    . ' - their activities are gone with them (the tool cannot accept this loss: put a 5.x version of the plugin in the package before the upgrade; the rehearsal kit refuses to start hop 2 otherwise)');
            }
        }

        if (!empty($base['aggregates'])) {
            $out('');
            $out('Value aggregates:');
            foreach ((array) $base['aggregates'] as $key => $expected) {
                $got = $now['aggregates'][$key] ?? null;
                if ($got !== null && (string) $got === (string) $expected) {
                    $out(sprintf('  MATCH %-24s %s', $key, $got));
                } else {
                    $out(sprintf('  DRIFT %-24s expected %s got %s', $key, $expected, var_export($got, true)));
                    $drift++;
                }
            }
        }

        // Value checksums. A migration can preserve every count while changing what is in the rows.
        $out('');
        $out('Value checksums:');
        $skip = array_flip((array) ($explained['skip_checksums'] ?? []));
        if (empty($base['checksums'])) {
            $out('  SKIPPED - the baseline predates value checksums.');
            $skipped++;
        } else {
            foreach ((array) $base['checksums'] as $table => $basecs) {
                if (isset($skip[$table])) {
                    $out(sprintf('  SEE CORE %-23s (whole-table checksum replaced by the core check below)', $table));
                    continue;
                }
                $cs = $now['checksums'][$table] ?? null;
                if ($cs === null) {
                    $out(sprintf('  MISSING %-26s in baseline, absent here', $table));
                    $drift++;
                    continue;
                }
                if ($basecs['crc'] === null || $cs['crc'] === null) {
                    $out(sprintf('  SKIPPED %-26s (checksums unsupported on one side; baseline=%s, here=%s)', $table,
                        $base['dbfamily'] ?? '?', $now['dbfamily'] ?? '?'));
                    $skipped++;
                    continue;
                }
                if ((string) $basecs['crc'] === (string) $cs['crc'] && (int) $basecs['rows'] === (int) $cs['rows']) {
                    $out(sprintf('  MATCH   %-26s rows=%d', $table, $cs['rows']));
                } else {
                    $why = '';
                    if (isset($basecs['cols'], $cs['cols']) && $basecs['cols'] !== $cs['cols']) {
                        $why = ' (column set differs: ' . implode(',', array_diff($basecs['cols'], $cs['cols'])) . ' / '
                            . implode(',', array_diff($cs['cols'], $basecs['cols'])) . ')';
                    }
                    $out(sprintf('  DRIFT   %-26s rows %d->%d  crc %s->%s%s', $table, (int) $basecs['rows'],
                        (int) $cs['rows'], $basecs['crc'], $cs['crc'], $why));
                    $drift++;
                }
            }
        }
        return ['drift' => $drift, 'skipped' => $skipped];
    }

    /**
     * Print the BizLMS legacy-table comparison and return its hard and unproven items.
     *
     * @param array{drift: string[], missing: string[], skipped: string[], new: string[]} $comparison Of the 22 plugins' tables.
     * @param string[] $otherunproven Unproven lines for the tables no inventory names.
     * @param int $knowncount How many BizLMS tables the baseline holds.
     * @param callable $out function (string $line): void
     * @return array{hard: string[], unproven: string[]}
     */
    public static function print_legacy(array $comparison, array $otherunproven, int $knowncount, callable $out): array {
        $out('');
        $out('BizLMS legacy tables (the archive: the import and the upgrades never write them):');
        $problems = legacy::problems($comparison);
        $unproven = array_merge($problems['unproven'], $otherunproven);
        if (!$problems['hard'] && !$unproven) {
            $out(sprintf('  MATCH   %d table(s): count, max id, columns and a CRC over every column of every row',
                $knowncount));
        }
        foreach ($problems['hard'] as $line) {
            $out('  DRIFT   ' . $line);
        }
        foreach ($unproven as $line) {
            $out('  UNPROVEN ' . $line);
        }
        return ['hard' => $problems['hard'], 'unproven' => $unproven];
    }
}

/**
 * Reading a Moodle config.php as data, without running it.
 *
 * Only `$CFG->name = <literal>;` statements are read: strings, numbers, true/false/null, arrays, `.` and `|` between
 * them, __DIR__, MYSQLI_* constants and getenv('NAME'). Anything else is skipped, and a missing database setting then
 * says which one, so the operator passes it with an explicit option.
 */
final class config {
    /** @var string Directory of the config file being read (for __DIR__). */
    private static $dir = '';

    /**
     * @param string $path
     * @return array<string, mixed> Setting name => value.
     */
    public static function settings(string $path): array {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('config.php not found or not readable: ' . $path);
        }
        if (!function_exists('token_get_all')) {
            throw new \RuntimeException('The tokenizer extension is not loaded: pass the database settings as options.');
        }
        self::$dir = dirname(realpath($path) ?: $path);
        return self::literals((string) file_get_contents($path));
    }

    /**
     * @param string $code PHP source.
     * @return array<string, mixed>
     */
    public static function literals(string $code): array {
        $tokens = [];
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $tokens[] = $token;
        }
        $out = [];
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            if (!(is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE && $tokens[$i][1] === '$CFG'
                    && isset($tokens[$i + 1], $tokens[$i + 2], $tokens[$i + 3])
                    && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_OBJECT_OPERATOR
                    && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_STRING
                    && $tokens[$i + 3] === '=')) {
                continue;
            }
            $j = $i + 4;
            try {
                $value = self::expression($tokens, $j);
            } catch (\RuntimeException $e) {
                continue;
            }
            if (isset($tokens[$j]) && $tokens[$j] === ';') {
                $out[$tokens[$i + 2][1]] = $value;
                $i = $j;
            }
        }
        return $out;
    }

    /**
     * @param array $t Tokens.
     * @param int $i Position, advanced.
     * @return mixed
     */
    private static function expression(array $t, int &$i) {
        $value = self::term($t, $i);
        while (isset($t[$i]) && ($t[$i] === '.' || $t[$i] === '|')) {
            $op = $t[$i];
            $i++;
            $right = self::term($t, $i);
            $value = $op === '.' ? ((string) $value . (string) $right) : ((int) $value | (int) $right);
        }
        return $value;
    }

    /**
     * @param array $t
     * @param int $i
     * @return mixed
     */
    private static function term(array $t, int &$i) {
        if (!isset($t[$i])) {
            throw new \RuntimeException('end of file');
        }
        $tok = $t[$i];
        if ($tok === '-' && isset($t[$i + 1]) && is_array($t[$i + 1])
                && in_array($t[$i + 1][0], [T_LNUMBER, T_DNUMBER], true)) {
            $number = $t[$i + 1];
            $i += 2;
            return $number[0] === T_LNUMBER ? -(int) $number[1] : -(float) $number[1];
        }
        if ($tok === '[') {
            $i++;
            return self::items($t, $i, ']');
        }
        if (!is_array($tok)) {
            throw new \RuntimeException('not a literal');
        }
        switch ($tok[0]) {
            case T_CONSTANT_ENCAPSED_STRING:
                $i++;
                $quote = $tok[1][0];
                $body = substr($tok[1], 1, -1);
                return $quote === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $body) : stripcslashes($body);
            case T_LNUMBER:
            case T_DNUMBER:
                $i++;
                return $tok[0] === T_LNUMBER ? (int) $tok[1] : (float) $tok[1];
            case T_DIR:
                $i++;
                return self::$dir;
            case T_ARRAY:
                if (isset($t[$i + 1]) && $t[$i + 1] === '(') {
                    $i += 2;
                    return self::items($t, $i, ')');
                }
                throw new \RuntimeException('not a literal');
            case T_STRING:
                $name = $tok[1];
                $lower = strtolower($name);
                if ($lower === 'true' || $lower === 'false' || $lower === 'null') {
                    $i++;
                    return $lower === 'true' ? true : ($lower === 'false' ? false : null);
                }
                if ($lower === 'getenv' && isset($t[$i + 1], $t[$i + 2], $t[$i + 3]) && $t[$i + 1] === '('
                        && is_array($t[$i + 2]) && $t[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING && $t[$i + 3] === ')') {
                    $env = getenv(substr($t[$i + 2][1], 1, -1));
                    if ($env === false) {
                        throw new \RuntimeException('environment variable not set');
                    }
                    $i += 4;
                    return $env;
                }
                if (preg_match('/^MYSQLI_[A-Z0-9_]+$/', $name) && defined($name)) {
                    $i++;
                    return constant($name);
                }
                throw new \RuntimeException('not a literal');
        }
        throw new \RuntimeException('not a literal');
    }

    /**
     * @param array $t
     * @param int $i
     * @param string $close The closing token.
     * @return array
     */
    private static function items(array $t, int &$i, string $close): array {
        $out = [];
        while (isset($t[$i]) && $t[$i] !== $close) {
            $first = self::expression($t, $i);
            if (isset($t[$i]) && is_array($t[$i]) && $t[$i][0] === T_DOUBLE_ARROW) {
                $i++;
                $out[(string) $first] = self::expression($t, $i);
            } else {
                $out[] = $first;
            }
            if (isset($t[$i]) && $t[$i] === ',') {
                $i++;
            }
        }
        if (!isset($t[$i])) {
            throw new \RuntimeException('unterminated array');
        }
        $i++;
        return $out;
    }

    /**
     * The connection settings of a config.php, with explicit options taking precedence.
     *
     * @param string $path config.php, or '' when every setting is passed as an option.
     * @param array<string, string> $override Options: dbhost, dbname, dbuser, dbpass, prefix, dbport, dbsocket.
     * @return array{host: string, user: string, pass: string, name: string, prefix: string, port: int, socket: string, flags: int, wwwroot: string}
     */
    public static function database(string $path, array $override = []): array {
        $cfg = $path !== '' ? self::settings($path) : [];
        $type = (string) ($cfg['dbtype'] ?? 'mysqli');
        if (!in_array($type, ['mysqli', 'mariadb', 'auroramysql'], true)) {
            throw new \RuntimeException("Database type '{$type}' is not supported: the checksums need MySQL or MariaDB.");
        }
        $options = isset($cfg['dboptions']) && is_array($cfg['dboptions']) ? $cfg['dboptions'] : [];
        $pick = static function (string $name, string $setting) use ($cfg, $override): string {
            if (isset($override[$name]) && $override[$name] !== '') {
                return (string) $override[$name];
            }
            return isset($cfg[$setting]) && !is_array($cfg[$setting]) ? (string) $cfg[$setting] : '';
        };
        $out = [
            'host' => $pick('dbhost', 'dbhost'),
            'user' => $pick('dbuser', 'dbuser'),
            'pass' => $pick('dbpass', 'dbpass'),
            'name' => $pick('dbname', 'dbname'),
            'prefix' => isset($override['prefix']) && $override['prefix'] !== '' ? (string) $override['prefix']
                : (string) ($cfg['prefix'] ?? ''),
            'port' => (int) (($override['dbport'] ?? '') !== '' ? $override['dbport'] : ($options['dbport'] ?? 0)),
            'socket' => (string) (($override['dbsocket'] ?? '') !== '' ? $override['dbsocket'] : ($options['dbsocket'] ?? '')),
            'flags' => (int) ($options['clientflags'] ?? 0),
            'wwwroot' => (string) ($cfg['wwwroot'] ?? ''),
        ];
        foreach (['host' => 'dbhost', 'user' => 'dbuser', 'name' => 'dbname'] as $key => $option) {
            if ($out[$key] === '') {
                throw new \RuntimeException("No {$option}: config.php does not hold it as a plain value; pass --{$option}=VALUE.");
            }
        }
        return $out;
    }

    /**
     * The nearest config.php above a directory.
     *
     * @param string $from
     * @return string|null
     */
    public static function find(string $from): ?string {
        $dir = $from;
        for ($i = 0; $i < 6; $i++) {
            if (is_file($dir . '/config.php')) {
                return $dir . '/config.php';
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
        return null;
    }
}

/**
 * The standalone command line.
 *
 * @param string[] $argv
 * @return int Exit code.
 */
function main(array $argv): int {
    $opts = ['config' => '', 'baseline' => '', 'compare' => '', 'dbhost' => '', 'dbname' => '', 'dbuser' => '',
        'dbpass-env' => '', 'prefix' => '', 'dbport' => '', 'dbsocket' => '', 'help' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } else if (preg_match('/^--(config|baseline|compare|dbhost|dbname|dbuser|dbpass-env|prefix|dbport|dbsocket)=(.*)$/s',
                $arg, $m)) {
            $opts[$m[1]] = $m[2];
        } else {
            fwrite(STDERR, "Unrecognised option: {$arg}\n");
            return 3;
        }
    }
    if ($opts['help']) {
        echo "Stage B parity metrics, standalone (no Moodle, no Sentientia plugin needed).\n\n"
            . "  --config=FILE      the Moodle config.php of the database to read (default: the nearest above this file)\n"
            . "  --baseline=FILE    write the baseline JSON\n"
            . "  --compare=FILE     compare this database with a baseline (exit 0 parity, 1 drift, 2 not proven)\n"
            . "  (neither)          print the metrics\n"
            . "  --dbhost --dbname --dbuser --prefix --dbport --dbsocket --dbpass-env=VARIABLE\n"
            . "                     explicit connection settings, for a config.php that does not hold them as plain values\n\n"
            . "Read-only: the session is read-only and every statement is a SELECT or SHOW.\n"
            . "Exit codes: 0 done or parity, 1 drift, 2 counts match but not proven, 3 the tool could not run.\n";
        return 0;
    }
    if ($opts['baseline'] !== '' && $opts['compare'] !== '') {
        fwrite(STDERR, "Use --baseline or --compare, not both.\n");
        return 3;
    }

    try {
        $explicit = $opts['dbhost'] !== '' || $opts['dbname'] !== '' || $opts['dbuser'] !== '';
        $configpath = $opts['config'] !== '' ? $opts['config'] : ($explicit ? '' : (string) config::find(__DIR__));
        if ($configpath === '' && !$explicit) {
            throw new \RuntimeException('No config.php found above this file: pass --config=FILE.');
        }
        $override = ['dbhost' => $opts['dbhost'], 'dbname' => $opts['dbname'], 'dbuser' => $opts['dbuser'],
            'prefix' => $opts['prefix'], 'dbport' => $opts['dbport'], 'dbsocket' => $opts['dbsocket'], 'dbpass' => ''];
        if ($opts['dbpass-env'] !== '') {
            $pass = getenv($opts['dbpass-env']);
            if ($pass === false) {
                throw new \RuntimeException('The environment variable ' . $opts['dbpass-env'] . ' is not set.');
            }
            $override['dbpass'] = $pass;
        }
        $c = config::database($configpath, $override);
        $started = microtime(true);
        $db = new mysqli_database($c);
        echo 'Connected: database ' . $c['name'] . ', prefix ' . $c['prefix'] . ', server ' . $db->server()
            . ($db->readonly ? ', read-only session' : ', WARNING: could not set the session read-only')
            . ($db->snapshot ? ', consistent snapshot' : ', WARNING: no consistent snapshot') . "\n";

        $hasconfig = $db->table_exists('config');
        $release = $hasconfig ? (string) $db->scalar("SELECT value FROM {config} WHERE name = 'release'") : '';
        $version = $hasconfig ? (string) $db->scalar("SELECT value FROM {config} WHERE name = 'version'") : '';
        echo 'Moodle ' . $release . ' (' . $version . ')' . "\n";

        $progress = static function (string $phase, float $seconds): void {
            echo sprintf("  [%-12s %7.2fs]\n", $phase, $seconds);
        };
        // A comparison reads only the metrics here: the baseline names the legacy tables it wants fingerprinted.
        $parts = $opts['compare'] !== '' ? ['metrics'] : ['metrics', 'legacy', 'core'];
        $doc = baseline::build($db, ['wwwroot' => $c['wwwroot'], 'release' => $release, 'version' => $version,
            'tool' => 'source_baseline.php'], $progress, $parts);
        $doc['layout']['snapshot'] = $db->snapshot;
        $doc['layout']['readonly'] = $db->readonly;
        echo sprintf("Total %.2fs\n", microtime(true) - $started);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
        return 3;
    }

    $print = static function (string $line): void {
        echo $line . "\n";
    };

    if ($opts['baseline'] !== '') {
        $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($opts['baseline'], $json . "\n", LOCK_EX) === false) {
            fwrite(STDERR, 'FAILED: cannot write ' . $opts['baseline'] . "\n");
            return 3;
        }
        echo 'Baseline saved: ' . $opts['baseline'] . ' (' . strlen($json) . " bytes)\n";
        print_summary($doc, $print);
        return 0;
    }

    if ($opts['compare'] !== '') {
        $base = json_decode((string) @file_get_contents($opts['compare']), true);
        if (!is_array($base) || empty($base['counts'])) {
            fwrite(STDERR, 'FAILED: cannot read baseline file: ' . $opts['compare'] . "\n");
            return 3;
        }
        return compare_command($db, $base, $doc, $print);
    }

    print_summary($doc, $print);
    return 0;
}

/**
 * Print the counts, aggregates, checksums and legacy tables of a document.
 *
 * @param array $doc
 * @param callable $print
 * @return void
 */
function print_summary(array $doc, callable $print): void {
    foreach ($doc['counts'] as $key => $value) {
        $print(sprintf('  %-24s %d', $key, $value));
    }
    foreach ($doc['aggregates'] as $key => $value) {
        $print(sprintf('  %-24s %s', $key, $value));
    }
    $print('');
    $print('Value checksums:');
    foreach ($doc['checksums'] as $table => $cs) {
        $print(sprintf('  %-28s rows=%-9d crc=%s', $table, $cs['rows'], $cs['crc'] ?? '(unsupported on this engine)'));
    }
    $print('');
    $rows = 0;
    foreach ($doc['legacy'] as $fp) {
        $rows += $fp['count'];
    }
    $print(sprintf('BizLMS legacy tables: %d present, %d rows in total; %d other table(s) with a legacy prefix',
        count($doc['legacy']), $rows, count($doc['legacy_other'])));
    $print('SCORM layout read: ' . ($doc['layout']['scorm'] ?? '?'));
    foreach ($doc['core'] as $table => $entry) {
        $print(sprintf('Core %-16s %s rows=%d maxid=%d', $table, $entry['mode'], $entry['count'], $entry['maxid']));
    }
    foreach ($doc['notes'] as $note) {
        $print('NOTE: ' . $note);
    }
}

/**
 * --compare of the standalone tool.
 *
 * @param database $db
 * @param array $base
 * @param array $now
 * @param callable $print
 * @return int Exit code.
 */
function compare_command(database $db, array $base, array $now, callable $print): int {
    $print('Baseline: ' . ($base['wwwroot'] ?? '?') . ' @ ' . gmdate('Y-m-d H:i:s', (int) ($base['captured_at'] ?? 0))
        . ' UTC (' . ($base['release'] ?? '?') . ', format ' . ($base['format'] ?? 1) . ', metrics '
        . ($base['tool']['metrics'] ?? 'none') . ')');
    $print('Current:  ' . $now['wwwroot'] . ' (' . $now['release'] . ', metrics ' . metrics::VERSION . ')');
    $refused = metrics::baseline_problem($base);
    if ($refused !== null) {
        $print('REFUSED: ' . $refused);
        return 3;
    }
    $result = baseline::compare_metrics($base, $now, $print);
    $drift = $result['drift'];
    $skipped = $result['skipped'];
    $unproven = [];

    $foot = metrics::cross_foot($now['counts']);
    $print('');
    $print('Invariants:');
    if ($foot !== null) {
        $print('  FAIL    tenant_cross_foot          ' . $foot);
        $drift++;
    } else {
        $print('  OK      tenant_cross_foot');
    }

    if (isset($base['legacy'])) {
        $names = array_keys((array) $base['legacy']);
        $current = legacy::fingerprints($db, $names);
        foreach (array_diff(legacy::known_present($db), $names) as $table) {
            $current[$table] = ['count' => 0, 'maxid' => 0, 'crc' => null, 'columns' => []];
        }
        $comparison = legacy::compare((array) $base['legacy'], $current);
        $othernow = legacy::fingerprints($db, array_keys((array) ($base['legacy_other'] ?? [])));
        $otherunproven = legacy::other_unproven((array) ($base['legacy_other'] ?? []), $othernow);
        $legacy = baseline::print_legacy($comparison, $otherunproven, count($names), $print);
        $drift += count($legacy['hard']);
        $unproven = $legacy['unproven'];
    } else {
        $print('');
        $print('BizLMS legacy tables: SKIPPED - the baseline predates legacy-table fingerprints.');
        $skipped++;
    }

    $print('');
    if ($drift > 0) {
        $print("RESULT: {$drift} metric(s) DRIFTED - investigate before proceeding.");
        return 1;
    }
    if ($skipped > 0 || $unproven) {
        $print('RESULT: counts match, but ' . ($skipped + count($unproven)) . ' item(s) could not be checked. '
            . 'Data is NOT proven intact.');
        return 2;
    }
    $print('RESULT: 100% PARITY - counts, aggregates, value checksums and the BizLMS legacy tables match.');
    return 0;
}

if (!defined('SENTIENTIA_PARITY_LIBRARY_ONLY')) {
    exit(main(isset($GLOBALS['argv']) ? (array) $GLOBALS['argv'] : ['source_baseline.php']));
}
