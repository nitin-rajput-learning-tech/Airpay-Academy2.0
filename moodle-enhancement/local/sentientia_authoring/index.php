<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Authoring Studio — landing page: lists the actor's recent course drafts and
 * links into the studio, the review queue, and the template manager.
 *
 * @package local_sentientia_authoring
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_authoring\draft_manager;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_authoring');
}
require_capability('local/sentientia_authoring:generate', $context);

$PAGE->set_url('/local/sentientia_authoring/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('index_page_title', 'local_sentientia_authoring'));
$PAGE->set_heading(get_string('index_page_heading', 'local_sentientia_authoring'));

$manageall = has_capability('local/sentientia_authoring:manage_all', $context);
$drafts = draft_manager::list_for_actor($USER, $manageall);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('index_page_heading', 'local_sentientia_authoring'));

echo html_writer::div(
    html_writer::link(new moodle_url('/local/sentientia_authoring/studio.php'),
        get_string('nav_studio', 'local_sentientia_authoring'), ['class' => 'btn btn-primary me-2'])
    . html_writer::link(new moodle_url('/local/sentientia_authoring/templates.php'),
        get_string('nav_templates', 'local_sentientia_authoring'), ['class' => 'btn btn-outline-secondary']),
    'mb-3');

if (empty($drafts)) {
    echo html_writer::div(get_string('index_empty', 'local_sentientia_authoring'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('index_col_title', 'local_sentientia_authoring'),
        get_string('index_col_status', 'local_sentientia_authoring'),
        get_string('index_col_cards', 'local_sentientia_authoring'),
        get_string('index_col_questions', 'local_sentientia_authoring'),
        get_string('index_col_created', 'local_sentientia_authoring'),
        get_string('index_col_actions', 'local_sentientia_authoring'),
    ];
    foreach ($drafts as $d) {
        $link = html_writer::link(
            new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => (int) $d->id]),
            get_string('index_review_link', 'local_sentientia_authoring'),
            ['class' => 'btn btn-sm btn-outline-primary']);
        $table->data[] = [
            format_string($d->title),
            html_writer::tag('span', s($d->status), ['class' => 'badge bg-secondary']),
            (int) $d->num_cards,
            (int) $d->num_questions,
            userdate((int) $d->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            $link,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
