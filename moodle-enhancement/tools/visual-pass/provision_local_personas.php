<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: create (or refresh) the test personas the Playwright visual pass
 * logs in as. Refuses to run unless $CFG->wwwroot is a localhost URL, so it can
 * never touch UAT or production.
 *
 * Every persona is a NEW account named vp_* - no real (imported) user is ever
 * changed. Passwords are generated here and written only to
 * .personas.local.json next to this script (gitignored); they are never
 * printed.
 *
 *   php provision_local_personas.php          (run with cwd = moodle5/public)
 *
 * Personas mirror the ADR-031 screen-check list: a site admin, tenant admins
 * of /1 and /177 (role 'administrator', manager archetype, system context as
 * on UAT), a /1 line manager with two direct reports, learners in /1 and /177
 * (role 'employee' at system context) and a /1 trainer/author ('trainer').
 *
 * Added 2026-09-30 for the persona-journey harness (persona_journeys.mjs), which
 * replaces the manual UAT persona testing of UAT-VALIDATION-PLAN-2026-09-03.md
 * (Phase 1 table). Three more personas, each created only if its role exists
 * locally (a missing role is reported and the persona skipped, never invented):
 *   vp_compliance1   /1   Compliance officer. No 'complianceofficer' role exists
 *                         locally; the compliance report's own definition of the
 *                         BizLMS compliance officer is "role id 9 assigned at a
 *                         course-category context" (viewer_scope / permission),
 *                         so the persona holds role 9 ('administrator') at the
 *                         Airpay category ONLY - no system-level role.
 *   vp_courseauthor1 /1   Course author: role 'sentientiaauthor' at system
 *                         context (how the one real author on the box holds it).
 *   vp_public77      /77  Public / external learner: no system role, just the
 *                         authenticated-user default a signup gets.
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/user/lib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}

global $DB;
$sys = context_system::instance();
$roleid = fn(string $short): int => (int) $DB->get_field('role', 'id', ['shortname' => $short], MUST_EXIST);
$roleexists = fn(string $short): bool => $DB->record_exists('role', ['shortname' => $short]);

// The course category that carries a tenant root: the top-level category whose
// idnumber is the tenant's (AirPay for /1). Falls back to the lowest non-default
// top-level category so a re-imported clone still resolves something.
$tenantcategory = function (string $tenantroot) use ($DB): ?int {
    $byroot = ['/1' => 'AirPay', '/77' => 'external', '/177' => 'ZEEA01'];
    if (isset($byroot[$tenantroot])) {
        $id = $DB->get_field('course_categories', 'id', ['idnumber' => $byroot[$tenantroot], 'parent' => 0]);
        if ($id) {
            return (int) $id;
        }
    }
    $id = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories} WHERE parent = 0 AND id > 1');
    return $id ? (int) $id : null;
};

$personas = [
    'vp_siteadmin'  => ['path' => null,    'roles' => [],                 'siteadmin' => true,  'label' => 'Site admin'],
    'vp_admin1'     => ['path' => '/1',    'roles' => ['administrator'],  'siteadmin' => false, 'label' => 'Tenant admin /1'],
    'vp_admin177'   => ['path' => '/177',  'roles' => ['administrator'],  'siteadmin' => false, 'label' => 'Tenant admin /177'],
    'vp_manager1'   => ['path' => '/1',    'roles' => ['employee'],       'siteadmin' => false, 'label' => 'Line manager /1'],
    'vp_learner1'   => ['path' => '/1',    'roles' => ['employee'],       'siteadmin' => false, 'label' => 'Learner /1',
                        'supervisor' => 'vp_manager1'],
    'vp_report1b'   => ['path' => '/1',    'roles' => ['employee'],       'siteadmin' => false, 'label' => 'Second report /1',
                        'supervisor' => 'vp_manager1'],
    'vp_learner177' => ['path' => '/177',  'roles' => ['employee'],       'siteadmin' => false, 'label' => 'Learner /177'],
    'vp_author1'    => ['path' => '/1',    'roles' => ['trainer'],        'siteadmin' => false, 'label' => 'Trainer / author /1'],
    // Added 2026-09-30 (persona-journey harness).
    'vp_compliance1'   => ['path' => '/1',  'roles' => [], 'siteadmin' => false, 'label' => 'Compliance officer /1',
                           'catroles' => [['role' => 'administrator', 'tenantroot' => '/1']]],
    'vp_courseauthor1' => ['path' => '/1',  'roles' => ['sentientiaauthor'], 'siteadmin' => false, 'label' => 'Course author /1'],
    'vp_public77'      => ['path' => '/77', 'roles' => [], 'siteadmin' => false, 'label' => 'Public learner /77'],
];

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

$out = [];
$ids = [];
$skipped = [];
foreach ($personas as $username => $p) {
    $needed = array_merge($p['roles'], array_column($p['catroles'] ?? [], 'role'));
    $missing = array_filter($needed, fn($r) => !$roleexists($r));
    if ($missing) {
        $skipped[$username] = 'role ' . implode(',', $missing) . ' does not exist locally';
        unset($personas[$username]);
        continue;
    }
    $pw = $gen();
    $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$user) {
        $new = (object) [
            'username' => $username, 'auth' => 'manual', 'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id, 'firstname' => 'VP',
            'lastname' => $p['label'], 'email' => $username . '@visualpass.localhost',
            'lang' => 'en', 'timezone' => '99',
        ];
        $id = user_create_user($new, false, false);
        $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    }
    update_internal_user_password($user, $pw);
    $DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
    unset_user_preference('auth_forcepasswordchange', $user);
    if (isset($DB->get_columns('user')['open_path'])) {
        $DB->set_field('user', 'open_path', $p['path'], ['id' => $user->id]);
    }
    foreach ($p['roles'] as $short) {
        role_assign($roleid($short), $user->id, $sys->id);
    }
    // Roles held at a course-category context only (the BizLMS compliance officer
    // shape). role_assign() is idempotent for an identical assignment.
    foreach ($p['catroles'] ?? [] as $cr) {
        $catid = $tenantcategory($cr['tenantroot']);
        if ($catid) {
            role_assign($roleid($cr['role']), $user->id, context_coursecat::instance($catid)->id);
        } else {
            $skipped[$username . ' (category role)'] = 'no category found for ' . $cr['tenantroot'];
        }
    }
    $ids[$username] = (int) $user->id;
    $out[$username] = ['id' => (int) $user->id, 'password' => $pw, 'label' => $p['label'], 'path' => $p['path']];
}

foreach ($personas as $username => $p) {
    if (!empty($p['supervisor']) && isset($DB->get_columns('user')['open_supervisorid'])) {
        $DB->set_field('user', 'open_supervisorid', $ids[$p['supervisor']], ['id' => $ids[$username]]);
    }
}

// Site admin: add the persona to $CFG->siteadmins (local DB only).
$admins = array_filter(array_map('intval', explode(',', (string) $CFG->siteadmins)));
if (!in_array($ids['vp_siteadmin'], $admins, true)) {
    $admins[] = $ids['vp_siteadmin'];
    set_config('siteadmins', implode(',', $admins));
}
accesslib_clear_all_caches(true);

$file = __DIR__ . '/.personas.local.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT));
echo 'Provisioned ' . count($out) . " local personas (ids " . implode(',', $ids) . "); credentials written to "
    . basename($file) . " (gitignored, not printed).\n";
foreach ($skipped as $who => $why) {
    echo "SKIPPED {$who}: {$why}\n";
}
