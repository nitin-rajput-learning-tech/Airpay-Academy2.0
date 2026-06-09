<?php
// Trainer dashboard page — standalone view for trainers.
// core_renderer.php redirects trainer-only users here.

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/sentientia_trainer/dashboard.php'));
$PAGE->set_title(get_string('pluginname', 'block_sentientia_trainer'));
$PAGE->set_heading(get_string('yoursessions', 'block_sentientia_trainer'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// Reuse block content logic.
$block = new block_sentientia_trainer();
$block->init();
$content = $block->get_content();
echo $content->text;

echo $OUTPUT->footer();
