<?php
// Bootstrap fixture for the full audit. Sets known passwords for
// representative test users at each role tier so the audit harness
// can authenticate as them. Run once before full_audit.sh.
//
// Local-XAMPP only. Never run on production.

define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');
require_once($CFG->libdir . '/moodlelib.php');

global $DB;

if ($CFG->wwwroot !== 'http://localhost:8080/moodle') {
    die("Refusing to run on non-local instance: $CFG->wwwroot\n");
}

$test_password = 'Airpay@Test2026!';

// ── Resolve representative users ──
$users = [
    'siteadmin' => $DB->get_record('user', ['username' => 'academy@airpay.co.in']),
    'manager'   => $DB->get_record('user', ['username' => 'kunal@airpay.co.in']),
    'learner'   => $DB->get_record_sql(
        "SELECT u.* FROM {user} u
          WHERE u.deleted = 0 AND u.suspended = 0
            AND u.id > 100
            AND u.open_supervisorid > 0          -- has a supervisor (=> learner)
            AND NOT EXISTS (SELECT 1 FROM {user} t WHERE t.open_supervisorid = u.id)
       ORDER BY u.lastaccess DESC
          LIMIT 1"),
];

echo "── Audit bootstrap — set test password '$test_password' on 3 users ──\n";
foreach ($users as $role => $u) {
    if (!$u) {
        echo "  $role: NO USER FOUND — skipped\n";
        continue;
    }
    $u->password = hash_internal_user_password($test_password);
    $DB->set_field('user', 'password', $u->password, ['id' => $u->id]);
    echo "  $role:  id={$u->id}  username={$u->username}  path={$u->open_path}\n";
}

echo "\nDone. Run audit:  bash moodle-enhancement/audit/full_audit.sh\n";
