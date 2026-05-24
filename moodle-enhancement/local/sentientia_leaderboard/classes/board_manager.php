<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD + lookup for board definitions.
 *
 * Mirrors the shape of local_sentientia_live\session_manager: derive
 * customer/tenant from the creator's open_path at creation time, pin them
 * to the row so a later open_path change doesn't move the resource across
 * tenants mid-life.
 *
 * @package local_sentientia_leaderboard
 */
class board_manager {

    public const TYPE_QUIZ       = 'quiz';
    public const TYPE_COMPLETION = 'completion';
    public const TYPE_SKILL      = 'skill';

    public const VALID_TYPES = [
        self::TYPE_QUIZ,
        self::TYPE_COMPLETION,
        self::TYPE_SKILL,
    ];

    public const SCOPE_COURSE   = 'course';
    public const SCOPE_TENANT   = 'tenant';
    public const SCOPE_CUSTOMER = 'customer';

    public const VALID_SCOPES = [
        self::SCOPE_COURSE,
        self::SCOPE_TENANT,
        self::SCOPE_CUSTOMER,
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_ARCHIVED = 'archived';

    /** Minimum allowed recompute interval (seconds). Prevents an admin
     *  hammering the DB with `recompute_seconds = 1`. */
    public const MIN_RECOMPUTE_SECONDS = 30;

    /**
     * Create a new board.
     *
     * @param array{
     *   name: string,
     *   type: string,
     *   scope?: string,
     *   courseid?: int,
     *   quizid?: int,
     *   skill_ids?: int[],
     *   window_start?: ?int,
     *   window_end?: ?int,
     *   recompute_seconds?: int,
     *   settings?: array,
     *   ownerid?: int,
     *   tenantid?: int,
     * } $data
     * @return int New board id.
     * @throws \moodle_exception on invalid input.
     */
    public static function create(array $data): int {
        global $DB, $USER;

        $now = time();
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 200) {
            throw new \moodle_exception('invalidname');
        }

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \moodle_exception('error_invalidtype',
                'local_sentientia_leaderboard');
        }

