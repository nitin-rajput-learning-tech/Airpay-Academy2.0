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

// ─── Tenant isolation (2026-05-28) ─────────────────────────────────────
// Resolve the user's top-level tenant category. Without this, learners
// from one tenant could see another tenant's org-tree categories (e.g. a
// Public learner seeing AIRPAY PAYMENT SERVICES, ZEEA, Vyaapaar, Tanzania
// subsidiaries in their interest picker — a real cross-tenant leak we
// shipped to local on 2026-05-28).
//
// Resolution chain (in local_airpay_org\accesslib::get_tenant_category_id):
//   1. BizLMS canonical via local_costcenter.category by path (prod)
//   2. Sentientia-native via org.shortname ↔ category.idnumber (works on
//      vanilla Moodle Sentientia deployments without BizLMS)
//   3. null → fail closed (render zero categories rather than leak all)
//
// Site admins and the rare admin-impersonating-onboarding case still get
// scoped to whichever tenant their open_path indicates — admins are
// already redirected away from onboarding in layout/dashboard.php, so
// this branch is for learners and managers only.
$tenant_catid = \local_airpay_org\accesslib::get_tenant_category_id(
    (string) ($USER->open_path ?? ''));
$tenant_catpath = '';
if ($tenant_catid) {
    $tenant_catpath = (string) $DB->get_field('course_categories', 'path',
        ['id' => $tenant_catid]);
}

$catdata = [];
if ($tenant_catid && $tenant_catpath !== '') {
    // Get categories within the user's tenant subtree (root + descendants).
    // Excludes the tenant root category itself when it has direct courses —
    // we surface the subsidiaries/topics, not the tenant label.
    $categories = $DB->get_records_sql(
        "SELECT cc.id, cc.name, COUNT(c.id) AS course_count
           FROM {course_categories} cc
           JOIN {course} c ON c.category = cc.id AND c.visible = 1 AND c.id > 1
          WHERE cc.id = :catid OR " . $DB->sql_like('cc.path', ':catpathwild') . "
       GROUP BY cc.id, cc.name
         HAVING COUNT(c.id) > 0
       ORDER BY COUNT(c.id) DESC",
        [
            'catid'        => $tenant_catid,
            'catpathwild'  => $tenant_catpath . '/%',
        ], 0, 12);

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
}

// Get 3 recommended courses for the user (most enrolled, visible) —
// scoped to the user's tenant subtree. Same resolver as above.
if ($tenant_catid && $tenant_catpath !== '') {
    $recommended = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.shortname, COUNT(ue.id) AS enrolcount
           FROM {course} c
           JOIN {course_categories} cc ON cc.id = c.category
           JOIN {enrol} e ON e.courseid = c.id
           JOIN {user_enrolments} ue ON ue.enrolid = e.id
          WHERE c.visible = 1 AND c.id > 1
            AND (cc.id = :catid OR " . $DB->sql_like('cc.path', ':catpathwild') . ")
       GROUP BY c.id, c.fullname, c.shortname
       ORDER BY COUNT(ue.id) DESC",
        [
            'catid'        => $tenant_catid,
            'catpathwild'  => $tenant_catpath . '/%',
        ], 0, 3);
} else {
    $recommended = [];
}

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
