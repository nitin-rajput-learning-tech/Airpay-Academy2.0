<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

/**
 * Platform-wide guard: a plugin that stores user data may not claim otherwise.
 *
 * @covers \local_sentientia_platform\tenant
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * On 2026-09-22 an audit found four plugins declaring
 * `\core_privacy\local\metadata\null_provider` - a positive assertion to
 * Moodle's privacy registry that they store no personal data - while between
 * them owning nine tables keyed on a user id, one of which also stored the
 * employee email address a reminder was sent to. A fifth had no provider file
 * at all. Under DPDP, a subject-access request returned nothing from any of
 * them and an erasure request deleted nothing, both reporting success.
 *
 * Every one of those was a single wrong line in a file nobody reads, so
 * reviewing the five is not a fix. This test walks the install.xml of every
 * Sentientia plugin on disk and fails the build if a plugin that declares a
 * user-identifying column also declares null_provider or declares no provider.
 * Since 2026-09-24 it also reads tables a plugin creates at runtime and lists
 * in a classes/schema/*::TABLES constant (see runtime_user_tables()).
 *
 * It is intentionally structural rather than a fixed allowlist: a NEW plugin
 * that ships a userid table and a copy-pasted null_provider fails here on its
 * first CI run.
 *
 * @package    local_sentientia_platform
 * @category   test
 *
 * @group tenant_isolation
 */
final class privacy_coverage_test extends \advanced_testcase {

    /**
     * Column names that identify a natural person.
     *
     * `costcenterid`, `courseid` and friends are deliberately absent: they
     * identify an org or a course, not a person.
     *
     * @var string[]
     */
    private const USER_COLUMNS = [
        'userid', 'user_id', 'requester_userid', 'useridfrom', 'useridto',
        'createdby', 'modifiedby', 'approved_by', 'decided_by', 'proposed_by',
        'assignedby', 'grantedby', 'revokedby', 'raisedby', 'ownerid',
        // Found during the 2026-09-22 sweep by reading the schemas rather
        // than guessing the names. Every one of these turned out to be a real
        // actor reference on a live table.
        'changed_by', 'assigned_by_userid', 'reviewed_by',
        // 2026-09-24: the ADR-017 employee / partner-employee profiles name
        // the person's manager. Only local_sentientia_platform uses them.
        'manager_userid', 'partner_manager_userid',
    ];

    /**
     * Plugins whose user-identifying columns are genuinely not personal data,
     * with the reason. Keep this SHORT and justified - it is the escape hatch
     * that lets the defect back in.
     *
     * @var array<string,string>
     */
    private const EXEMPT = [
        // local_sentientia_privacy IS the data-request machinery. Its own
        // queue rows are exported and erased by the request workflow itself;
        // a provider here would recurse.
        'local_sentientia_privacy' => 'implements the data-request workflow itself',
    ];

    /**
     * Locate the local/ plugin directories to scan.
     *
     * @return string Absolute path to the local plugin root.
     */
    private function local_root(): string {
        global $CFG;
        return $CFG->dirroot . '/local';
    }

    /**
     * Tables declared by a plugin's install.xml, with their user columns.
     *
     * @param string $plugindir
     * @return array<string,string[]> table => user columns
     */
    private function user_tables(string $plugindir): array {
        $xml = $plugindir . '/db/install.xml';
        if (!is_readable($xml)) {
            return [];
        }

        $raw = file_get_contents($xml);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($raw);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            // A malformed install.xml is its own bug, reported elsewhere
            // (a raw '<' in a COMMENT attribute has bitten us before).
            return [];
        }

        $found = [];
        foreach ($doc->xpath('//TABLE') ?: [] as $table) {
            $tname = (string) $table['NAME'];
            $cols = [];
            foreach ($table->xpath('.//FIELD') ?: [] as $field) {
                $fname = (string) $field['NAME'];
                if (in_array($fname, self::USER_COLUMNS, true)) {
                    $cols[] = $fname;
                }
            }
            if (!empty($cols)) {
                $found[$tname] = $cols;
            }
        }

