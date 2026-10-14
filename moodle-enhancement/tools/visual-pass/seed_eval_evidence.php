<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: seed one test learner with an evaluation history, for the EV-18 evidence of
 * /local/sentientia_evaluation/my_evaluations.php (docs/visual-evidence/2026-10-14/ev18/README.md).
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set, so it can never touch UAT/production or mail
 * anyone. Never deletes anything.
 *
 * The learner is a NEW local test account, vp_eval77: tenant /77 (the one tenant the 2026-10-14 flags are switched on
 * for) with the 'employee' role at system context. vp_public77 holds no system role and so lacks
 * local/sentientia_evaluation:respond, which the page requires; no existing persona is changed. The password is
 * generated here and merged into .personas.local.json (gitignored), never printed.
 *
 * Forms and history, through the plugin's own evaluation_manager (each recognised again by name, so a re-run adds
 * nothing). All are native forms of tenant /77 with test text only:
 *   1 named reaction survey, answered                    -> "Responded", no note
 *   2 anonymous workplace pulse, answered                -> "Responded" + "your answers are not linked to you"
 *   3 named manager feedback form, assigned, due in 7 d  -> "Waiting for you"
 *   4 named follow-up form, assigned, due 3 days ago     -> "Closed without a response" (the assignment is set to
 *     expired directly: nothing but the evaluation task expires one, and it is not run here)
 * An "imported" badge needs a row the BizLMS importer writes; none is faked here.
 *
 *   php seed_eval_evidence.php     (run with cwd = moodle5/public)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/user/lib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}
if (empty($CFG->noemailever)) {
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set.\n");
    exit(1);
}

use local_sentientia_evaluation\evaluation_manager as em;

const EV_TENANT = 77;

global $DB;
$sys = context_system::instance();

// 1. The learner.
$username = 'vp_eval77';
$credsfile = __DIR__ . '/.personas.local.json';
$creds = is_file($credsfile) ? (json_decode(file_get_contents($credsfile), true) ?: []) : [];
$user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$user) {
    $id = user_create_user((object) [
        'username' => $username, 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        'firstname' => 'VP', 'lastname' => 'Learner (evaluations) /77', 'email' => $username . '@visualpass.localhost',
        'lang' => 'en', 'timezone' => '99',
    ], false, false);
    $user = $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    echo "created user {$username} (id {$user->id})\n";
}
if (empty($creds[$username]['password'])) {
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#%*+-='];
    $pw = '';
    foreach ($sets as $set) {
        $pw .= $set[random_int(0, strlen($set) - 1)];
    }
    $all = implode('', $sets);
    for ($i = 0; $i < 14; $i++) {
        $pw .= $all[random_int(0, strlen($all) - 1)];
    }
    $pw = str_shuffle($pw);
    update_internal_user_password($user, $pw);
    $creds[$username] = ['id' => (int) $user->id, 'password' => $pw, 'label' => 'Learner (evaluations) /77', 'path' => '/' . EV_TENANT];
    file_put_contents($credsfile, json_encode($creds, JSON_PRETTY_PRINT));
    echo "credentials for {$username} written to " . basename($credsfile) . " (gitignored, not printed)\n";
}
$DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
unset_user_preference('auth_forcepasswordchange', $user);
if (isset($DB->get_columns('user')['open_path'])) {
    $DB->set_field('user', 'open_path', '/' . EV_TENANT, ['id' => $user->id]);
}
$employee = (int) $DB->get_field('role', 'id', ['shortname' => 'employee'], MUST_EXIST);
role_assign($employee, $user->id, $sys->id);
accesslib_clear_all_caches(true);
if (!has_capability('local/sentientia_evaluation:respond', $sys, $user->id)) {
    fwrite(STDERR, "vp_eval77 lacks local/sentientia_evaluation:respond - the page would refuse.\n");
    exit(1);
}

// 2. Forms. Recognised by name; questions only added to a form this script created.
$form = function (string $name, int $anonymous, array $questions) use ($DB): int {
    $existing = $DB->get_field('local_sentientia_evaluation', 'id', ['name' => $name], IGNORE_MULTIPLE);
    if ($existing) {
        return (int) $existing;
    }
    $id = em::create((object) ['name' => $name, 'description' => 'Local evidence form (test text only).',
        'kirkpatrick_level' => 1, 'trigger_event' => 'manual', 'costcenterid' => EV_TENANT,
        'status' => em::STATUS_ACTIVE, 'anonymous' => $anonymous]);
    foreach ($questions as [$type, $text]) {
        em::create_question((object) ['evaluationid' => $id, 'questiontype' => $type, 'questiontext' => $text, 'required' => 1]);
    }
    return $id;
};
$answers = function (int $evaluationid, array $byorder): array {
    $out = [];
    foreach (array_values(em::get_questions($evaluationid)) as $i => $q) {
        $out[$q->id] = $byorder[$i];
    }
    return $out;
};

$f1 = $form('VP Course reaction survey: Compliance Essentials', 0, [
    ['rating', 'The course content was relevant to my role.'],
    ['yesno', 'Would you recommend this course to a colleague?'],
    ['text', 'What one thing would improve the course?'],
]);
$f2 = $form('VP Anonymous workplace pulse', 1, [
    ['rating', 'I feel able to raise a concern at work.'],
    ['text', 'Anything you want the team to know?'],
]);
$f3 = $form('VP Manager feedback form', 0, [
    ['rating', 'My manager gives me clear feedback.'],
    ['text', 'What should your manager do more of?'],
]);
$f4 = $form('VP Training follow-up (30 days after)', 0, [
    ['rating', 'I have used what I learned in the course.'],
    ['text', 'What got in the way?'],
]);

// 3. History. An assignment first (as the trigger or an admin would make it), then the answer.
$day = DAYSECS;
foreach ([$f1, $f2] as $fid) {
    em::ensure_assignment($fid, (int) $user->id, 'manual', 0, null, time() + 7 * $day);
}
em::ensure_assignment($f3, (int) $user->id, 'manual', 0, null, time() + 7 * $day);
$a4 = em::ensure_assignment($f4, (int) $user->id, 'manual', 0, null, time() - 3 * $day);
if (!$DB->record_exists('local_sentientia_evaluation_responses', ['evaluationid' => $f1, 'userid' => $user->id])) {
    em::submit_response($f1, (int) $user->id, $answers($f1, [4, 'yes', 'More worked examples from payments.']));
    echo "answered form {$f1}\n";
}
// An anonymous answer is stored with user id 0, so the 'already answered' test is the assignment's status.
if ($DB->get_field('local_sentientia_evaluation_assign', 'status', ['evaluationid' => $f2, 'userid' => $user->id]) !== 'responded') {
    em::submit_response($f2, (int) $user->id, $answers($f2, [5, 'Thank you for asking.']));
    echo "answered anonymous form {$f2}\n";
}
$DB->set_field('local_sentientia_evaluation_assign', 'status', 'expired', ['id' => $a4]);

echo "history of {$username}:\n";
foreach (\local_sentientia_evaluation\learner_history::for_user((int) $user->id) as $e) {
    echo "  {$e->name} | {$e->status} | unlinked=" . (int) $e->unlinked . " | imported=" . (int) $e->imported . "\n";
}
