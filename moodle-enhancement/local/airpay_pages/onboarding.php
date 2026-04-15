<?php
/**
 * Airpay Academy — Learner Onboarding Wizard.
 * Shown on first login only. Collects interests, recommends courses, sets goals.
 * Stores completion flag in user preference to never show again.
 *
 * @package    local_airpay_pages
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

// Check if onboarding already completed — skip if so.
$completed = get_user_preferences('airpay_onboarding_complete', 0, $USER->id);
if ($completed) {
    redirect(new moodle_url('/my/'));
}

// Handle form submission: save preferences and mark complete.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'complete' && confirm_sesskey()) {
    set_user_preference('airpay_onboarding_complete', 1, $USER->id);

    // Save selected interests.
    $interests = optional_param_array('interests', [], PARAM_INT);
    if (!empty($interests)) {
        set_user_preference('airpay_learning_interests', implode(',', $interests), $USER->id);
    }

    // Save weekly goal.
    $goal = optional_param('weekly_goal', 3, PARAM_INT);
    set_user_preference('airpay_weekly_goal', min(max($goal, 1), 7), $USER->id);

    redirect(new moodle_url('/my/'), 'Welcome to Airpay Academy! Your dashboard is ready.', null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Handle skip.
if ($action === 'skip' && confirm_sesskey()) {
    set_user_preference('airpay_onboarding_complete', 1, $USER->id);
    redirect(new moodle_url('/my/'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/airpay_pages/onboarding.php');
$PAGE->set_title('Welcome to Airpay Academy');
$PAGE->set_pagelayout('embedded'); // Minimal layout — no navbar clutter.

// Get available categories for interest selection.
$categories = $DB->get_records_sql(
    "SELECT cc.id, cc.name, COUNT(c.id) as course_count
       FROM {course_categories} cc
       JOIN {course} c ON c.category = cc.id AND c.visible = 1 AND c.id > 1
     GROUP BY cc.id, cc.name
       HAVING COUNT(c.id) > 0
     ORDER BY COUNT(c.id) DESC",
    [], 0, 12);

$catdata = [];
$icons = ['fa-briefcase', 'fa-shield', 'fa-line-chart', 'fa-users', 'fa-code', 'fa-university',
    'fa-cogs', 'fa-graduation-cap', 'fa-lightbulb-o', 'fa-heart', 'fa-rocket', 'fa-globe'];
$i = 0;
foreach ($categories as $cat) {
    $catdata[] = [
        'id'    => $cat->id,
        'name'  => format_string($cat->name),
        'count' => $cat->course_count,
        'icon'  => $icons[$i % count($icons)],
    ];
    $i++;
}

// Get 3 recommended courses for the user (most enrolled, visible).
$recommended = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.shortname, COUNT(ue.id) as enrolcount
       FROM {course} c
       JOIN {enrol} e ON e.courseid = c.id
       JOIN {user_enrolments} ue ON ue.enrolid = e.id
      WHERE c.visible = 1 AND c.id > 1
     GROUP BY c.id, c.fullname, c.shortname
     ORDER BY COUNT(ue.id) DESC",
    [], 0, 3);

$recdata = [];
foreach ($recommended as $r) {
    $recdata[] = [
        'id'       => $r->id,
        'fullname' => format_string($r->fullname),
        'shortname' => format_string($r->shortname),
        'enrolled' => (int)$r->enrolcount,
        'url'      => (new moodle_url('/local/search/coursedetails.php', ['id' => $r->id]))->out(false),
    ];
}

$data = [
    'firstname'    => format_string($USER->firstname),
    'categories'   => $catdata,
    'has_categories' => !empty($catdata),
    'recommended'  => $recdata,
    'has_recommended' => !empty($recdata),
    'sesskey'      => sesskey(),
    'actionurl'    => (new moodle_url('/local/airpay_pages/onboarding.php'))->out(false),
    'logourl'      => (new moodle_url('/theme/airpayux/pix/brand/academy-logo-350.png'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_pages/onboarding', $data);
echo $OUTPUT->footer();