        return $found;
    }

    /**
     * Tables a plugin creates from PHP rather than install.xml, with their
     * user columns.
     *
     * Added 2026-09-24. local_sentientia_platform's five ADR-017 user-type
     * tables are created by classes/schema/user_type_tables.php (called from
     * db/install.php and an upgrade step). The moodle-enhancement tree's
     * install.xml does not list them, so the install.xml scan could not see
     * them, and a public-signup learner's consumer profile survived a DPDP
     * erasure reported as 'completed'.
     *
     * Convention: a plugin that creates tables at runtime lists them in a
     * public TABLES constant on a class under classes/schema/. Only tables
     * listed that way are considered - nothing is guessed from PHP source,
     * which is what keeps this free of false positives - and their columns
     * are read from the live schema, so a listed table with no user column
     * requires nothing. A listed table absent from this database is skipped
     * because its columns are unknown; the owning plugin's own test covers it
     * (local_sentientia_platform\privacy_provider_test creates the user-type
     * tables through the same ensure() the installer uses).
     *
     * @param string $plugindir
     * @param string $component
     * @return array<string,string[]> table => user columns
     */
    private function runtime_user_tables(string $plugindir, string $component): array {
        global $DB;
        $dbman = $DB->get_manager();
        $found = [];

        foreach (glob($plugindir . '/classes/schema/*.php') ?: [] as $file) {
            $class = '\\' . $component . '\\schema\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if (!$reflection->hasConstant('TABLES')) {
                continue;
            }
            foreach ((array) $reflection->getConstant('TABLES') as $table) {
                if (!is_string($table) || !$dbman->table_exists($table)) {
                    continue;
                }
                $cols = array_values(array_intersect(
                    array_keys($DB->get_columns($table)), self::USER_COLUMNS));
                if (!empty($cols)) {
                    $found[$table] = $cols;
                }
            }
        }

        return $found;
    }

    /**
     * Every table with a user column that a plugin owns: its install.xml
     * tables plus its runtime-created ones. Where both list a table (the
     * top-level local/ tree's platform install.xml also carries the user-type
     * tables) the install.xml entry is kept; it names the same columns.
     *
     * @param string $plugindir
     * @param string $component
     * @return array<string,string[]> table => user columns
     */
    private function all_user_tables(string $plugindir, string $component): array {
        return $this->user_tables($plugindir)
            + $this->runtime_user_tables($plugindir, $component);
    }

    public function test_no_plugin_holding_user_data_declares_null_provider(): void {
        $root = $this->local_root();
        $this->assertDirectoryExists($root);

        $offenders = [];
        $checked = 0;

        foreach (glob($root . '/sentientia_*', GLOB_ONLYDIR) ?: [] as $plugindir) {
            $plugin = basename($plugindir);
            $component = 'local_' . $plugin;

            if (isset(self::EXEMPT[$component])) {
                continue;
            }

            $usertables = $this->all_user_tables($plugindir, $component);
            if (empty($usertables)) {
                continue;
            }
            $checked++;

            $providerfile = $plugindir . '/classes/privacy/provider.php';
            if (!is_readable($providerfile)) {
                $offenders[] = sprintf(
                    '%s owns %s but has NO classes/privacy/provider.php',
                    $component, implode(', ', array_keys($usertables)));
                continue;
            }

            // Reflection, not a string search on the source. The first draft
            // of this test matched the literal 'null_provider' anywhere in the
            // file, and then flagged all five providers it had just fixed --
            // because their docblocks explain the null_provider they replaced.
            // A guard that fires on its own documentation gets switched off.
            $class = "\\{$component}\\privacy\\provider";
            if (!class_exists($class)) {
                $offenders[] = sprintf(
                    '%s owns %s but %s does not load',
                    $component, implode(', ', array_keys($usertables)), $class);
                continue;
            }

            $nullprovider = 'core_privacy\local\metadata\null_provider';
            $implements = class_implements($class) ?: [];
            if (isset($implements[$nullprovider])) {
                $offenders[] = sprintf(
                    '%s declares null_provider but owns %s',
                    $component, implode(', ', array_keys($usertables)));
            }
        }

        $this->assertGreaterThan(10, $checked,
            'the scan found suspiciously few plugins with user tables; an '
            . 'empty scan would pass this test while proving nothing');

        $this->assertSame([], $offenders,
            "a plugin that stores personal data must not tell Moodle's privacy "
            . "registry that it stores none:\n  - "
            . implode("\n  - ", $offenders));
    }

    public function test_declared_providers_cover_the_tables_they_own(): void {
        $root = $this->local_root();
        $gaps = [];

        foreach (glob($root . '/sentientia_*', GLOB_ONLYDIR) ?: [] as $plugindir) {
            $plugin = basename($plugindir);
            $component = 'local_' . $plugin;

            if (isset(self::EXEMPT[$component])) {
                continue;
            }

            $usertables = $this->all_user_tables($plugindir, $component);
            if (empty($usertables)) {
                continue;
            }

            $class = "\\{$component}\\privacy\\provider";
            if (!class_exists($class)) {
                continue;
            }
            if (!is_subclass_of($class, \core_privacy\local\metadata\provider::class)
                && !in_array(\core_privacy\local\metadata\provider::class,
                    class_implements($class) ?: [], true)) {
                continue;
            }

            $declared = [];
            try {
                $collection = $class::get_metadata(new collection($component));
                foreach ($collection->get_collection() as $item) {
                    $declared[] = $item->get_name();
                }
            } catch (\Throwable $e) {
                $gaps[] = "{$component}: get_metadata() threw " . $e->getMessage();
                continue;
            }

            foreach (array_keys($usertables) as $table) {
                if (!in_array($table, $declared, true)) {
                    $gaps[] = sprintf('%s owns %s (%s) but does not declare it',
                        $component, $table, implode(', ', $usertables[$table]));
                }
            }
        }

        $this->assertSame([], $gaps,
            "a provider that declares some of its tables but not others is the "
            . "harder version of the same bug - the registry looks populated:\n  - "
            . implode("\n  - ", $gaps));
    }
}
