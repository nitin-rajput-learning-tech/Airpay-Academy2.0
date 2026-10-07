<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * The learner-facing "My learning paths" page data (ADR-032, mapping doc section 17, code fix 7).
 *
 * Until now a learner had no page of their own for a learning path: view.php is the admin surface
 * (rosters with names and e-mail addresses, CSV export) and ADR-031 took its :view away from learners. This
 * is the page BizLMS had and the import needs, so a learner sees the paths they were enrolled in, their
 * progress, and - for the plans the BizLMS import brought over - their enrolment and completion dates.
 *
 * Rules, each one deliberate:
 *  - Their own rows only (lpu.userid = the learner). Nothing here lists another person.
 *  - Inside their own tenant, through tenant::path_filter() (ADR-031). A path with no tenant path is
 *    cross-tenant-only, so a learner never sees it, imported or not.
 *  - Active paths only (status 1, visible 1). BizLMS showed a learner only plans with visible = 1, so the
 *    completed history on an archived path stays admin-only (decision learningplan.history_on_archived_paths
 *    = hidden_from_learners).
 *  - Read only, and behind the default-OFF flag sentientia.learningpath.learner_paths.enabled.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class learner_paths {

    /** The flag that turns the page on. Default OFF; the import never flips it. */
    public const FLAG = 'sentientia.learningpath.learner_paths.enabled';

    /** Characters of description shown on a card. */
    private const SUMMARY_LENGTH = 180;

    /**
     * Is the page switched on for the current user?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return class_exists('\local_sentientia_platform\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled(self::FLAG);
    }

    /**
     * The template context of the page for the current user.
     *
     * @return array
     */
    public static function page_data(): array {
        global $USER;
        $cards = self::cards((int) $USER->id);

        $inprogress = 0;
        $completed = 0;
        foreach ($cards as $card) {
            if ($card['is_completed']) {
                $completed++;
            } else if ($card['is_inprogress']) {
                $inprogress++;
            }
        }
        return [
            'paths' => $cards,
            'has_paths' => !empty($cards),
            'total' => count($cards),
            'inprogress' => $inprogress,
            'completed' => $completed,
            'notstarted' => count($cards) - $inprogress - $completed,
        ];
    }

    /**
     * One card per path the learner is enrolled in.
     *
     * @param int $userid The learner; the caller is that user (the tenant filter is the caller's).
     * @return array[]
     */
    public static function cards(int $userid): array {
        global $DB;

        [$tsql, $targs] = \local_sentientia_platform\tenant::path_filter('lp');
        $rows = $DB->get_records_sql(
            "SELECT lpu.id AS rowid, lp.id AS pathid, lp.name, lp.description, lp.descriptionformat,
                    lp.startdate, lp.enddate, lpu.status, lpu.timecreated AS enrolled, lpu.timecompleted
               FROM {local_sentientia_learningpath_users} lpu
               JOIN {local_sentientia_learningpath} lp ON lp.id = lpu.pathid
              WHERE lpu.userid = :userid AND lp.status = 1 AND lp.visible = 1 AND $tsql
           ORDER BY CASE WHEN lpu.status = :done THEN 1 ELSE 0 END, lp.name ASC, lp.id ASC",
            ['userid' => $userid, 'done' => path_manager::ENROL_COMPLETED] + $targs);
        if (!$rows) {
            return [];
        }

        $pathids = array_map(static fn($r) => (int) $r->pathid, $rows);
        [$pin, $pparams] = $DB->get_in_or_equal(array_values($pathids), SQL_PARAMS_NAMED, 'lpid');
        $courserows = $DB->get_records_sql(
            "SELECT lpc.id, lpc.pathid, lpc.courseid, lpc.mandatory, c.fullname, c.visible
               FROM {local_sentientia_learningpath_courses} lpc
               JOIN {course} c ON c.id = lpc.courseid
              WHERE lpc.pathid $pin
           ORDER BY lpc.pathid ASC, lpc.sortorder ASC, lpc.id ASC", $pparams);

        $courseids = array_values(array_unique(array_map(static fn($c) => (int) $c->courseid, $courserows)));
        $done = [];
        if ($courseids) {
            [$cin, $cparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'lpco');
            $done = array_flip(array_map('intval', $DB->get_fieldset_select('course_completions', 'course',
                "userid = :userid AND timecompleted > 0 AND course $cin", ['userid' => $userid] + $cparams)));
        }
        $bypath = [];
        foreach ($courserows as $c) {
            $bypath[(int) $c->pathid][] = $c;
        }

        $context = \context_system::instance();
        $cards = [];
        foreach ($rows as $r) {
            $pathid = (int) $r->pathid;
            $courses = [];
            $required = 0;
            $requireddone = 0;
            $anydone = 0;
            foreach ($bypath[$pathid] ?? [] as $c) {
                $iscomplete = isset($done[(int) $c->courseid]);
                $ismandatory = (int) $c->mandatory === 1;
                $anydone += $iscomplete ? 1 : 0;
                $required += $ismandatory ? 1 : 0;
                $requireddone += ($ismandatory && $iscomplete) ? 1 : 0;
                $courses[] = [
                    // escape => false: the template escapes {{name}} once; format_string() escaping too shows "A &amp; B".
                    'name' => format_string($c->fullname, true, ['context' => $context, 'escape' => false]),
                    'url' => (int) $c->visible
                        ? (new \moodle_url('/course/view.php', ['id' => $c->courseid]))->out(false) : '',
                    'has_url' => (bool) (int) $c->visible,
                    'is_done' => $iscomplete,
                    'is_mandatory' => $ismandatory,
                ];
            }
            if ($required === 0) {
                // No course is marked required: progress counts every course.
                $required = count($courses);
                $requireddone = $anydone;
            }

            $iscompleted = (int) $r->status === path_manager::ENROL_COMPLETED;
            $isinprogress = !$iscompleted && $anydone > 0;
            $progress = $iscompleted ? 100 : ($required > 0 ? (int) floor(100 * $requireddone / $required) : 0);

            $text = self::summary($r, $context);
            $cover = path_manager::cover_url($pathid);
            $cards[] = [
                'id' => $pathid,
                'name' => format_string($r->name, true, ['context' => $context, 'escape' => false]),
                'summary' => $text,
                'has_summary' => $text !== '',
                'cover_url' => $cover === null ? '' : $cover->out(false),
                'has_cover' => $cover !== null,
                'is_completed' => $iscompleted,
                'is_inprogress' => $isinprogress,
                'is_notstarted' => !$iscompleted && !$isinprogress,
                'progress' => $progress,
                'course_count' => count($courses),
                'courses_done' => $anydone,
                'has_courses' => !empty($courses),
                'courses' => $courses,
                'enrolled_on' => self::date((int) $r->enrolled),
                'has_enrolled_on' => (int) $r->enrolled > 0,
                'completed_on' => self::date((int) $r->timecompleted),
                'has_completed_on' => $iscompleted && (int) $r->timecompleted > 0,
                'opens_on' => self::date((int) $r->startdate),
                'has_opens_on' => (int) $r->startdate > 0,
                'closes_on' => self::date((int) $r->enddate),
                'has_closes_on' => (int) $r->enddate > 0,
            ];
        }
        return $cards;
    }

    /**
     * A short plain-text summary of the path description.
     *
     * @param \stdClass $row
     * @param \context $context
     * @return string
     */
    private static function summary(\stdClass $row, \context $context): string {
        $description = trim((string) ($row->description ?? ''));
        if ($description === '') {
            return '';
        }
        $text = format_text($description, (int) ($row->descriptionformat ?? FORMAT_HTML),
            ['context' => $context, 'para' => false]);
        return shorten_text(trim(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')), self::SUMMARY_LENGTH);
    }

    /**
     * A date for the page, empty for a missing (0) time so a card never shows 1970.
     *
     * @param int $time
     * @return string
     */
    private static function date(int $time): string {
        return $time > 0 ? userdate($time, '%d %b %Y') : '';
    }
}
