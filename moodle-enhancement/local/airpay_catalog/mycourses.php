<?php
/**
 * My Courses — custom Airpay replacement for /my/courses.php.
 *
 * Shows enrolled courses with progress bars, images, categories,
 * and the Airpay design system. Replaces Moodle's block_myoverview.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

require_login();

$PAGE->set_url(new moodle_url('/local/airpay_catalog/mycourses.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('mycourses'));
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('mycourses'));

$filter = optional_param('filter', 'all', PARAM_ALPHA); // all, inprogress, completed, notstarted

// Get all enrolled courses for the current user.
$enrolledcourses = enrol_get_my_courses('*', 'fullname ASC');

$courses = [];
$stats = ['total' => 0, 'inprogress' => 0, 'completed' => 0, 'notstarted' => 0];

foreach ($enrolledcourses as $course) {
    $completion = new completion_info($course);
    $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);

    $status = 'notstarted';
    if ($progress !== null && $progress >= 100) {
        $status = 'completed';
        $stats['completed']++;
    } elseif ($progress !== null && $progress > 0) {
        $status = 'inprogress';
        $stats['inprogress']++;
    } else {
        $stats['notstarted']++;
    }
    $stats['total']++;

    // Apply filter.
    if ($filter !== 'all' && $filter !== $status) {
        continue;
    }

    // Get course image.
    $courseimage = '';
    $context = context_course::instance($course->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false, 'sortorder', false);
    foreach ($files as $f) {
        if ($f->is_valid_image()) {
            $courseimage = moodle_url::make_pluginfile_url(
                $f->get_contextid(), $f->get_component(), $f->get_filearea(),
                null, $f->get_filepath(), $f->get_filename()
            )->out(false);
            break;
        }
    }

    // Get category name.
    $categoryname = '';
    try {
        if (!empty($course->open_categoryid)) {
            $categoryname = $DB->get_field('local_custom_category', 'fullname', ['id' => $course->open_categoryid]);
        }
        if (empty($categoryname) && $course->category > 0) {
            $categoryname = $DB->get_field('course_categories', 'name', ['id' => $course->category]);
        }
    } catch (Exception $e) {
        $categoryname = '';
    }

    // Last accessed.
    $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess',
        ['userid' => $USER->id, 'courseid' => $course->id]);

    $courses[] = [
        'id'           => $course->id,
        'fullname'     => format_string($course->fullname),
        'shortname'    => format_string($course->shortname),
        'imageurl'     => $courseimage,
        'has_image'    => !empty($courseimage),
        'categoryname' => format_string($categoryname ?: 'General'),
        'progress'     => $progress !== null ? round($progress) : 0,
        'has_progress' => ($progress !== null && $progress > 0),
        'status'       => $status,
        'is_completed' => ($status === 'completed'),
        'is_inprogress' => ($status === 'inprogress'),
        'is_notstarted' => ($status === 'notstarted'),
        'viewurl'      => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'detailurl'    => (new moodle_url('/local/search/coursedetails.php', ['id' => $course->id]))->out(false),
        'lastaccess'   => $lastaccess ? userdate($lastaccess, '%d %b %Y') : 'Never',
    ];
}

$data = [
    'courses'       => $courses,
    'has_courses'   => !empty($courses),
    'total'         => $stats['total'],
    'completed'     => $stats['completed'],
    'inprogress'    => $stats['inprogress'],
    'notstarted'    => $stats['notstarted'],
    'filter_all'    => ($filter === 'all'),
    'filter_inprogress' => ($filter === 'inprogress'),
    'filter_completed' => ($filter === 'completed'),
    'filter_notstarted' => ($filter === 'notstarted'),
    'baseurl'       => (new moodle_url('/local/airpay_catalog/mycourses.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_catalog/mycourses', $data);
echo $OUTPUT->footer();
