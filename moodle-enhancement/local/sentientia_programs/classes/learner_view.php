<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * What a learner sees of the programs they are enrolled in (ADR-032 code fix 1).
 *
 * The program pages (index.php, view.php) are for managers and editing teachers. BizLMS showed a learner their
 * programs and progress; after the BizLMS import the learner's history is in Sentientia's tables and this class is
 * the reader for it. The page is myprograms.php, behind the flag sentientia.programs.learner.enabled
 * (default OFF).
 *
 * Who is shown what:
 *   - Only the learner's own enrolments.
 *   - Only active, visible programs. A program that was switched off (archived, hidden) is not shown to
 *     learners, only to admins (decision program.inactive_history_to_learners = false; BizLMS treated a
 *     switched-off program as gone).
 *   - Only programs in the learner's own tenant. A program with no tenant path is cross-tenant-only and is
 *     not shown to a tenant learner, in line with ADR-031.
 *   - Enrolments of deleted users are not readable by anyone here, since the page only ever serves $USER.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_view {

    /** Flag of the learner page. */
    public const FLAG_LEARNER = 'sentientia.programs.learner.enabled';

    /** Flag of the imported-history readers on the admin pages. */
    public const FLAG_HISTORY = 'sentientia.programs.history.enabled';

    /**
     * Is the learner page on for the signed-in user?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return \local_sentientia_platform\feature_flags::is_enabled(self::FLAG_LEARNER);
    }

    /**
     * The programs a learner is enrolled in and may see.
     *
     * @param int $userid Must be the signed-in user: the tenant filter is the caller's.
     * @return \stdClass[] Keyed by program id: id, name, description, descriptionformat, status, enrolstatus,
     *                     enrolled_at, timecompleted. Newest enrolment first.
     */
    public static function programs_for_user(int $userid): array {
        global $DB;
        if ($userid <= 1) {
            return [];
        }
        [$tsql, $tparams] = \local_sentientia_platform\tenant::path_filter('p');
        return $DB->get_records_sql(
            "SELECT p.id, p.name, p.description, p.descriptionformat, p.status,
                    pu.status AS enrolstatus, pu.timecreated AS enrolled_at, pu.timecompleted
               FROM {local_sentientia_programs_users} pu
               JOIN {local_sentientia_programs} p ON p.id = pu.programid
              WHERE pu.userid = :uid
                AND p.status = :active
                AND p.visible = 1
                AND $tsql
           ORDER BY pu.timecreated DESC, p.id ASC",
            ['uid' => $userid, 'active' => program_manager::STATUS_ACTIVE] + $tparams);
    }

    /**
     * Add display values to a program_manager::get_user_program_state() result.
     *
     * Empty levels (no course, so nothing to do) are dropped from the list; dates are formatted for the
     * viewer's timezone.
     *
     * @param array $state
     * @return array The same array, levels filtered and decorated.
     */
    public static function decorate_state(array $state): array {
        $levels = [];
        foreach ($state['levels'] as $lvl) {
            if (!empty($lvl['empty'])) {
                continue;
            }
            $lvl['timecompleted_human'] = !empty($lvl['timecompleted'])
                ? userdate((int) $lvl['timecompleted'], get_string('strftimedatefullshort', 'langconfig')) : '';
            $lvl['has_counter'] = empty($lvl['stored']) && (int) $lvl['mandatory_total'] > 0;
            $levels[] = $lvl;
        }
        $state['levels'] = $levels;
        $state['timecompleted_human'] = !empty($state['timecompleted'])
            ? userdate((int) $state['timecompleted'], get_string('strftimedatefullshort', 'langconfig')) : '';
        return $state;
    }

    /**
     * The template context of myprograms.php.
     *
     * @param int $userid
     * @return array
     */
    public static function page_data(int $userid): array {
        $programs = [];
        foreach (self::programs_for_user($userid) as $program) {
            $state = self::decorate_state(program_manager::get_user_program_state((int) $program->id, $userid));
            $completed = (int) $program->enrolstatus === program_manager::ENROL_COMPLETED;
            $logo = program_manager::program_logo_url((int) $program->id);
            $programs[] = [
                'id'                  => (int) $program->id,
                'name'                => format_string($program->name),
                'description'         => format_text((string) ($program->description ?? ''),
                    (int) ($program->descriptionformat ?? FORMAT_HTML)),
                'has_description'     => trim((string) ($program->description ?? '')) !== '',
                'has_logo'            => $logo !== null,
                'logo_url'            => $logo ? $logo->out(false) : '',
                'completed'           => $completed,
                'inprogress'          => !$completed && (int) $program->enrolstatus === program_manager::ENROL_INPROGRESS,
                'enrolled_human'      => userdate((int) $program->enrolled_at,
                    get_string('strftimedatefullshort', 'langconfig')),
                'completed_human'     => ($completed && $program->timecompleted)
                    ? userdate((int) $program->timecompleted, get_string('strftimedatefullshort', 'langconfig')) : '',
                'overall_pct'         => (int) $state['overall_pct'],
                'completed_levels'    => (int) $state['completed_levels'],
                'total_levels'        => (int) $state['total_levels'],
                'has_levels'          => !empty($state['levels']),
                'levels'              => $state['levels'],
            ];
        }
        return [
            'programs'     => $programs,
            'has_programs' => !empty($programs),
        ];
    }
}
