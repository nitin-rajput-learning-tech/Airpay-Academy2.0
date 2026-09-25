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

        // tenantid derivation: explicit > resolve from owner.
        //
        // ADR-031 (2026-09-25). This used to say "the index.php form runs
        // require_capability(:promoteboard)" - there is no such form, and
        // nothing checked it. Now create() enforces it itself:
        //   - an owner whose open_path does not resolve no longer yields a
        //     silent customer-wide (tenantid 0) board: pass tenantid 0
        //     explicitly to make one;
        //   - a tenantid 0 board ranks users from EVERY tenant and is shown
        //     to every tenant, so only a cross-tenant actor may create one;
        //   - anyone else creates boards in their own tenant only.
        // CLI callers (seed_demo_boards.php) run as the site admin.
        if (array_key_exists('tenantid', $data)) {
            $tenantid = (int) $data['tenantid'];
        } else {
            $owner = $DB->get_record('user', ['id' => $ownerid],
                'id, open_path', MUST_EXIST);
            $tenantid = self::resolve_tenant_from_open_path((string) ($owner->open_path ?? ''));
            if ($tenantid <= 0) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
        }
        if (!\local_sentientia_platform\tenant::is_cross_tenant()) {
            if ($tenantid <= 0) {
                throw new \moodle_exception('error_cantpromote', 'local_sentientia_leaderboard');
            }
            $own = \local_sentientia_platform\tenant::root_for_current_user();
            if ($own <= 0 || $tenantid !== $own) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
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
     * List boards for a given tenant scope. Web surfaces call
     * {@see list_for_viewer()}, which decides the scope (ADR-031); this is
     * the query underneath it.
     *
     * @param int $viewer_tenant The viewer's tenant root (0 = customer-wide boards only).
     * @param bool $can_view_all True only for a cross-tenant viewer (skips the tenant filter).
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
     * ADR-031: the boards the CURRENT user may see.
     *
     * The single entry point for every listing surface (index.php, the
     * list_boards web service, the block and its board picker). A
     * cross-tenant user (site admin or local/sentientia_platform:crosstenant
     * holder) sees every tenant's boards. Anyone else sees their own tenant's
     * boards plus customer-wide (tenantid 0) ones; a user whose tenant does
     * not resolve sees none. Until 2026-09-25 the callers passed
     * has_capability(:viewall) as list_visible()'s $can_view_all, and
     * :viewall defaulted to the manager archetype that every tenant admin
     * holds.
     *
     * @param array $filters as list_visible()
     * @return \stdClass[]
     */
    public static function list_for_viewer(array $filters = []): array {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return self::list_visible(0, true, $filters);
        }
        $root = \local_sentientia_platform\tenant::root_for_current_user();
        if ($root <= 0) {
            return [];
        }
        return self::list_visible($root, false, $filters);
    }

    /**
     * ADR-031: may the CURRENT user see this board?
     *
     * The single board gate for view.php, get_board, stream.php and the
     * block. Cross-tenant users see any board; anyone else a board of their
     * own tenant or a customer-wide (tenantid 0) one, and nothing when their
     * own tenant does not resolve.
     */
    public static function viewer_can_see(\stdClass $board): bool {
        if (\local_sentientia_platform\tenant::is_cross_tenant()) {
            return true;
        }
        $root = \local_sentientia_platform\tenant::root_for_current_user();
        if ($root <= 0) {
            return false;
        }
        $boardtenant = (int) $board->tenantid;
        return $boardtenant === 0 || $boardtenant === $root;
    }

    /**
     * ADR-031: does the CURRENT user see learners who opted out of leaderboards?
     *
     * Site admins only - the behaviour they had before. This used to ride on
     * :viewall, so every tenant admin also saw opted-out learners on every
     * rendered board, their own tenant's included. Cross-tenant reach and the
     * opt-out bypass are different permissions; this is not the former.
     */
    public static function viewer_bypasses_optout(): bool {
        return is_siteadmin();
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
     * local_sentientia_platform\tenant::root_for_user but takes a string so we
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
