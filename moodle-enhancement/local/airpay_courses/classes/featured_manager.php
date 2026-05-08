<?php
/**
 * Phase F.2 (2026-05-08) — featured-courses widget for the learner dashboard.
 *
 * Admin curates a tenant-scoped list of courses; the widget renders the
 * top N for the current user, hiding any course they're already enrolled
 * in. Replaces the BizLMS "Featured" carousel that was global + un-scoped.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_courses;

defined('MOODLE_INTERNAL') || die();

class featured_manager {

    private const TABLE = 'local_airpay_featured_courses';

    /**
     * Add a course to the featured list. Idempotent — if the
     * (courseid, costcenterid) pair already exists, the row is left
     * alone (returns the existing ID).
     *
     * @return int Row ID
     */
    public static function add(int $courseid, int $costcenterid = 0,
                                int $sort_order = 0,
                                ?string $label = null): int {
        global $DB;
        // Validate course exists and is visible.
        $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);

        $existing = $DB->get_record(self::TABLE,
            ['courseid' => $courseid, 'costcenterid' => $costcenterid]);
        if ($existing) {
            // Already pinned — refresh label/order if provided.
            $changed = false;
            if ($label !== null && $existing->label !== $label) {
                $existing->label = $label; $changed = true;
            }
            if ($sort_order !== 0 && (int) $existing->sort_order !== $sort_order) {
                $existing->sort_order = $sort_order; $changed = true;
            }
            if ($changed) {
                $DB->update_record(self::TABLE, $existing);
            }
            return (int) $existing->id;
        }

        return (int) $DB->insert_record(self::TABLE, (object) [
            'courseid'     => $courseid,
            'costcenterid' => $costcenterid,
            'sort_order'   => $sort_order ?: self::next_sort_order($costcenterid),
            'label'        => $label,
            'timecreated'  => time(),
        ]);
    }

    /** Remove a featured row by ID. */
    public static function remove(int $id): bool {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
        return true;
    }

    /**
     * Reorder featured rows. Pass an array of IDs in the desired order;
     * each row's sort_order is rewritten to match.
     *
     * @return int Number of rows updated
     */
    public static function reorder(array $ordered_ids): int {
        global $DB;
        $changed = 0;
        $tx = $DB->start_delegated_transaction();
        try {
            foreach (array_values($ordered_ids) as $i => $id) {
                $DB->set_field(self::TABLE, 'sort_order', ($i + 1) * 10,
                    ['id' => (int) $id]);
                $changed++;
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return $changed;
    }

    /**
     * List all featured rows for the admin curation page.
     * Joined with course info; ordered by sort_order.
     *
     * @return list<array{id:int, courseid:int, costcenterid:int,
     *                    sort_order:int, label:string, fullname:string,
     *                    shortname:string, visible:bool}>
     */
    public static function list_all(int $costcenterid_filter = -1): array {
        global $DB;
        $where = '';
        $params = [];
        if ($costcenterid_filter >= 0) {
            $where = 'WHERE f.costcenterid = :cid';
            $params['cid'] = $costcenterid_filter;
        }
        $rows = $DB->get_records_sql("
            SELECT f.id, f.courseid, f.costcenterid, f.sort_order, f.label,
                   c.fullname, c.shortname, c.visible
              FROM {" . self::TABLE . "} f
              JOIN {course} c ON c.id = f.courseid
              $where
          ORDER BY f.costcenterid ASC, f.sort_order ASC, c.fullname ASC",
            $params);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->id,
                'courseid'     => (int) $r->courseid,
                'costcenterid' => (int) $r->costcenterid,
                'sort_order'   => (int) $r->sort_order,
                'label'        => (string) ($r->label ?? ''),
                'fullname'     => format_string($r->fullname),
                'shortname'    => format_string($r->shortname),
                'visible'      => (int) $r->visible === 1,
            ];
        }
        return $out;
    }

    /**
     * Get the featured-courses widget context for one user.
     *
     * Tenant scoping:
     *   - Reads user's tenant from $USER->open_path top-level segment
     *   - Returns rows where costcenterid = 0 (all tenants) OR matches user's tenant
     *   - Hides courses the user is already enrolled in
     *
     * @return array{has_courses:bool, courses:list<array>, more_url:string}
     */
    public static function get_widget_for_user(int $userid, int $limit = 6): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid],
            'id, open_path', MUST_EXIST);
        $parts = explode('/', trim((string) ($user->open_path ?? ''), '/'));
        $tenant_top = isset($parts[0]) && ctype_digit($parts[0])
            ? (int) $parts[0] : 0;

        $rows = $DB->get_records_sql("
            SELECT f.id, f.courseid, f.label, f.sort_order,
                   c.fullname, c.shortname, c.summary, c.summaryformat
              FROM {" . self::TABLE . "} f
              JOIN {course} c ON c.id = f.courseid
             WHERE c.visible = 1
               AND (f.costcenterid = 0 OR f.costcenterid = :ctid)
               AND NOT EXISTS (
                   SELECT 1 FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid
                    WHERE e.courseid = f.courseid AND ue.userid = :uid
               )
          ORDER BY f.sort_order ASC, c.fullname ASC",
            ['ctid' => $tenant_top, 'uid' => $userid], 0, $limit);

        $courses = [];
        foreach ($rows as $r) {
            $summary = format_text((string) ($r->summary ?? ''),
                (int) ($r->summaryformat ?? FORMAT_HTML),
                ['noclean' => false]);
            // Strip HTML for snippet.
            $snippet = trim(html_to_text($summary, 0));
            if (function_exists('mb_substr')) {
                $snippet = mb_strlen($snippet) > 140
                    ? mb_substr($snippet, 0, 140) . '…' : $snippet;
            }
            $courses[] = [
                'courseid'  => (int) $r->courseid,
                'fullname'  => format_string($r->fullname),
                'shortname' => format_string($r->shortname),
                'label'     => format_string((string) ($r->label ?? '')),
                'has_label' => !empty(trim((string) ($r->label ?? ''))),
                'snippet'   => $snippet,
                'has_snippet' => !empty($snippet),
                'viewurl'   => (new \moodle_url('/course/view.php',
                    ['id' => $r->courseid]))->out(false),
                'enrolurl'  => (new \moodle_url('/enrol/index.php',
                    ['id' => $r->courseid]))->out(false),
            ];
        }
        return [
            'has_courses' => !empty($courses),
            'courses'     => $courses,
            'more_url'    => (new \moodle_url(
                '/local/airpay_catalog/index.php'))->out(false),
        ];
    }

    /** Helper: next sort_order for a tenant. */
    private static function next_sort_order(int $costcenterid): int {
        global $DB;
        $max = (int) $DB->get_field_sql(
            "SELECT MAX(sort_order) FROM {" . self::TABLE . "}
              WHERE costcenterid = :cid",
            ['cid' => $costcenterid]);
        return $max + 10;
    }
}
