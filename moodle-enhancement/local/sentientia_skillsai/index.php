<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Skills Intelligence — extraction job queue (landing page).
 *
 * Lists the current actor's extraction jobs (or all jobs if they hold
 * :manage_all), each linking to its review page. Gated by the master
 * feature flag + the :extract or :review capability.
 *
 * @package local_sentientia_skillsai
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_skillsai\taxonomy_manager;

require_login();
$context = context_system::instance();

// Gate 1 — feature flag.
if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_skillsai');
}

// Gate 2 — capability (extract OR review).
if (!has_capability('local/sentientia_skillsai:extract', $context)
        && !has_capability('local/sentientia_skillsai:review', $context)) {
    require_capability('local/sentientia_skillsai:review', $context);
}

$PAGE->set_url('/local/sentientia_skillsai/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('queue_page_title', 'local_sentientia_skillsai'));
$PAGE->set_heading(get_string('queue_page_heading', 'local_sentientia_skillsai'));

$manageall = has_capability('local/sentientia_skillsai:manage_all', $context);
$jobs = taxonomy_manager::list_for_actor($USER, $manageall, 100);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('queue_page_heading', 'local_sentientia_skillsai'));

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/sentientia_skillsai/extract.php'),
        get_string('nav_extract', 'local_sentientia_skillsai'),
        ['class' => 'btn btn-primary me-2']
    ) .
    html_writer::link(
        new moodle_url('/local/sentientia_skillsai/taxonomy.php'),
        get_string('nav_taxonomy', 'local_sentientia_skillsai'),
        ['class' => 'btn btn-secondary']
    ),
    'mb-3'
);

if (empty($jobs)) {
    echo html_writer::div(get_string('queue_empty', 'local_sentientia_skillsai'),
        'alert alert-info', ['role' => 'status']);
    echo $OUTPUT->footer();
    return;
}

$table = new html_table();
$table->head = [
    get_string('col_title', 'local_sentientia_skillsai'),
    get_string('col_sourcekind', 'local_sentientia_skillsai'),
    get_string('col_status', 'local_sentientia_skillsai'),
    get_string('col_extracted', 'local_sentientia_skillsai'),
    get_string('col_actions', 'local_sentientia_skillsai'),
];
$table->attributes['class'] = 'generaltable';

foreach ($jobs as $job) {
    $reviewurl = new moodle_url('/local/sentientia_skillsai/review.php', ['jobid' => $job->id]);
    $table->data[] = [
        format_string($job->title),
        s(get_string('sourcekind_' . $job->sourcekind, 'local_sentientia_skillsai')),
        s(get_string('jobstatus_' . $job->status, 'local_sentientia_skillsai')),
        (int)$job->num_extracted,
        html_writer::link($reviewurl, get_string('action_review', 'local_sentientia_skillsai'),
            ['class' => 'btn btn-sm btn-outline-primary']),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
