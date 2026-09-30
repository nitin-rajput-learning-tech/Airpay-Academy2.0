<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: throwaway accounts and classroom data for the QR-attendance re-check of the
 * final review (docs/visual-evidence/2026-09-30/qr-and-loginas/README.md, screens 18, 19, 21-24).
 * Refuses unless wwwroot is localhost, so it can never touch UAT or production.
 *
 * It exists so this pass does not touch the vp_* personas a Playwright persona pass may be
 * logged in as: every account here is a NEW vpqr_* account, and the classroom is its own.
 *
 *   vpqr_admin1    tenant admin /1 (role 'administrator': holds :view and :attendance)
 *   vpqr_learner1  learner /1, on the roster
 *   vpqr_learner2  learner /1, on the roster
 *
 * Passwords are generated here and written only to --creds (default .personas.local.json next to
 * this script, gitignored); they are never printed. The data file (--out, keep it OUTSIDE the
 * repo: it holds valid scan tokens) lists the sessions, which are NEW on every run so each starts
 * with no attendance rows, except sessionA where the trainer has marked vpqr_learner1 Absent:
 *   sessionA  running now, vpqr_learner1 already marked ABSENT by the trainer (Hindi "already marked")
 *   sessionF  running now, no rows: the trainer's grid Save versus a scan that lands after the grid loaded
 *   sessionG  running now, no rows: the trainer marks learner1 only; learner2 is never touched and can still scan
 *
 *   php seed_qr_recheck.php --out=<file.json> [--creds=<file.json>]     (cwd = moodle5/public)
 *   php seed_qr_recheck.php --report=<file.json>                        print the attendance rows
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
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set.\n");
    exit(1);
}

use local_sentientia_classroom\session_manager;

global $DB;
[$options] = cli_get_params(['out' => '', 'report' => '', 'creds' => __DIR__ . '/.personas.local.json'], []);

if ($options['report'] !== '') {
    $data = json_decode(file_get_contents($options['report']), true);
    foreach (['sessionA', 'sessionF', 'sessionG'] as $key) {
        $rows = $DB->get_records_sql(
            "SELECT a.id, u.username, a.status, a.markedby, a.notes
               FROM {local_sentientia_classroom_attendance} a
               JOIN {user} u ON u.id = a.userid
              WHERE a.sessionid = :sid ORDER BY a.id", ['sid' => $data[$key]['id']]);
        echo "{$key} (session {$data[$key]['id']}): " . count($rows) . " attendance row(s)\n";
        foreach ($rows as $r) {
            echo "  {$r->username}: status {$r->status} (1 = Present, 0 = Absent), marked by user {$r->markedby}, note '{$r->notes}'\n";
        }
    }
    exit(0);
}
if ($options['out'] === '') {
    cli_error('Pass --out=<file.json> (outside the repo) or --report=<file.json>.');
}

$sys = context_system::instance();
$roleid = fn(string $short): int => (int) $DB->get_field('role', 'id', ['shortname' => $short], MUST_EXIST);
$gen = function (): string {
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#%*+-='];
    $pw = '';
    foreach ($sets as $set) {
        $pw .= $set[random_int(0, strlen($set) - 1)];
    }
    $all = implode('', $sets);
    for ($i = 0; $i < 14; $i++) {
        $pw .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($pw);
};

$creds = is_file($options['creds']) ? (json_decode(file_get_contents($options['creds']), true) ?: []) : [];
$accounts = [
    'vpqr_admin1'   => ['role' => 'administrator', 'label' => 'QR check tenant admin /1'],
    'vpqr_learner1' => ['role' => 'employee',      'label' => 'QR check learner 1 /1'],
    'vpqr_learner2' => ['role' => 'employee',      'label' => 'QR check learner 2 /1'],
];
$ids = [];
foreach ($accounts as $username => $a) {
    $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$user) {
        $id = user_create_user((object) [
            'username' => $username, 'auth' => 'manual', 'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id, 'firstname' => 'VPQR', 'lastname' => $a['label'],
            'email' => $username . '@visualpass.localhost', 'lang' => 'en', 'timezone' => '99',
        ], false, false);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    }
    $pw = $gen();
    update_internal_user_password($user, $pw);
    $DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
    unset_user_preference('auth_forcepasswordchange', $user);
    if (isset($DB->get_columns('user')['open_path'])) {
        $DB->set_field('user', 'open_path', '/1', ['id' => $user->id]);
    }
    role_assign($roleid($a['role']), $user->id, $sys->id);
    $ids[$username] = (int) $user->id;
    $creds[$username] = ['id' => (int) $user->id, 'password' => $pw, 'label' => $a['label'], 'path' => '/1'];
}
accesslib_clear_all_caches(true);
file_put_contents($options['creds'], json_encode($creds, JSON_PRETTY_PRINT));

$classroomname = 'VPQR recheck classroom';
$classroomid = (int) $DB->get_field('local_sentientia_classroom', 'id', ['name' => $classroomname]);
if (!$classroomid) {
    $classroomid = (int) $DB->insert_record('local_sentientia_classroom', (object) [
        'name' => $classroomname, 'description' => '', 'costcenterid' => 0, 'open_path' => '/1',
        'location' => 'Room 1', 'capacity' => 20, 'status' => session_manager::STATUS_ACTIVE, 'visible' => 1,
        'timecreated' => time(), 'timemodified' => time(),
    ]);
}
session_manager::enrol_users($classroomid, [$ids['vpqr_learner1'], $ids['vpqr_learner2']]);

$run = userdate(time(), '%d %b %H:%M');
$newsession = fn(string $title): int => (int) session_manager::create_session($classroomid, (object) [
    'title' => $title . " ({$run})", 'starttime' => time(), 'endtime' => time() + HOURSECS,
]);
$sa = $newsession('Marked Absent by trainer');
$sf = $newsession('Grid vs scan');
$sg = $newsession('Untouched learner');

// The trainer (the tenant admin) marks learner1 Absent on session A, on purpose.
\core\session\manager::set_user($DB->get_record('user', ['id' => $ids['vpqr_admin1']], '*', MUST_EXIST));
session_manager::mark_attendance($sa, $ids['vpqr_learner1'], session_manager::ATT_ABSENT);
\core\session\manager::set_user(get_admin());

$token = fn(int $sid): string => session_manager::qr_token($sid, time());
$out = [
    'classroomid' => $classroomid,
    'sessionA' => ['id' => $sa, 'token' => $token($sa)],
    'sessionF' => ['id' => $sf, 'token' => $token($sf)],
    'sessionG' => ['id' => $sg, 'token' => $token($sg)],
    'users' => $ids,
];
file_put_contents($options['out'], json_encode($out, JSON_PRETTY_PRINT));
echo "seeded: classroom {$classroomid}, sessions A {$sa}, F {$sf}, G {$sg}; accounts " . implode(', ', array_keys($ids))
    . ". Data written to {$options['out']}; credentials written to " . basename($options['creds']) . " (not printed).\n";
