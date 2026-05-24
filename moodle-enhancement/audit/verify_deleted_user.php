<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$u = $DB->get_record('user', ['id' => 3375]);
if ($u) {
    echo "USER RECORD STILL EXISTS (soft deleted)\n";
    echo "  id: {$u->id}\n";
    echo "  username: {$u->username}\n";
    echo "  email: {$u->email}\n";
    echo "  deleted: {$u->deleted}\n";
    echo "  suspended: {$u->suspended}\n";
    echo "  open_employeeid: {$u->open_employeeid}\n";
    echo "  open_designation: {$u->open_designation}\n";
} else {
    echo "USER record gone from DB entirely (hard delete)\n";
}
