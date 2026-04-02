<?php
require_once('../../config.php');

echo $OUTPUT->header();

$programcompletion = new \local_program\local\completion();
$programcompletion->program_course_completion_task();

echo $OUTPUT->footer();
die;