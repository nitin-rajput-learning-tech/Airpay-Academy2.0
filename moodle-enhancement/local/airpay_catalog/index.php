<?php
/**
 * Airpay LXP-Style Course Catalog — main page.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

$PAGE->set_url(new moodle_url('/local/airpay_catalog/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('catalog', 'local_airpay_catalog'));
$PAGE->set_pagelayout('standard');

$search = optional_param('q', '', PARAM_TEXT);
$category = optional_param('category', 0, PARAM_INT);
$sort = optional_param('sort', 'newest', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$userid = $USER->id;

// Fetch catalog sections.
$manager = \local_airpay_catalog\catalog_manager::class;

$in_progress = $manager::get_in_progress($userid, 6);
$trending    = $manager::get_trending($userid, 6);
$new_courses = $manager::get_new($userid, 6);
$categories  = $manager::get_categories();

// Filtered course list.
$filters = [];
if ($category) {
    $filters['category'] = $category;
}
$results = $manager::get_courses($userid, $search, $filters, $sort, $page, 12);

// Add progress_offset for SVG ring in continue-learning cards.
foreach ($in_progress as &$ip) {
    $pct = $ip['progress'] ?? 0;
    $ip['progress_offset'] = round(94.25 * (1 - $pct / 100), 2);
}
unset($ip);

// Resolve active category name for filter chip display.
$active_category_name = '';
if ($category) {
    $catobj = $DB->get_record('course_categories', ['id' => $category], 'name');
    $active_category_name = $catobj ? format_string($catobj->name) : '';
}

// Build template data.
$data = [
    'search_query'  => s($search),
    'has_search'    => !empty($search),

    // Carousels.
    'in_progress'       => $in_progress,
    'has_in_progress'   => !empty($in_progress),
    'trending'          => $trending,
    'has_trending'      => !empty($trending),
    'new_courses'       => $new_courses,
    'has_new_courses'   => !empty($new_courses),

    // Categories.
    'categories'        => $categories,
    'has_categories'    => !empty($categories),

    // Active category filter.
    'has_active_category'   => !empty($category) && !empty($active_category_name),
    'active_category_id'    => $category,
    'active_category_name'  => $active_category_name,

    // All courses (filtered/paginated).
    'courses'           => $results['courses'],
    'has_courses'       => !empty($results['courses']),
    'total'             => $results['total'],
    'pages'             => $results['pages'],
    'current_page'      => $results['page'],
    'has_more'          => $results['has_more'],
    'showing_from'      => ($results['page'] * 12) + 1,
    'showing_to'        => min(($results['page'] + 1) * 12, $results['total']),

    // Sort options.
    'sort'              => $sort,
    'sort_newest'       => ($sort === 'newest'),
    'sort_popular'      => ($sort === 'popular'),
    'sort_name'         => ($sort === 'name'),

    // URLs.
    'baseurl'           => (new moodle_url('/local/airpay_catalog/index.php'))->out(false),
    'searchurl'         => (new moodle_url('/local/airpay_catalog/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_catalog/catalog', $data);
echo $OUTPUT->footer();
