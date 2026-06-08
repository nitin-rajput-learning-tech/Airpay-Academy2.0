<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Exam detail view — standalone page with tabs:
 *   Overview  : description, duration, passing grade, status, linked quiz
 *   Attempts  : individual attempts pulled from quiz_attempts
 *   Roster    : users enrolled in wrapping course
 *   Analytics : pass rate, avg score, time, attempt distribution
 *
 * Closes Phase 3 B.3 deferred item.
 *
 * @package local_sentientia_exams
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id  = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'attempts', 'roster', 'analytics'], true)) {
    $tab = 'overview';
}

$exam = $DB->get_record('local_sentientia_exams', ['id' => $id], '*', MUST_EXIST);
$quiz = $exam->quizid
    ? $DB->get_record('quiz', ['id' => $exam->quizid])
    : null;
$course = ($quiz && $quiz->course)
    ? $DB->get_record('course', ['id' => $quiz->course])
    : null;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_exams/view.php',
    ['id' => $id, 'tab' => $tab]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Exam: ' . format_string($exam->name));
$PAGE->set_heading('Exam: ' . format_string($exam->name));
require_capability('local/sentientia_exams:view', $ctx);

$can_edit  = has_capability('local/sentientia_exams:update', $ctx);
$can_enrol = has_capability('local/sentientia_exams:enrol', $ctx);

// Counts.
$count_attempts = 0;
$count_enrolled = 0;
$count_passed = 0;
if ($quiz) {
    $count_attempts = (int) $DB->count_records('quiz_attempts',
        ['quiz' => $quiz->id, 'state' => 'finished']);
    if ($course) {
        $count_enrolled = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :cid",
            ['cid' => $course->id]);
    }
    // Passed = attempts where sumgrades / grade >= passinggrade%.
    $pass_threshold = (float) ($exam->passinggrade ?: 50);
    $count_passed = (int) $DB->count_records_sql(
        "SELECT COUNT(DISTINCT qa.userid)
           FROM {quiz_attempts} qa
          WHERE qa.quiz = :qid
            AND qa.state = 'finished'
            AND qa.sumgrades IS NOT NULL
            AND (qa.sumgrades * 100.0 / NULLIF((SELECT SUM(grade) FROM {quiz_grades} WHERE quiz = qa.quiz), 0)) >= :pg",
        ['qid' => $quiz->id, 'pg' => $pass_threshold]);
}

