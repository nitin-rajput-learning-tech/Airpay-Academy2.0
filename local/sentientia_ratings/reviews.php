<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Item reviews page: the average rating, the like and dislike counts and the written reviews of a course,
 * classroom, programme or learning path (ADR-032: what the BizLMS import brought over).
 *
 * Both the reviews and the reactions are NEW surfaces and ship behind their own flags, OFF by default
 * (sentientia.ratings.reviews, sentientia.ratings.reactions). With both off this page does not exist.
 *
 *   /local/sentientia_ratings/reviews.php?itemid=42&area=local_sentientia_courses
 *
 * @package    local_sentientia_ratings
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$itemid = required_param('itemid', PARAM_INT);
$area = required_param('area', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/sentientia_ratings:rate', $context);

$data = \local_sentientia_ratings\item_summary::export($itemid, $area, $USER, max(0, $page));
if ($data === null) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_ratings');
}

$url = new moodle_url('/local/sentientia_ratings/reviews.php', ['itemid' => $itemid, 'area' => $area]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('reviewspagetitle', 'local_sentientia_ratings'));
$PAGE->set_heading(get_string('reviewspagetitle', 'local_sentientia_ratings'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_ratings/review_list', $data);
if ($data['showreviews'] && $data['total'] > \local_sentientia_ratings\review_manager::PERPAGE) {
    echo $OUTPUT->paging_bar($data['total'], max(0, $page), \local_sentientia_ratings\review_manager::PERPAGE, $url);
}
echo $OUTPUT->footer();
