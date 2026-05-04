<?php
// Airpay Online Exams — admin exam management.
//
// Exams are wrappers around Moodle quiz activities. The wrapper adds
// tenant scoping, custom passing grades, and dashboard reporting on
// top of standard Moodle quizzes.
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

// ── Filter params ────────────────────────────────────────────────────
$orgid   = optional_param('orgid', 0, PARAM_INT);
$status  = optional_param('status', 'all', PARAM_ALPHA);
$search  = optional_param('search', '', PARAM_TEXT);

// ── Permissions ──────────────────────────────────────────────────────
$can_manage = is_siteadmin() || has_capability('local/airpay_exams:manage', $context);

// ── Build query against wrapper table ────────────────────────────────
$dbman = $DB->get_manager();
$rows = [];
$total = 0;
$active = 0;

if ($dbman->table_exists('local_airpay_exams')) {
    // Build WHERE.
    $whereclauses = ['1=1'];
    $params = [];

    if ($orgid > 0) {
        $org = $DB->get_record('local_airpay_org', ['id' => $orgid]);
        if ($org) {
            $whereclauses[] = "e.open_path LIKE :orgpath";
            $params['orgpath'] = $org->path . '%';
        }
    }

    if ($status === 'active') {
        $whereclauses[] = "e.status = 1";
    } else if ($status === 'inactive') {
        $whereclauses[] = "e.status = 0";
    }

    if (!empty($search)) {
        $s = '%' . $DB->sql_like_escape($search) . '%';
        $whereclauses[] = "(" .
            $DB->sql_like('e.name', ':s1', false) . " OR " .
            $DB->sql_like('q.name', ':s2', false) . " OR " .
            $DB->sql_like('c.fullname', ':s3', false) .
        ")";
        $params['s1'] = $s;
        $params['s2'] = $s;
        $params['s3'] = $s;
    }

    $where = implode(' AND ', $whereclauses);

    $total  = $DB->count_records('local_airpay_exams');
    $active = $DB->count_records('local_airpay_exams', ['status' => 1]);

    // Fetch with joins for course + quiz info + attempt counts.
    $sql = "SELECT e.*, q.name AS quizname, q.timelimit AS quiztimelimit, q.timeopen, q.timeclose,
                   q.course AS courseid, c.fullname AS coursename, c.shortname AS coursecode,
                   (SELECT COUNT(qa.id) FROM {quiz_attempts} qa WHERE qa.quiz = e.quizid) AS attempts,
                   (SELECT COUNT(qa.id) FROM {quiz_attempts} qa WHERE qa.quiz = e.quizid AND qa.state = 'finished') AS finished,
                   (SELECT ROUND(AVG(qa.sumgrades), 1) FROM {quiz_attempts} qa
                     WHERE qa.quiz = e.quizid AND qa.state = 'finished') AS avg_score
              FROM {local_airpay_exams} e
              JOIN {quiz} q ON q.id = e.quizid
              JOIN {course} c ON c.id = q.course
             WHERE {$where}
          ORDER BY e.timemodified DESC, e.id DESC";

    $exams = $DB->get_records_sql($sql, $params, 0, 100);

    // Org lookup.
    $orgs = $DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC');
    $org_lookup = [];
    foreach ($orgs as $o) {
        $org_lookup[$o->path] = format_string($o->fullname);
    }

    foreach ($exams as $e) {
        $parts = explode('/', trim($e->open_path ?? '', '/'));
        $orgname = $org_lookup['/' . ($parts[0] ?? '')] ?? '—';

        // Use exam-level overrides, fall back to quiz settings.
        $duration = $e->duration ?? $e->quiztimelimit;
        $duration_label = $duration > 0 ? round($duration / 60) . ' min' : 'No limit';

        $is_active = ((int) $e->status === 1);

        $rows[] = [
            'id'           => $e->id,
            'name'         => format_string($e->name),
            'quizname'     => format_string($e->quizname),
            'coursename'   => format_string($e->coursename),
            'orgname'      => $orgname,
            'attempts'     => (int) $e->attempts,
            'finished'     => (int) $e->finished,
            'avg_score'    => $e->avg_score ? number_format($e->avg_score, 1) : '—',
            'duration'     => $duration_label,
            'passinggrade' => $e->passinggrade ? round($e->passinggrade) . '%' : '—',
            'is_active'    => $is_active,
            'statuslabel'  => $is_active ? 'Active' : 'Inactive',
            'statuscss'    => $is_active ? 'badge-success' : 'badge-secondary',
            'viewurl'      => (new moodle_url('/mod/quiz/view.php', ['q' => $e->quizid]))->out(false),
            'reporturl'    => (new moodle_url('/mod/quiz/report.php', ['id' => $DB->get_field('course_modules', 'id',
                                ['instance' => $e->quizid, 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'])])]))->out(false),
            'can_manage'   => $can_manage,
        ];
    }
}

// Total quizzes available (for "register more" hint when list is empty).
$total_quizzes = $DB->count_records_sql(
    "SELECT COUNT(q.id) FROM {quiz} q JOIN {course} c ON c.id = q.course WHERE c.id > 1 AND c.visible = 1");

// Org filter options.
$org_options = [];
foreach ($DB->get_records('local_airpay_org', ['depth' => 1, 'visible' => 1], 'fullname ASC') as $o) {
    $org_options[] = ['id' => $o->id, 'name' => format_string($o->fullname), 'selected' => ($o->id == $orgid)];
}

$data = [
    'total'         => $total,
    'active'        => $active,
    'inactive'      => $total - $active,
    'total_quizzes' => $total_quizzes,
    'unregistered'  => max(0, $total_quizzes - $total),

    'search'        => $search,
    'org_options'   => $org_options,
    'has_org_options' => !empty($org_options),
    'status_all'    => ($status === 'all'),
    'status_active' => ($status === 'active'),
    'status_inactive' => ($status === 'inactive'),
    'filter_url'    => (new moodle_url('/local/airpay_exams/index.php'))->out(false),

    'exams'         => $rows,
    'has_exams'     => !empty($rows),
    'can_manage'    => $can_manage,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_exams/manage', $data);
echo $OUTPUT->footer();