// Per-tab data.
$tab_data = [];
switch ($tab) {
    case 'attempts':
        if ($quiz) {
            $rows = $DB->get_records_sql(
                "SELECT qa.id, qa.userid, qa.attempt, qa.state, qa.sumgrades,
                        qa.timestart, qa.timefinish,
                        u.firstname, u.lastname, u.email
                   FROM {quiz_attempts} qa
                   JOIN {user} u ON u.id = qa.userid
                  WHERE qa.quiz = :qid AND qa.preview = 0
                  ORDER BY qa.timefinish DESC, qa.timestart DESC
                  LIMIT 200",
                ['qid' => $quiz->id]);
            $tab_data['attempts'] = array_values(array_map(function($r) {
                $duration = ($r->timefinish && $r->timestart)
                    ? gmdate('H:i:s', $r->timefinish - $r->timestart)
                    : '—';
                return [
                    'attempt'   => (int) $r->attempt,
                    'fullname'  => trim($r->firstname . ' ' . $r->lastname),
                    'email'     => (string) $r->email,
                    'state'     => $r->state,
                    'score'     => $r->sumgrades !== null ? number_format($r->sumgrades, 2) : '—',
                    'started'   => $r->timestart ? userdate($r->timestart, '%d %b %H:%M') : '—',
                    'finished'  => $r->timefinish ? userdate($r->timefinish, '%d %b %H:%M') : '—',
                    'duration'  => $duration,
                ];
            }, $rows));
            $tab_data['has_attempts'] = count($rows) > 0;
        }
        break;

    case 'roster':
        if ($course) {
            // Reuse the courses list_course_enrolments via direct query.
            $rows = $DB->get_records_sql(
                "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                        u.open_employeeid, u.lastaccess,
                        ue.status AS enrol_status
                   FROM {user} u
                   JOIN {user_enrolments} ue ON ue.userid = u.id
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = :cid
                    AND u.deleted = 0
                  ORDER BY u.lastname ASC, u.firstname ASC
                  LIMIT 200",
                ['cid' => $course->id]);
            $tab_data['roster'] = array_values(array_map(fn($r) => [
                'userid'      => (int) $r->id,
                'fullname'    => trim($r->firstname . ' ' . $r->lastname),
                'email'       => (string) $r->email,
                'employee_id' => (string) ($r->open_employeeid ?? ''),
                'status'      => $r->enrol_status == 0 ? 'Active' : 'Suspended',
                'last_access' => $r->lastaccess ? userdate($r->lastaccess, '%d %b %Y') : 'Never',
            ], $rows));
            $tab_data['has_roster'] = count($rows) > 0;
            $tab_data['enrolled_users_url'] = (new moodle_url(
                '/local/sentientia_courses/enrolledusers.php',
                ['id' => $course->id]))->out(false);
        }
        break;

    case 'analytics':
        if ($quiz) {
            // Avg score, pass rate, avg time.
            $stats = $DB->get_record_sql(
                "SELECT
                    AVG(qa.sumgrades) AS avg_score,
                    MIN(qa.sumgrades) AS min_score,
                    MAX(qa.sumgrades) AS max_score,
                    AVG(qa.timefinish - qa.timestart) AS avg_time_sec,
                    COUNT(*) AS total_attempts
                 FROM {quiz_attempts} qa
                 WHERE qa.quiz = :qid AND qa.state = 'finished'
                   AND qa.sumgrades IS NOT NULL", ['qid' => $quiz->id]);

            $max_grade = (float) ($DB->get_field('quiz', 'grade',
                ['id' => $quiz->id]) ?: 100);
            $pass_pct = $count_attempts > 0
                ? round(100 * $count_passed / $count_attempts, 1)
                : 0;

            $tab_data['avg_score']  = $stats && $stats->avg_score !== null
                ? number_format($stats->avg_score, 2) : '—';
            $tab_data['min_score']  = $stats && $stats->min_score !== null
                ? number_format($stats->min_score, 2) : '—';
            $tab_data['max_score']  = $stats && $stats->max_score !== null
                ? number_format($stats->max_score, 2) : '—';
            $tab_data['max_grade']  = number_format($max_grade, 2);
            $tab_data['avg_time']   = $stats && $stats->avg_time_sec
                ? gmdate('H:i:s', (int) $stats->avg_time_sec)
                : '—';
            $tab_data['pass_pct']   = $pass_pct;
            $tab_data['pass_threshold'] = (float) ($exam->passinggrade ?: 50);
            $tab_data['count_passed']  = $count_passed;
            $tab_data['count_failed']  = max(0, $count_attempts - $count_passed);
            $tab_data['count_attempts'] = $count_attempts;
            $tab_data['has_analytics']  = $count_attempts > 0;

            // Phase B0+ — stat_card-compatible KPI tiles for the
            // analytics tab. Legacy flat fields above are preserved.
            $tab_data['kpi_tiles'] = [
                [
                    'label' => 'Total Attempts',
                    'value' => number_format($count_attempts),
                    'icon'  => 'pencil-square-o',
                    'color' => 'primary',
                ],
                [
                    'label' => 'Pass Rate',
                    'value' => $pass_pct . '%',
                    'icon'  => 'check-circle',
                    // Semantic by pass-rate band.
                    'color' => $pass_pct >= 80 ? 'success'
                             : ($pass_pct >= 50 ? 'warning' : 'danger'),
                ],
                [
                    'label' => 'Avg Score',
                    'value' => $tab_data['avg_score'] . ' / ' . $tab_data['max_grade'],
                    'icon'  => 'star',
                    'color' => 'accent',
                ],
                [
                    'label' => 'Avg Time',
                    'value' => $tab_data['avg_time'],
                    'icon'  => 'clock-o',
                    'color' => 'info',
                ],
            ];
            $tab_data['has_kpi_tiles'] = !empty($tab_data['kpi_tiles']);
        }
        break;
}

$status_label = match ((int) $exam->status) {
    1       => 'Published',
    0       => 'Draft',
    default => 'Archived',
};

$data = [
    'examid'         => (int) $exam->id,
    'name'           => format_string($exam->name),
    'description'    => '',
    'duration'       => (int) ($exam->duration ?: 0),
    'passinggrade'   => (float) ($exam->passinggrade ?: 0),
    'status_label'   => $status_label,
    'has_quiz'       => !empty($quiz),
    'quiz_name'      => $quiz ? format_string($quiz->name) : '',
    'quiz_url'       => $quiz ? (new \moodle_url('/mod/quiz/view.php',
        ['q' => $quiz->id]))->out(false) : '',
    'has_course'     => !empty($course),
    'course_name'    => $course ? format_string($course->fullname) : '',
    'course_url'     => $course ? (new \moodle_url('/course/view.php',
        ['id' => $course->id]))->out(false) : '',

    'count_attempts' => $count_attempts,
    'count_enrolled' => $count_enrolled,
    'count_passed'   => $count_passed,

    'tab_overview_active'   => $tab === 'overview',
    'tab_attempts_active'   => $tab === 'attempts',
    'tab_roster_active'     => $tab === 'roster',
    'tab_analytics_active'  => $tab === 'analytics',

    'tab_overview_url'    => (new moodle_url('/local/sentientia_exams/view.php', ['id' => $id, 'tab' => 'overview']))->out(false),
    'tab_attempts_url'    => (new moodle_url('/local/sentientia_exams/view.php', ['id' => $id, 'tab' => 'attempts']))->out(false),
    'tab_roster_url'      => (new moodle_url('/local/sentientia_exams/view.php', ['id' => $id, 'tab' => 'roster']))->out(false),
    'tab_analytics_url'   => (new moodle_url('/local/sentientia_exams/view.php', ['id' => $id, 'tab' => 'analytics']))->out(false),

    'can_edit'  => $can_edit,
    'can_enrol' => $can_enrol,
    'back_url'  => (new moodle_url('/local/sentientia_exams/index.php'))->out(false),
];
$data = array_merge($data, $tab_data);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_exams/view', $data);
echo $OUTPUT->footer();
