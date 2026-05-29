<?php
// Trainer dashboard page — standalone view for trainers.
// core_renderer.php redirects trainer-only users here.

require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/airpay_trainer/dashboard.php'));
$PAGE->set_title(get_string('pluginname', 'block_airpay_trainer'));
$PAGE->set_heading(get_string('yoursessions', 'block_airpay_trainer'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// Reuse block content logic. Legacy block_<name> classes are NOT handled by
// core_component's class autoloader (they load only via the block framework,
// e.g. block_load_class()/block_instance()), so a bare `new block_airpay_trainer()`
// fatals with "Class not found" (QA Walk 2026-05-29, T-04). Load block_base and
// the block class explicitly before instantiating — block_base must be defined
// first because the block class declaration `extends block_base`.
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once(__DIR__ . '/block_airpay_trainer.php');
$block = new block_airpay_trainer();
$block->init();
$content = $block->get_content();
echo $content->text;

echo $OUTPUT->footer();
