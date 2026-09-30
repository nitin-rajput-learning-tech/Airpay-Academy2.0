<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: create the classroom data the QR-attendance and "Log in as" screen
 * checks need (docs/visual-evidence/2026-09-30/qr-and-loginas/README.md).
 * Refuses unless wwwroot is localhost, so it can never touch UAT or production.
 *
 * Creates (or reuses) two classrooms in tenant /1 and adds NEW sessions to them on
 * every run, so every run starts with no attendance rows (except the one seeded on
 * purpose, sessionE):
 *   - "VP Evidence QR classroom"            active; roster vp_learner1 + vp_learner177
 *       sessionA  running now (the window is open)
 *       sessionC  tomorrow (the window has not opened)
 *       sessionD  yesterday (the window has closed)
 *       sessionE  running now, with vp_learner1 already marked ABSENT by the trainer
 *       sessionF  running now, for the trainer-grid-versus-QR-scan check
 *   - "VP Evidence QR cancelled classroom"  cancelled; roster vp_learner1
 *       sessionB  running now
 * and, if missing, one suspended account, vp_suspended1 (a new test account that
 * is never logged in to; its password is random and is not kept).
 *
 * Writes a JSON file (--out=<path>, put it OUTSIDE the repo: it holds valid scan
 * tokens) with the session ids, their hourly QR tokens, an id that has no session,
 * and the user ids qr_loginas_checks.mjs needs. The tokens come from
 * session_manager::qr_token(), which signs with the per-site secret (it is created on
 * first use); the file also holds the OLD salt-free sha256 token, which must be refused.
 *
 *   php seed_qr_evidence.php --out=<file.json>        (cwd = moodle5/public)
 *   php seed_qr_evidence.php --report=<file.json>     print the attendance rows of those sessions
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}

if (empty($CFG->noemailever)) {
    // Creating an account and enrolling users can send mail on some installs.
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set.\n");
    exit(1);
}

use local_sentientia_classroom\session_manager;

global $DB;
[$options] = cli_get_params(['out' => '', 'report' => ''], []);

if ($options['report'] !== '') {
    $data = json_decode(file_get_contents($options['report']), true);
    foreach (['sessionA', 'sessionB', 'sessionC', 'sessionD', 'sessionE', 'sessionF'] as $key) {
        if (empty($data[$key]['id'])) {
            continue;
        }
        $rows = $DB->get_records_sql(
            "SELECT a.id, a.userid, u.username, a.status, a.markedby, a.notes
               FROM {local_sentientia_classroom_attendance} a
               JOIN {user} u ON u.id = a.userid
              WHERE a.sessionid = :sid ORDER BY a.id", ['sid' => $data[$key]['id']]);
        echo "{$key} (session {$data[$key]['id']}): " . count($rows) . " attendance row(s)\n";
        foreach ($rows as $r) {
            echo "  {$r->username}: status {$r->status} (1 = Present), marked by user {$r->markedby}, note '{$r->notes}'\n";
        }
    }
    exit(0);
}
if ($options['out'] === '') {
    cli_error('Pass --out=<file.json> (outside the repo) or --report=<file.json>.');
}
foreach (['local_sentientia_classroom', 'local_sentientia_classroom_sessions',
        'local_sentientia_classroom_users', 'local_sentientia_classroom_attendance'] as $table) {
    if (!$DB->get_manager()->table_exists($table)) {
        cli_error("Table {$table} is missing: local_sentientia_classroom is not installed here.");
    }
}

$user = function (string $username) use ($DB): stdClass {
    return $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', MUST_EXIST);
};
$learner = $user('vp_learner1');
$manager = $user('vp_manager1');
$learner177 = $user('vp_learner177');
$siteadmin = $user('vp_siteadmin');

// A second site admin (the profile page must not offer "Log in as" for one).
$other = 0;
foreach (explode(',', (string) $CFG->siteadmins) as $adminid) {
    $adminid = (int) $adminid;
    if ($adminid > 0 && $adminid !== (int) $siteadmin->id
            && $DB->record_exists('user', ['id' => $adminid, 'deleted' => 0, 'suspended' => 0])) {
        $other = $adminid;
        break;
    }
}
if (!$other) {
    cli_error('There is no second site admin on this box.');
}

// A suspended account in the learner's tenant.
$suspended = $DB->get_record('user', ['username' => 'vp_suspended1', 'deleted' => 0]);
if (!$suspended) {
    $id = user_create_user((object) [
        'username' => 'vp_suspended1', 'auth' => 'manual', 'confirmed' => 1, 'suspended' => 1,
        'mnethostid' => $CFG->mnet_localhost_id, 'firstname' => 'VP', 'lastname' => 'Suspended',
        'email' => 'vp_suspended1@example.com', 'password' => random_string(16) . 'Aa1-#',  // random; meets the password policy; never kept
    ]);
    $DB->set_field('user', 'open_path', $learner->open_path ?? null, ['id' => $id]);
    $suspended = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    echo "created vp_suspended1 (id {$id})\n";
}

$classroom = function (string $name, int $status) use ($DB): int {
    $id = $DB->get_field('local_sentientia_classroom', 'id', ['name' => $name]);
    if ($id) {
        return (int) $id;
    }
    return (int) $DB->insert_record('local_sentientia_classroom', (object) [
        'name' => $name, 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
        'location' => 'Room 1', 'capacity' => 20, 'status' => $status, 'visible' => 1,
        'timecreated' => time(), 'timemodified' => time(),
    ]);
};
$newsession = function (int $classroomid, string $title, int $offset = 0): int {
    $start = time() + $offset;
    return (int) session_manager::create_session($classroomid, (object) [
        'title' => $title, 'starttime' => $start, 'endtime' => $start + HOURSECS,
    ]);
};

$active = $classroom('VP Evidence QR classroom', session_manager::STATUS_ACTIVE);
$cancelled = $classroom('VP Evidence QR cancelled classroom', session_manager::STATUS_CANCELLED);
session_manager::enrol_users($active, [(int) $learner->id, (int) $learner177->id]);
session_manager::enrol_users($cancelled, [(int) $learner->id]);

$run = userdate(time(), '%d %b %H:%M');
$sa = $newsession($active, "Day 1 ({$run})");
$sb = $newsession($cancelled, "Day 1 ({$run})");
$sc = $newsession($active, "Tomorrow ({$run})", DAYSECS);
$sd = $newsession($active, "Yesterday ({$run})", -DAYSECS);
$se = $newsession($active, "Marked Absent by trainer ({$run})");
$sf = $newsession($active, "Grid vs scan ({$run})");
$missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_sentientia_classroom_sessions}') + 100000;

