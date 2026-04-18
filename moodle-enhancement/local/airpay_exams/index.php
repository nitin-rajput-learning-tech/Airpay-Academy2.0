<?php
// Airpay Online Exams — admin exam management.
//
// Queries Moodle quiz activities across courses as the exam engine.
// BizLMS stored exams in local_onlinetests; we use native quiz module.
//
// @package    local_airpay_exams
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/airpay_exams:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/airpay_exams/index.php'));
$PAGE->set_title('Online Exams');
$PAGE->set_heading('Online Exams');
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);

// ── Filters ───────────────────────────────────────────────────────────
$orgid   = optional_param('orgid', 0, PARAM_INT);
$status  = optional_param('status', 'all', PARAM_ALPHA);
$search  = optional_param('search', '', PARAM_TEXT);
$page    = optional_param('page', 0, PARAM_INT);
$perpage = 25;

$can_manage = has_capability('local/airpay_exams:manage', $context);

// ── Build query — quiz activities across courses ──────────────────────
$whereclauses = ["c.id > 1", "c.visible = 1"];
$params = [];

if ($orgid > 0) {
    $org = $DB->get_record('local_airpay_org', ['id' => $orgid]);
    if ($org) {
        $whereclauses[] = "c.open_path LIKE :orgpath";
        $params['orgpath'] = $org->path . '%';
    }
} else if (!is_siteadmin()) {
    global $USER;
    $parts = explode('/', trim($USER->open_path ?? '', '/'));
    if (!empty($parts[0])) {
        $whereclauses[] = "c.open_path LIKE :userorg";
        $params['userorg'] = '/' . $parts[0] . '%';
    }
}

if (!empty($search)) {
    $s = '%' . $DB->sql_like_escape($search) . '%';
    $whereclauses[] = "(" . $DB->sql_like('q.name', ':s1', false) . " OR " . $DB->sql_like('c.fullname', ':s2', false) . ")";
    $params['s1'] = $s;
    $params['s2'] = $s;
}

$now = time();
if ($status === 'open') {
    $whereclauses[] = "(q.timeopen = 0 OR q.timeopen <= :now1) AND (q.timeclose = 0 OR q.timeclose > :now2)";
    $params['now1'] = $now;
    $params['now2'] = $now;
} else if ($status === 'closed') {
    $whereclauses[] = "q.timeclose > 0 AND q.timeclose < :now3";
    $params['now3'] = $now;
}

$where = implode(' AND ', $whereclauses);

// ── Counts ────────────────────────────────────────────────────────────
$basewhere = implode(' AND ', array_filter($whereclauses, fn($c) => strpos($c, 'timeopen') === false && strpos($c, 'timeclose') === false));
$total_count = $DB->count_records_sql(
    "SELECT COUNT(q.id) FROM {quiz} q JOIN {course} c ON c.id = q.course WHERE {$basewhere}", $params);
