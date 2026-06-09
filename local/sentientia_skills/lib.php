<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Get skills summary for dashboard integration.
 */
function local_sentientia_skills_get_dashboard_data(int $userid): array {
    $analysis = \local_sentientia_skills\skills_manager::get_gap_analysis($userid);
    $radar    = \local_sentientia_skills\skills_manager::get_radar_data($userid);
    $gapcourses = \local_sentientia_skills\skills_manager::get_gap_courses($userid, 3);

    return array_merge($analysis, $radar, [
        'gap_courses'     => $gapcourses,
        'has_gap_courses' => !empty($gapcourses),
    ]);
}
