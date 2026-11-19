<?php
// Airpay Course Management — admin (datatable-driven).
//
// @package    local_sentientia_courses
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sentientia_courses:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sentientia_courses/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_sentientia_courses'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_courses'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

$can_create = has_capability('local/sentientia_courses:create', $context);
$can_manage = has_capability('local/sentientia_courses:manage', $context);
$can_enrol  = has_capability('local/sentientia_courses:enrol', $context);

// KPI counts + category options — tenant-scoped to the SAME row set the
// datatable lists, so a tenant admin is never shown a global total above a
// tenant-scoped table (UAT ZEEA findings #3 + #4). Site admins keep global
// counts + all categories; course_manager owns the site-vs-tenant split.
$kpi = \local_sentientia_courses\course_manager::manage_kpi_counts();
$total_count   = $kpi['total'];
$visible_count = $kpi['visible'];
$hidden_count  = $kpi['hidden'];

$cat_options = \local_sentientia_courses\course_manager::manage_category_options();

// Datatable columns.
$columns = [
    ['key' => 'fullname',    'label' => 'Course Name', 'sortable' => true,  'sortkey' => 'fullname',   'format' => 'html'],
    ['key' => 'shortname',   'label' => 'Code',        'sortable' => true,  'sortkey' => 'shortname'],
    ['key' => 'catname',     'label' => 'Category',    'sortable' => false],
    ['key' => 'enrolled',    'label' => 'Enrolled',    'sortable' => false],
    ['key' => 'created',     'label' => 'Created',     'sortable' => true,  'sortkey' => 'timecreated'],
    ['key' => 'statuslabel', 'label' => 'Visibility',  'sortable' => true,  'sortkey' => 'visible', 'format' => 'badge'],
    // Surface walk 2026-11-18 V19: the rows carry 'actions'; declaring the column gives it a header
    // cell (theme datatable.js WF-023 path), so the blue header spans the table.
    ['key' => 'actions', 'label' => get_string('actions'), 'sortable' => false, 'format' => 'html'],
];

// Phase B0+ — stat_card-compatible tiles.
$kpi_tiles = [
    [
        'label' => 'Total Courses',
        'value' => number_format($total_count),
        'icon'  => 'book',
        'color' => 'primary',
    ],
    [
        'label' => 'Visible',
        'value' => number_format($visible_count),
        'icon'  => 'eye',
        'color' => 'success',
    ],
    [
        'label' => 'Hidden',
        'value' => number_format($hidden_count),
        'icon'  => 'eye-slash',
        'color' => 'info',
    ],
];

$data = [
    'total_count'    => number_format($total_count),
    'visible_count'  => number_format($visible_count),
    'hidden_count'   => number_format($hidden_count),
    'kpi_tiles'      => $kpi_tiles,
    'has_kpi_tiles'  => !empty($kpi_tiles),
    'can_create'     => $can_create,
    'can_manage'     => $can_manage,
    'can_enrol'      => $can_enrol,
    // UAT fix 2026-05-09: route URLs through moodle_url so they work on
    // installs not rooted at / (this XAMPP runs at /moodle/).
    'featured_url'   => (new moodle_url('/local/sentientia_courses/featured.php'))->out(false),
    'enrol_csv_url'  => (new moodle_url('/local/sentientia_courses/enrol_csv.php'))->out(false),
    'export_url'     => (new moodle_url('/local/sentientia_courses/exportcsv.php'))->out(false),
    'cat_options'    => $cat_options,
    'has_cat_options' => !empty($cat_options),
    'columns_json'   => s(json_encode($columns)),
    // W1-1 BizLMS parity: 5-level org hierarchy cascade.
    'cascade_group'  => 'courses-filter',
];

// W1-1 BizLMS parity: explicit AMD bootstrap for the 5-level cascade.
$PAGE->requires->js_call_amd('theme_sentientia/org_cascade', 'init');

// Verify B fix (2026-11-20): the modal forms on this page (new/edit course,
// enrol users) register their disabledIf rules through core's
// M.form.initFormDependencies, which runs from a deferred YUI
// Y.use('mform') callback queued behind the date selector's calendar
// modules. On a slow load that callback can fire after the user has closed
// the modal. ModalForm destroys the form on close, so core's
// dependencyManager gets a null form and throws "Cannot read properties of
// null (reading 'get')" (lib/form/form.js initElementsByName). Any form with
// a rule does this, the date selector's enddate[enabled] rule included.
// Load 'mform' with the page and skip the init for a form that is no longer
// in the document: nothing is left to initialise. Forms still on the page
// initialise exactly as before. Registered before any modal opens, so YUI
// runs this callback ahead of every queued form init.
require_once($CFG->libdir . '/formslib.php');
$PAGE->requires->js_init_code("
    var coreinit = M.form.initFormDependencies;
    if (coreinit && !coreinit.skipsRemovedForms) {
        M.form.initFormDependencies = function(Y, formid, dependencies) {
            if (!document.getElementById(formid)) {
                return false;
            }
            return coreinit.call(this, Y, formid, dependencies);
        };
        M.form.initFormDependencies.skipsRemovedForms = true;
    }
", false, moodleform::get_js_module());

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_courses/manage', $data);
echo $OUTPUT->footer();