$total_attempts = $DB->count_records_sql(
    "SELECT COUNT(qa.id) FROM {quiz_attempts} qa
       JOIN {quiz} q ON q.id = qa.quiz
       JOIN {course} c ON c.id = q.course WHERE {$basewhere}", $params);
$total_passed = $DB->count_records_sql(
    "SELECT COUNT(qa.id) FROM {quiz_attempts} qa
       JOIN {quiz} q ON q.id = qa.quiz
       JOIN {course} c ON c.id = q.course
      WHERE qa.state = 'finished' AND {$basewhere}", $params);

// ── Fetch quizzes ─────────────────────────────────────────────────────
$sql = "SELECT q.id, q.name, q.timeopen, q.timeclose, q.timelimit, q.grade AS maxgrade,
               c.id AS courseid, c.fullname AS coursename, c.open_path,
               (SELECT COUNT(qa.id) FROM {quiz_attempts} qa WHERE qa.quiz = q.id) AS attempts,
               (SELECT COUNT(qa.id) FROM {quiz_attempts} qa WHERE qa.quiz = q.id AND qa.state = 'finished') AS finished,
               (SELECT ROUND(AVG(qa.sumgrades), 1) FROM {quiz_attempts} qa WHERE qa.quiz = q.id AND qa.state = 'finished') AS avg_score
          FROM {quiz} q
          JOIN {course} c ON c.id = q.course
         WHERE {$where}
      ORDER BY q.name ASC";

$quizzes = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
$filtered_count = $DB->count_records_sql("SELECT COUNT(q.id) FROM {quiz} q JOIN {course} c ON c.id = q.course WHERE {$where}", $params);

// Org lookup.
$orgs = $DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC');
$org_lookup = [];
foreach ($orgs as $o) { $org_lookup[$o->path] = format_string($o->fullname); }

$rows = [];
foreach ($quizzes as $q) {
    $parts = explode('/', trim($q->open_path ?? '', '/'));
    $orgname = $org_lookup['/' . ($parts[0] ?? '')] ?? '—';

    $is_open = ($q->timeopen == 0 || $q->timeopen <= $now) && ($q->timeclose == 0 || $q->timeclose > $now);

    $rows[] = [
        'id'         => $q->id,
        'name'       => format_string($q->name),
        'coursename' => format_string($q->coursename),
        'orgname'    => $orgname,
        'attempts'   => (int) $q->attempts,
        'finished'   => (int) $q->finished,
        'avg_score'  => $q->avg_score ? number_format($q->avg_score, 1) : '—',
        'timelimit'  => $q->timelimit > 0 ? round($q->timelimit / 60) . ' min' : 'No limit',
        'is_open'    => $is_open,
        'statuslabel' => $is_open ? 'Open' : ($q->timeclose > 0 ? 'Closed' : 'Open'),
        'statuscss'  => $is_open ? 'badge-success' : 'badge-secondary',
        'opens'      => $q->timeopen > 0 ? userdate($q->timeopen, '%d %b %Y') : '—',
        'closes'     => $q->timeclose > 0 ? userdate($q->timeclose, '%d %b %Y') : 'No deadline',
        'viewurl'    => (new moodle_url('/mod/quiz/view.php', ['q' => $q->id]))->out(false),
        'editurl'    => (new moodle_url('/course/modedit.php', ['update' => $DB->get_field('course_modules', 'id',
                            ['instance' => $q->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])])]))->out(false),
        'reporturl'  => (new moodle_url('/mod/quiz/report.php', ['id' => $DB->get_field('course_modules', 'id',
                            ['instance' => $q->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])])]))->out(false),
        'can_manage'  => $can_manage,
    ];
}

// Org filter options.
$org_options = [];
foreach ($orgs as $o) {
    $org_options[] = ['id' => $o->id, 'name' => format_string($o->fullname), 'selected' => ($o->id == $orgid)];
}

// Pagination.
$baseurl = new moodle_url('/local/airpay_exams/index.php', ['orgid' => $orgid, 'status' => $status, 'search' => $search]);
$total_pages = ceil($filtered_count / $perpage);
$pagination_pages = [];
for ($i = 0; $i < min($total_pages, 20); $i++) {
    $purl = clone $baseurl; $purl->param('page', $i);
    $pagination_pages[] = ['pagenum' => $i + 1, 'url' => $purl->out(false), 'isactive' => ($i == $page)];
}
$prevurl = clone $baseurl; $prevurl->param('page', max(0, $page - 1));
$nexturl = clone $baseurl; $nexturl->param('page', $page + 1);

$data = [
    'total_count' => $total_count, 'total_attempts' => number_format($total_attempts),
    'total_passed' => number_format($total_passed), 'filtered_count' => $filtered_count,
    'search' => $search, 'org_options' => $org_options, 'has_org_options' => !empty($org_options),
    'status_all' => ($status === 'all'), 'status_open' => ($status === 'open'), 'status_closed' => ($status === 'closed'),
    'filter_url' => (new moodle_url('/local/airpay_exams/index.php'))->out(false),
    'exams' => $rows, 'has_exams' => !empty($rows),
    'showing_from' => ($page * $perpage) + 1, 'showing_to' => min(($page + 1) * $perpage, $filtered_count),
    'has_pagination' => ($total_pages > 1), 'pagination_pages' => $pagination_pages,
    'has_prev' => ($page > 0), 'prev_url' => $prevurl->out(false),
    'has_next' => ($page < $total_pages - 1), 'next_url' => $nexturl->out(false),
    'can_manage' => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_exams/manage', $data);
echo $OUTPUT->footer();
