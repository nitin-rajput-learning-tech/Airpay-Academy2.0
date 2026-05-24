<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

$u = $DB->get_record('user', ['username' => 'claude.test']);
if ($u) {
    echo "USER CREATED!\n";
    echo "  id: {$u->id}\n";
    echo "  username: {$u->username}\n";
    echo "  email: {$u->email}\n";
    echo "  name: {$u->firstname} {$u->lastname}\n";
    echo "  suspended: {$u->suspended}\n";
    echo "  deleted: {$u->deleted}\n";
    echo "  timecreated: " . date('Y-m-d H:i:s', $u->timecreated) . "\n";
} else {
    echo "USER NOT FOUND — create may have failed\n";
}
