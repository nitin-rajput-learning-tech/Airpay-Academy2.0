<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * The imported BizLMS admin log (ADR-032, mapping doc section 8): reading, tenant scoping, export and erasure.
 *
 * The rows are written only by the legacy_logs importer (classes/bizlms/). This class never writes a row
 * except to anonymise one on an erasure request.
 *
 * Tenant scope follows ADR-031: a caller that is cross-tenant sees every row, a caller with a resolvable
 * tenant sees the rows whose actor_path sits inside their own tenant (whole-path match through
 * tenant::path_descendant_filter(), never a bare prefix), a caller with no resolvable tenant sees nothing,
 * and a row with no actor_path is cross-tenant only.
 *
 * Erasure (signed decision legacy_logs.description_erasure = keep_row_scrub_name): the row stays, the actor
 * columns are cleared, and the first name in the description is replaced. A description is kept only when it
 * has the exact shape BizLMS wrote; anything else is replaced whole, because the name cannot be found in it
 * with certainty.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_log {

    /** Table written by the importer. */
    public const TABLE = 'local_sentientia_admin_log';

    /** Feature flag of the report page (default OFF). */
    public const FLAG = 'sentientia.legacy_logs.report.enabled';

    /** Source value of a row that came from local_logs. */
    public const SOURCE_LOGS = 'local_logs';

    /** Source value of a row that came from local_courseerrors. */
    public const SOURCE_ERRORS = 'local_courseerrors';

    /** What replaces the first name in a description that has the BizLMS shape. */
    public const NAME_PLACEHOLDER = '[erased]';

    /** What replaces a description whose shape is not the BizLMS one, so the name cannot be located in it. */
    public const DESCRIPTION_PLACEHOLDER = '[description removed on an erasure request]';

    /**
     * The shape of every description BizLMS wrote through local_custom_logs(): course create, update and
     * delete (local_courses), forum and online exam delete (local_forum, local_onlineexams), in English.
     * Group 2 is the first name. The strings are 'User with Username "NAME"  created the course  "TITLE"'
     * (two spaces after the closing quote on a create, as BizLMS wrote it), 'User with Username "NAME" has
     * updated the course  "TITLE"' and 'User with Username "NAME" has deleted the course with courseid  "ID"',
     * so the gaps are matched as runs of white space, not as one space.
     */
    private const NAME_SHAPE = '/^(User with Username ")(.*?)("\s+(?:created|has updated|has deleted)\s+the\s+\S+)/su';

    /** Rows per page when anonymising. */
    private const CHUNK = 500;

    /**
     * Is the report switched on for the current user?
     *
     * @return bool
     */
    public static function report_enabled(): bool {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
        } catch (\Throwable $e) {
            // The flag tables or the registry cache are not there yet (an install or upgrade in progress):
            // a flag that cannot be read is OFF.
            return false;
        }
    }

    /**
     * The tenant restriction for a viewer, as a WHERE fragment on the alias l.
     *
     * @param \stdClass|null $user Defaults to the current user.
     * @return array{0: string, 1: array}|null [sql, params]; null when the viewer may see nothing.
     */
    public static function scope(?\stdClass $user = null): ?array {
        if (!class_exists('\local_sentientia_platform\tenant')) {
            return null;
        }
        $scope = \local_sentientia_platform\tenant::scope_path($user);
        if ($scope === null) {
            return null;
        }
        if ($scope === '') {
            return ['1 = 1', []];
        }
        return \local_sentientia_platform\tenant::path_descendant_filter($scope, 'l', 'actor_path', 'alscope');
    }

    /**
     * Build the WHERE of a listing.
     *
     * @param array $filters source, event, module (each optional, exact match).
     * @param \stdClass|null $user
     * @return array{0: string, 1: array}|null Null when the viewer may see nothing.
     */
    private static function where(array $filters, ?\stdClass $user = null): ?array {
        $scope = self::scope($user);
        if ($scope === null) {
            return null;
        }
        $where = [$scope[0]];
        $params = $scope[1];
        foreach (['source', 'event', 'module'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where[] = "l.{$field} = :alf{$field}";
                $params["alf{$field}"] = $value;
            }
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * Number of rows the viewer may see with these filters.
     *
     * @param array $filters
     * @param \stdClass|null $user
     * @return int
     */
    public static function count(array $filters = [], ?\stdClass $user = null): int {
        global $DB;
        $where = self::where($filters, $user);
        if ($where === null) {
            return 0;
        }
        return (int) $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::TABLE . '} l WHERE ' . $where[0], $where[1]);
    }

    /**
     * One page of rows the viewer may see, newest first.
     *
     * @param array $filters
     * @param int $page 0-based.
     * @param int $perpage At most 100.
     * @param \stdClass|null $user
     * @return \stdClass[] Rows with the actor's name fields (actor_deleted is 1 for a deleted user, null when
     *         there is no user row).
     */
    public static function page(array $filters = [], int $page = 0, int $perpage = 50, ?\stdClass $user = null): array {
        global $DB;
        $where = self::where($filters, $user);
        if ($where === null) {
            return [];
        }
        $perpage = max(10, min(100, $perpage));
        $page = max(0, $page);
        return array_values($DB->get_records_sql(
            'SELECT l.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename,
                    u.alternatename, u.deleted AS actor_deleted
               FROM {' . self::TABLE . '} l
          LEFT JOIN {user} u ON u.id = l.userid
              WHERE ' . $where[0] . '
           ORDER BY l.timecreated DESC, l.id DESC',
            $where[1], $page * $perpage, $perpage));
    }

    /**
     * The values a filter select offers: every source, event and module in the table, each with its row count.
     * Names of event types, not of people, so they are not tenant-scoped.
     *
     * @return array{source: array<string, int>, event: array<string, int>, module: array<string, int>}
     */
    public static function filter_options(): array {
        global $DB;
        $out = [];
        foreach (['source', 'event', 'module'] as $field) {
            $out[$field] = [];
            $rows = $DB->get_records_sql(
                "SELECT l.{$field} AS fv, COUNT(1) AS n FROM {" . self::TABLE . "} l GROUP BY l.{$field} ORDER BY l.{$field}",
                [], 0, 100);
            foreach ($rows as $row) {
                $out[$field][(string) $row->fv] = (int) $row->n;
            }
        }
        return $out;
    }

    /**
     * Replace the first name in a description.
     *
     * Idempotent. A bulk upload error carries no first name and is returned unchanged.
     *
     * @param string $source The row's source value.
     * @param string $description
     * @return string
     */
    public static function scrub_description(string $source, string $description): string {
        if ($source !== self::SOURCE_LOGS) {
            return $description;
        }
        if (preg_match(self::NAME_SHAPE, $description, $m, PREG_OFFSET_CAPTURE)) {
            [$name, $offset] = $m[2];
            return substr($description, 0, $offset) . self::NAME_PLACEHOLDER . substr($description, $offset + strlen($name));
        }
        return self::DESCRIPTION_PLACEHOLDER;
    }

    /**
     * The rows that name a user, for a data export.
     *
     * @param int $userid
     * @return \stdClass[] Rows where the user is the actor or the modifier, oldest first.
     */
    public static function rows_for_user(int $userid): array {
        global $DB;
        if ($userid <= 0) {
            return [];
        }
        return array_values($DB->get_records_select(self::TABLE, 'userid = :alu1 OR usermodified = :alu2',
            ['alu1' => $userid, 'alu2' => $userid], 'timecreated ASC, id ASC'));
    }

    /**
     * Erase the people in some rows, keeping the rows: the actor and modifier columns are cleared and the
     * description of a row the user acted in is scrubbed.
     *
     * @param int[] $userids
     * @return int Rows changed.
     */
    public static function anonymise_users(array $userids): int {
        global $DB;
        $userids = array_values(array_unique(array_filter(array_map('intval', $userids), static fn(int $id): bool => $id > 0)));
        $changed = 0;
        foreach (array_chunk($userids, self::CHUNK) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'alau');
            // Rows the user acted in: the description names them. Cleared rows drop out of the condition,
            // so the loop ends when none is left.
            do {
                $rows = $DB->get_records_select(self::TABLE, "userid $insql", $params, 'id ASC',
                    'id, source, description', 0, self::CHUNK);
                foreach ($rows as $row) {
                    $DB->update_record(self::TABLE, (object) [
                        'id' => (int) $row->id,
                        'description' => self::scrub_description((string) $row->source, (string) $row->description),
                        'userid' => 0,
                    ]);
                    $changed++;
                }
            } while ($rows);
            // Rows the user only modified: the description belongs to the actor, so it stays.
            $modified = $DB->count_records_select(self::TABLE, "usermodified $insql", $params);
            if ($modified > 0) {
                $DB->set_field_select(self::TABLE, 'usermodified', 0, "usermodified $insql", $params);
                $changed += $modified;
            }
        }
        return $changed;
    }

    /**
     * Erase the people in every row, keeping the rows (the whole system context is being erased).
     *
     * @return int Rows changed.
     */
    public static function anonymise_all(): int {
        global $DB;
        $changed = 0;
        $after = 0;
        do {
            $rows = $DB->get_records_select(self::TABLE, 'id > :alafter', ['alafter' => $after], 'id ASC',
                'id, source, description, userid, usermodified', 0, self::CHUNK);
            foreach ($rows as $row) {
                $after = (int) $row->id;
                $description = (string) $row->description;
                // A row that never had an actor and whose text is not in the BizLMS shape (a cron-style entry)
                // names nobody, so its text is history and stays. A row with an actor, or with a name in the
                // BizLMS shape, is scrubbed as anonymise_users() scrubs it.
                $named = (int) $row->userid > 0 || preg_match(self::NAME_SHAPE, $description) === 1;
                $scrubbed = $named ? self::scrub_description((string) $row->source, $description) : $description;
                if ($scrubbed === $description && (int) $row->userid === 0 && (int) $row->usermodified === 0) {
                    continue;
                }
                $DB->update_record(self::TABLE, (object) [
                    'id' => (int) $row->id, 'description' => $scrubbed, 'userid' => 0, 'usermodified' => 0,
                ]);
                $changed++;
            }
        } while ($rows);
        return $changed;
    }
}
