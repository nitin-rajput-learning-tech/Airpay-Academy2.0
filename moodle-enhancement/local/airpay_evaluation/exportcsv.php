<?php
// Airpay Evaluations — CSV export of raw responses (G-05).
//
// URL: /local/airpay_evaluation/exportcsv.php?id=<evaluationid>
//      [&date_from=YYYY-MM-DD][&date_to=YYYY-MM-DD]
//      [&courseid=N][&programid=N][&classroomid=N]
//
// Streams a CSV download with one row per response. Uses
// evaluation_manager::response_to_csv_row() so the column layout stays
// in sync with the in-app analysis page.
//
// @package    local_airpay_evaluation
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/airpay_evaluation:manage', $context);

$evaluationid = required_param('id', PARAM_INT);

// Optional filters — same parameter names as responses.php uses.
$date_from   = optional_param('date_from',   '', PARAM_RAW);
$date_to     = optional_param('date_to',     '', PARAM_RAW);
$courseid    = optional_param('courseid',    0,  PARAM_INT);
$programid   = optional_param('programid',   0,  PARAM_INT);
$classroomid = optional_param('classroomid', 0,  PARAM_INT);

$eval = $DB->get_record('local_airpay_evaluation',
    ['id' => $evaluationid], '*', MUST_EXIST);

// Tenant scope: non-siteadmin only sees evaluations in their org tree.
if (!is_siteadmin()) {
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    if ($top > 0 && !empty($eval->open_path)) {
        $epath = trim($eval->open_path, '/');
        $eparts = explode('/', $epath);
        $etop = isset($eparts[0]) && ctype_digit($eparts[0]) ? (int) $eparts[0] : 0;
        if ($etop !== $top) {
            throw new \moodle_exception('nopermissions', 'error');
        }
    }
}

// Build the filter array. Date strings come in as YYYY-MM-DD; parse to ts.
$filters = ['evaluationid' => $evaluationid];
if (!empty($date_from)) {
    $ts = strtotime($date_from);
    if ($ts !== false) { $filters['date_from'] = $ts; }
}
if (!empty($date_to)) {
    $ts = strtotime($date_to . ' 23:59:59');
    if ($ts !== false) { $filters['date_to'] = $ts; }
}
if ($courseid    > 0) { $filters['courseid']    = $courseid; }
if ($programid   > 0) { $filters['programid']   = $programid; }
if ($classroomid > 0) { $filters['classroomid'] = $classroomid; }

$questions = \local_airpay_evaluation\evaluation_manager::get_questions($evaluationid);
$responses = \local_airpay_evaluation\evaluation_manager::get_responses_filtered($filters);

// Filename: <evaluation slug>-responses-<YYYYMMDD>.csv
$slug = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($eval->name));
$slug = trim($slug, '-');
if ($slug === '') { $slug = 'evaluation-' . $eval->id; }
$filename = $slug . '-responses-' . date('Ymd') . '.csv';

// Headers — force download.
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it with the right encoding.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, \local_airpay_evaluation\evaluation_manager::csv_header_row($questions));
foreach ($responses as $r) {
    fputcsv($out,
        \local_airpay_evaluation\evaluation_manager::response_to_csv_row($r, $questions, $eval));
}
fclose($out);

exit;
