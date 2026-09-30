<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: create the classroom data the QR-attendance and "Log in as" screen
 * checks need (docs/visual-evidence/2026-09-30/qr-and-loginas/README.md).
 * Refuses unless wwwroot is localhost, so it can never touch UAT or production.
 *
 * Creates (or reuses) two classrooms in tenant /1 and adds a NEW session to each on
 * every run, so every run starts with no attendance rows:
 *   - "VP Evidence QR classroom"            active; roster vp_learner1 + vp_learner177
 *   - "VP Evidence QR cancelled classroom"  cancelled; roster vp_learner1
 * and, if missing, one suspended account, vp_suspended1 (a new test account that
 * is never logged in to; its password is random and is not kept).
 *
 * Writes a JSON file (--out=<path>, put it OUTSIDE the repo: it holds valid scan
 * tokens) with the session ids, their hourly QR tokens, an id that has no session,
 * and the user ids qr_loginas_checks.mjs needs.
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
    foreach (['sessionA', 'sessionB'] as $key) {
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
$newsession = function (int $classroomid, string $title): int {
    $start = time();
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
$missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_sentientia_classroom_sessions}') + 100000;

$salt = $CFG->passwordsaltmain ?? '';
$token = fn(int $sid): string => hash('sha256', $sid . '|' . date('Y-m-d-H') . '|' . $salt);

$out = [
    'sessionA' => ['id' => $sa, 'token' => $token($sa)],
    'sessionB' => ['id' => $sb, 'token' => $token($sb)],
    'missing' => ['id' => $missing, 'token' => $token($missing)],
    'expiredtoken' => hash('sha256', 'not-a-current-token'),
    'users' => [
        'learner1' => (int) $learner->id, 'manager1' => (int) $manager->id,
        'learner177' => (int) $learner177->id, 'suspended' => (int) $suspended->id,
        'siteadmin' => (int) $siteadmin->id, 'siteadmin2' => $other,
    ],
];
file_put_contents($options['out'], json_encode($out, JSON_PRETTY_PRINT));
echo "seeded: classroom {$active} (session {$sa}), cancelled classroom {$cancelled} (session {$sb}), "
    . "missing session id {$missing}. Wrote {$options['out']}\n";
