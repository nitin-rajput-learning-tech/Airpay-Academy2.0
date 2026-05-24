<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
global $DB;

echo "=== ADMIN USERS (siteadmins) ===\n";
$adminids = explode(',', $CFG->siteadmins);
foreach ($adminids as $aid) {
    $u = $DB->get_record('user', ['id' => $aid]);
    if ($u) {
        echo sprintf("  id=%d username=%s email=%s name=%s %s lastaccess=%s\n",
            $u->id, $u->username, $u->email,
            $u->firstname, $u->lastname,
            $u->lastaccess ? date('Y-m-d H:i', $u->lastaccess) : 'never');
    }
}

echo "\n=== USERS WITH MANAGER ROLE ===\n";
$mgrs = $DB->get_records_sql(
    "SELECT u.id, u.username, u.email, u.firstname, u.lastname, u.lastaccess, u.suspended
       FROM {user} u
       JOIN {role_assignments} ra ON ra.userid = u.id
       JOIN {role} r ON r.id = ra.roleid
      WHERE r.shortname IN ('manager', 'editingteacher', 'coursecreator')
        AND u.deleted = 0
   ORDER BY u.lastaccess DESC
      LIMIT 15");
foreach ($mgrs as $u) {
    echo sprintf("  id=%d username=%s email=%s name=%s %s suspended=%d lastaccess=%s\n",
        $u->id, $u->username, $u->email,
        $u->firstname, $u->lastname, $u->suspended,
        $u->lastaccess ? date('Y-m-d H:i', $u->lastaccess) : 'never');
}

echo "\n=== TEST/DEMO USERS (by username pattern) ===\n";
$testusers = $DB->get_records_sql(
    "SELECT id, username, email, firstname, lastname, lastaccess
       FROM {user}
      WHERE deleted = 0
        AND (LOWER(username) LIKE '%test%' OR LOWER(username) LIKE '%demo%'
             OR LOWER(username) LIKE '%admin%' OR LOWER(username) = 'academy'
             OR LOWER(email) LIKE '%@airpay.co.in')
   ORDER BY lastaccess DESC
      LIMIT 20");
foreach ($testusers as $u) {
    echo sprintf("  id=%d username=%s email=%s name=%s %s lastaccess=%s\n",
        $u->id, $u->username, $u->email,
        $u->firstname, $u->lastname,
        $u->lastaccess ? date('Y-m-d H:i', $u->lastaccess) : 'never');
}

echo "\n=== MOST RECENTLY LOGGED-IN USERS (top 10) ===\n";
$recent = $DB->get_records_sql(
    "SELECT id, username, email, firstname, lastname, lastaccess
       FROM {user}
      WHERE deleted = 0 AND lastaccess > 0 AND id > 1
   ORDER BY lastaccess DESC
      LIMIT 10");
foreach ($recent as $u) {
    echo sprintf("  id=%d username=%s email=%s name=%s %s lastaccess=%s\n",
        $u->id, $u->username, $u->email,
        $u->firstname, $u->lastname, date('Y-m-d H:i', $u->lastaccess));
}
