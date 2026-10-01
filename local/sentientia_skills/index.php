<?php
/**
 * Airpay Skills Dashboard — learner-facing gap analysis + radar chart.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/sentientia_skills/index.php');
// Phase E (UI-NAV-AUDIT N-08): learner-facing title matching the sidebar
// label ("My Skills"), not the internal product name.
$PAGE->set_title(get_string('myskills_title', 'local_sentientia_skills'));
$PAGE->set_heading(get_string('myskills_title', 'local_sentientia_skills'));
$PAGE->set_pagelayout('standard');

$userid = optional_param('userid', $USER->id, PARAM_INT);

// Only admins/managers can view other users' skills.
if ($userid !== $USER->id && !is_siteadmin()) {
    // D9 (persona pass 2026-09-30): the retired BizLMS local/courses:manage became
    // local/sentientia_courses:manage under ADR-025; the old name is declared nowhere.
    $hasmanagecap = has_capability('local/sentientia_courses:manage', context_system::instance());
    $isdirectreport = false;
    if (!$hasmanagecap) {
        try {
            $isdirectreport = $DB->record_exists_select('user',
                'id = :uid AND open_supervisorid = :mgr AND deleted = 0',
                ['uid' => $userid, 'mgr' => $USER->id]);
        } catch (\Throwable $e) {
            $isdirectreport = false;
        }
    }
    if (!$hasmanagecap && !$isdirectreport) {
        throw new moodle_exception('nopermission', 'error', '',
            null, 'You do not have permission to view this user\'s skills.');
    }
    // ADR-031: local/sentientia_courses:manage says WHAT, not WHERE - a course manager
    // may open the gap analysis of users in their own tenant only (it used to
    // be any user id in any tenant). A direct report is theirs by definition.
    if ($hasmanagecap) {
        \local_sentientia_platform\tenant::require_same_tenant_user($userid);
    }
}

$manager = \local_sentientia_skills\skills_manager::class;

// Gap analysis data.
$analysis = $manager::get_gap_analysis($userid);

// Radar chart data.
$radar = $manager::get_radar_data($userid);

// Recommended courses: the ones that close a gap, then - with the skills-first recommendations flag ON - courses
// for the skills the learner said they are interested in (ADR-032). get_gap_courses() returns arrays already
// formatted for display (fullname, skill_name, teaches_level, viewurl); this page read them as objects with other
// field names (coursename, courseid), so every card rendered empty.
$recommendations = [];
$hasinterestrecs = false;
foreach ($manager::get_recommended_courses($userid, 5) as $r) {
    $recommendations[] = [
        'coursename'    => $r['fullname'],
        'skill_name'    => $r['skill_name'],
        'teaches_label' => $manager::LEVELS[(int) ($r['teaches_level'] ?? 0)] ?? '',
        'detailurl'     => $r['viewurl'],
    ];
    $hasinterestrecs = $hasinterestrecs || ($r['reason'] ?? '') === 'interest';
}

// ADR-032 readers, each behind its own default-OFF flag: what the learner already holds (when the designation has
// no role skills to compare against) and the skills the learner said they are interested in.
$heldskills = [];
if (!$analysis['has_data'] && $manager::flag_enabled($manager::FLAG_HELD)) {
    $heldskills = $manager::get_held_skills($userid);
}
$interests = [];
if ($manager::flag_enabled($manager::FLAG_RECS)) {
    $interests = $manager::get_interest_skills($userid);
}

// Summary ring offset for SVG.
$summary_pct = $analysis['summary']['percentage'] ?? 0;
$summary_offset = round(238.76 * (1 - $summary_pct / 100), 2);

$data = array_merge($analysis, $radar, [
    'summary_offset'       => $summary_offset,
    'has_recommendations'  => !empty($recommendations),
    'recommendations'      => $recommendations,
    'has_interest_recs'    => $hasinterestrecs,
    'has_held_skills'      => !empty($heldskills),
    'held_skills'          => $heldskills,
    'has_interests'        => !empty($interests),
    'interests'            => $interests,
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_skills/dashboard', $data);
echo $OUTPUT->footer();
