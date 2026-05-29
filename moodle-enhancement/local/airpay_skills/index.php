<?php
/**
 * Airpay Skills Dashboard — learner-facing gap analysis + radar chart.
 *
 * @package    local_airpay_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE, $CFG;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_skills/index.php');
$PAGE->set_title(get_string('pluginname', 'local_airpay_skills'));
$PAGE->set_heading(get_string('pluginname', 'local_airpay_skills'));
$PAGE->set_pagelayout('standard');

$userid = optional_param('userid', $USER->id, PARAM_INT);

// Only admins/managers can view other users' skills.
if ($userid !== $USER->id && !is_siteadmin()) {
    $hasmanagecap = has_capability('local/courses:manage', context_system::instance());
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
}

$manager = \local_airpay_skills\skills_manager::class;

// Gap analysis data.
$analysis = $manager::get_gap_analysis($userid);

// Radar chart data.
$radar = $manager::get_radar_data($userid);

// Recommended courses to close gaps.
$recommendations = [];
if ($analysis['has_data'] && $analysis['summary']['gaps'] > 0) {
    $recs = $manager::get_gap_courses($userid, 5);
    foreach ($recs as $r) {
        $recommendations[] = [
            'coursename'   => format_string($r->coursename),
            'skill_name'   => format_string($r->skill_name),
            'teaches_label' => $manager::LEVELS[$r->teaches_level] ?? '',
            'detailurl'    => (new moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false),
        ];
    }
}

// Summary ring offset for SVG.
$summary_pct = $analysis['summary']['percentage'] ?? 0;
$summary_offset = round(238.76 * (1 - $summary_pct / 100), 2);

$data = array_merge($analysis, $radar, [
    'summary_offset'       => $summary_offset,
    'has_recommendations'  => !empty($recommendations),
    'recommendations'      => $recommendations,
]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_skills/dashboard', $data);
echo $OUTPUT->footer();