// The trainer (vp_manager1 stands in) marks vp_learner1 Absent on session E, the way the
// attendance grid does for a learner nobody ticked. The owner decision: a scan never changes it.
\core\session\manager::set_user($manager);
session_manager::mark_attendance($se, (int) $learner->id, session_manager::ATT_ABSENT);
\core\session\manager::set_user(get_admin());

// The same token the trainer's QR page shows (per-site secret, current hour).
$token = fn(int $sid): string => session_manager::qr_token($sid, time());
// What the page accepted before 2026-09-30 on an install with no $CFG->passwordsaltmain:
// a plain sha256 anybody could compute. It must now be refused.
$saltfree = fn(int $sid): string => hash('sha256', $sid . '|' . date('Y-m-d-H') . '|');

$out = [
    'sessionA' => ['id' => $sa, 'token' => $token($sa), 'saltfreetoken' => $saltfree($sa)],
    'sessionB' => ['id' => $sb, 'token' => $token($sb)],
    'sessionC' => ['id' => $sc, 'token' => $token($sc)],
    'sessionD' => ['id' => $sd, 'token' => $token($sd)],
    'sessionE' => ['id' => $se, 'token' => $token($se)],
    'sessionF' => ['id' => $sf, 'token' => $token($sf)],
    'missing' => ['id' => $missing, 'token' => $token($missing)],
    'expiredtoken' => hash('sha256', 'not-a-current-token'),
    'users' => [
        'learner1' => (int) $learner->id, 'manager1' => (int) $manager->id,
        'learner177' => (int) $learner177->id, 'suspended' => (int) $suspended->id,
        'siteadmin' => (int) $siteadmin->id, 'siteadmin2' => $other,
    ],
];
file_put_contents($options['out'], json_encode($out, JSON_PRETTY_PRINT));
echo "seeded: classroom {$active} (sessions A {$sa}, C {$sc}, D {$sd}, E {$se}, F {$sf}), "
    . "cancelled classroom {$cancelled} (session {$sb}), missing session id {$missing}. "
    . "Wrote {$options['out']}\n";