        $scope = (string) ($data['scope'] ?? self::SCOPE_TENANT);
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            throw new \moodle_exception('error_invalidscope',
                'local_sentientia_leaderboard');
        }

        // Type-specific required-field gates.
        $courseid = (int) ($data['courseid'] ?? 0);
        $quizid   = (int) ($data['quizid']   ?? 0);
        if ($type === self::TYPE_QUIZ && $quizid <= 0) {
            throw new \moodle_exception('error_quiznotscoped',
                'local_sentientia_leaderboard');
        }
        if ($type === self::TYPE_COMPLETION && $courseid <= 0) {
            throw new \moodle_exception('error_completionnotscoped',
                'local_sentientia_leaderboard');
        }

        $window_start = isset($data['window_start']) ? (int) $data['window_start'] : null;
        $window_end   = isset($data['window_end'])   ? (int) $data['window_end']   : null;
        if ($window_start !== null && $window_end !== null
                && $window_end <= $window_start) {
            throw new \moodle_exception('error_invalidwindow',
                'local_sentientia_leaderboard');
        }

        $recompute_seconds = (int) ($data['recompute_seconds'] ?? 120);
        if ($recompute_seconds < self::MIN_RECOMPUTE_SECONDS) {
            throw new \moodle_exception('error_invalidrecompute',
                'local_sentientia_leaderboard');
        }

        // Resolve owner + tenant. If $data carries an explicit ownerid /
        // tenantid (admin script use), honour it; otherwise derive from $USER.
        $ownerid = (int) ($data['ownerid'] ?? ($USER->id ?? 0));
        if ($ownerid <= 0) {
            throw new \moodle_exception('invaliduser');
        }

        // tenantid derivation: explicit > resolve from owner. Validate
        // siteadmin can pass 0 (customer-wide) but a tenant-bound caller
        // cannot. Promote check is the caller's responsibility (the
        // index.php form runs require_capability(:promoteboard) when the
        // submitted tenantid is 0).
        if (array_key_exists('tenantid', $data)) {
            $tenantid = (int) $data['tenantid'];
        } else {
            $owner = $DB->get_record('user', ['id' => $ownerid],
                'id, open_path', MUST_EXIST);
            $tenantid = self::resolve_tenant_from_open_path((string) ($owner->open_path ?? ''));
        }

        $skill_ids = $data['skill_ids'] ?? null;
        $skill_ids_json = ($skill_ids === null || $skill_ids === [])
            ? null
            : json_encode(array_map('intval', (array) $skill_ids));

        $settings = $data['settings'] ?? [];
        $settings_json = empty($settings) ? null : json_encode($settings);

        $row = new \stdClass();
        $row->name             = $name;
        $row->type             = $type;
        $row->scope            = $scope;
        $row->courseid         = $courseid;
        $row->quizid           = $quizid;
        $row->skill_ids_json   = $skill_ids_json;
        $row->window_start     = $window_start;
        $row->window_end       = $window_end;
        $row->recompute_seconds = $recompute_seconds;
        $row->ownerid          = $ownerid;
        $row->customerid       = 1;  // Phase 1: hardcoded Airpay
        $row->tenantid         = $tenantid;
        $row->status           = self::STATUS_ACTIVE;
        $row->settings_json    = $settings_json;
        $row->last_recomputed  = 0;
        $row->timecreated      = $now;
        $row->timemodified     = $now;

        return (int) $DB->insert_record('local_sentientia_lb_boards', $row);
    }

    /**
     * Load a board by id, or null if not found.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;
        $row = $DB->get_record('local_sentientia_lb_boards', ['id' => $id]);
        return $row ?: null;
    }

    /**
     * List boards visible to the caller. Tenant-scoped unless the caller
     * has :viewall.
     *
     * @param int $viewer_tenant The viewer's tenant root (0 = global view).
     * @param bool $can_view_all Whether the viewer has :viewall (skips tenant filter).
     * @param array $filters Optional: ['type' => 'quiz', 'status' => 'active', ...]
     * @return \stdClass[]
     */
    public static function list_visible(int $viewer_tenant, bool $can_view_all,
                                          array $filters = []): array {
        global $DB;
        $where = [];
        $params = [];
        if (!$can_view_all) {
            // Tenant rows OR customer-wide (tenantid=0) rows are both visible.
            $where[] = '(b.tenantid = :tn OR b.tenantid = 0)';
            $params['tn'] = $viewer_tenant;
        }
        if (!empty($filters['type'])) {
            $where[] = 'b.type = :tp';
            $params['tp'] = (string) $filters['type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'b.status = :st';
            $params['st'] = (string) $filters['status'];
        } else {
            $where[] = "b.status = :defst";
            $params['defst'] = self::STATUS_ACTIVE;
        }
        $sql = "SELECT b.* FROM {local_sentientia_lb_boards} b";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY b.timemodified DESC';
        $rows = $DB->get_records_sql($sql, $params, 0, 500);
        return array_values($rows);
    }

    /**
     * Mark the board's last_recomputed and bump timemodified. Used by the
     * ranking engine after a successful recompute.
     */
    public static function mark_recomputed(int $boardid): void {
        global $DB;
        $now = time();
        $DB->set_field('local_sentientia_lb_boards', 'last_recomputed',
            $now, ['id' => $boardid]);
        $DB->set_field('local_sentientia_lb_boards', 'timemodified',
            $now, ['id' => $boardid]);
    }

    /**
     * Delete a board and cascade — entries + events.
     */
    public static function delete(int $boardid): void {
        global $DB;
        $DB->delete_records('local_sentientia_lb_entries', ['boardid' => $boardid]);
        $DB->delete_records('local_sentientia_lb_events',  ['boardid' => $boardid]);
        $DB->delete_records('local_sentientia_lb_boards',  ['id'      => $boardid]);
    }

    /**
     * Boards whose last_recomputed timestamp is older than their
     * recompute_seconds threshold. Used by the scheduled task.
     *
     * @return \stdClass[]
     */
    public static function boards_due_for_recompute(): array {
        global $DB;
        $now = time();
        // We can't use a column-vs-column compare in $DB->get_records, so
        // raw SQL with the columns referenced on both sides of the comparison.
        $sql = "SELECT b.*
                  FROM {local_sentientia_lb_boards} b
                 WHERE b.status = :active
                   AND (:now - b.last_recomputed) >= b.recompute_seconds";
        return array_values($DB->get_records_sql($sql, [
            'active' => self::STATUS_ACTIVE,
            'now'    => $now,
        ]));
    }

    /**
     * Derive the tenant root from an open_path string. Single source of
     * truth for the open_path → costcenterid parsing. Mirrors
     * local_airpay_core\tenant::root_for_user but takes a string so we
     * can use it on freshly-loaded user records.
     *
     * "/1/2/3" → 1
     * "/77"     → 77
     * empty     → 0
     */
    public static function resolve_tenant_from_open_path(string $path): int {
        $parts = explode('/', trim($path, '/'));
        $first = $parts[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }
}
