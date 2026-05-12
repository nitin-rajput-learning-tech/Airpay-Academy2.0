<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * AI bridge demo — Phase 6 F.6.
 *
 * Site admin can test the core_ai → airpay_assistant integration:
 *   - course summarisation
 *   - quiz-question generation
 *   - text translation
 *
 * Provider is configured at /admin/settings.php?section=aiproviders.
 *
 * @package local_airpay_assistant
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE, $DB;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_assistant/ai_demo.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('AI bridge demo');
$PAGE->set_heading('AI bridge demo (Phase 6 F.6)');
require_capability('moodle/site:config', $ctx);  // siteadmin only

$action = optional_param('action', '', PARAM_ALPHA);
$result = null;

if ($action === 'summarise') {
    $cid = required_param('courseid', PARAM_INT);
    $result = ['title' => "Course summary for #$cid",
               'output' => \local_airpay_assistant\core_ai_bridge::summarise_course($cid)];
} else if ($action === 'quiz') {
    $topic = required_param('topic', PARAM_TEXT);
    $q = \local_airpay_assistant\core_ai_bridge::generate_quiz_question($topic);
    $result = ['title' => "Quiz Q on '$topic'",
               'output' => "Q: $q->question\n\nA: "
                 . implode("\n   ", $q->options)
                 . "\n\nCorrect: option {$q->correct}\nExplanation: $q->explanation"];
} else if ($action === 'translate') {
    $text = required_param('text', PARAM_TEXT);
    $lang = required_param('lang', PARAM_TEXT);
    $result = ['title' => "Translation to $lang",
               'output' => \local_airpay_assistant\core_ai_bridge::translate_text($text, $lang)];
}

$available = \local_airpay_assistant\core_ai_bridge::is_available();
$courses = $DB->get_records_select('course', 'id > 1', null,
    'fullname ASC', 'id, shortname, fullname', 0, 20);

$course_opts = [];
foreach ($courses as $c) {
    $course_opts[] = [
        'id' => $c->id,
        'label' => format_string($c->shortname) . ' — ' . format_string($c->fullname),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_assistant/ai_demo', [
    'available'    => $available,
    'courses'      => $course_opts,
    'has_result'   => $result !== null,
    'result_title' => $result['title'] ?? '',
    'result_text'  => $result['output'] ?? '',
    'config_url'   => (new moodle_url('/admin/settings.php',
        ['section' => 'aiproviders']))->out(false),
]);
echo $OUTPUT->footer();
