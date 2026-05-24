<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$c = $DB->get_record('course', ['shortname' => 'CLDTEST01']);
if ($c) {
    echo "COURSE CREATED!\n";
    echo "  id: {$c->id}\n";
    echo "  fullname: {$c->fullname}\n";
    echo "  shortname: {$c->shortname}\n";
    echo "  idnumber: {$c->idnumber}\n";
    echo "  category: {$c->category}\n";
    echo "  format: {$c->format}\n";
    echo "  visible: {$c->visible}\n";
    echo "  timecreated: " . date('Y-m-d H:i:s', $c->timecreated) . "\n";

    // Cleanup so test repeatable
    require_once($CFG->dirroot . '/course/lib.php');
    delete_course($c, false);
    echo "  [cleanup] course deleted\n";
} else {
    echo "Course NOT found — create may have failed\n";
}
