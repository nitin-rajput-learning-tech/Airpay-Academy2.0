<?php
// Provisioning helper — assign the dedicated "Sentientia Author" role to SME
// content authors at SYSTEM context, then verify the AI author/SME capabilities
// resolve at the page gate (CONTEXT_SYSTEM). This is the per-deployment last
// mile flagged in PERSONA-FEATURE-CHECK-2026-06-17.md: the role is shipped by
// local_sentientia_authoring/db/upgrade.php (step 2026061701), but WHO is an
// author is a deployment decision, so assignment lives here, not in upgrade.
//
// Idempotent: role_assign() no-ops if the user already holds the role at the
// system context. Safe to re-run.
//
// Usage (from any cwd):
//   php assign_author_role.php                       # dry-run: just AUDIT the role + its caps
//   php assign_author_role.php asif.ansari           # assign one username, then verify
//   php assign_author_role.php asif.ansari binay.upadhyay   # assign several
//   UNASSIGN=1 php assign_author_role.php asif.ansari        # remove the assignment instead
//
// LOCAL/STAGING provisioning tool. On production, the same usernames map to the
// real SME accounts; run it once per deployment as part of the rollout gate.

define('CLI_SCRIPT', true);
require('C:/xampp/htdocs/moodle5/config.php');
require_once($CFG->libdir . '/accesslib.php');

global $DB;

$shortname = 'sentientiaauthor';
$role = $DB->get_record('role', ['shortname' => $shortname]);
if (!$role) {
    fwrite(STDERR, "FAIL  role '{$shortname}' not found. Run the plugin upgrade first "
        . "(local_sentientia_authoring >= 2026061701).\n");
    exit(1);
}

$syscontext = context_system::instance();
$authorcaps = [
    'local/sentientia_authoring:generate',
    'local/sentientia_authoring:review',
    'local/sentientia_authoring:managetemplates',
    'local/sentientia_skillsai:extract',
    'local/sentientia_skillsai:review',
];

// --- AUDIT: what the role grants, and where it is assignable ----------------
echo "ROLE  id={$role->id}  shortname={$role->shortname}  name=\"{$role->name}\"\n";
$levels = $DB->get_fieldset_select('role_context_levels', 'contextlevel', 'roleid = ?', [$role->id]);
sort($levels);
$levelnames = array_map(static fn($l) => $l == CONTEXT_SYSTEM ? "SYSTEM({$l})" : (string)$l, $levels);
echo "      assignable at context levels: " . (implode(', ', $levelnames) ?: '(none!)') . "\n";
echo "      capabilities granted at system context:\n";
foreach ($authorcaps as $cap) {
    $rc = $DB->get_record('role_capabilities',
        ['roleid' => $role->id, 'capability' => $cap, 'contextid' => $syscontext->id]);
    $perm = $rc ? (int)$rc->permission : null;
    $label = $perm === CAP_ALLOW ? 'ALLOW' : ($perm === null ? 'NOT SET' : "perm={$perm}");
    $exists = $DB->record_exists('capabilities', ['name' => $cap]) ? '' : '  (cap not installed)';
    printf("        %-44s %s%s\n", $cap, $label, $exists);
}

// --- ASSIGN / UNASSIGN + VERIFY per username --------------------------------
$usernames = array_slice($argv, 1);
$unassign = (bool)getenv('UNASSIGN');
if (!$usernames) {
    echo "\n(dry-run — no usernames given. Pass usernames to assign + verify.)\n";
    exit(0);
}

echo "\n" . ($unassign ? 'UNASSIGNING' : 'ASSIGNING') . " at SYSTEM context:\n";
foreach ($usernames as $un) {
    $u = $DB->get_record('user', ['username' => $un, 'deleted' => 0]);
    if (!$u) { echo "  MISS  {$un}  (no such user)\n"; continue; }

    if ($unassign) {
        role_unassign($role->id, $u->id, $syscontext->id);
        echo "  -     {$un}  id={$u->id}  role removed at system\n";
        continue;
    }

    role_assign($role->id, $u->id, $syscontext->id);

    // Verify the actual page gate: has_capability at system context for this user.
    $allyes = true;
    $results = [];
    foreach ($authorcaps as $cap) {
        if (!$DB->record_exists('capabilities', ['name' => $cap])) { continue; }
        $has = has_capability($cap, $syscontext, $u->id);
        $allyes = $allyes && $has;
        $results[] = ($has ? '+' : '-') . substr($cap, strrpos($cap, ':') + 1);
    }
    $verdict = $allyes ? 'OK  all author caps resolve YES' : 'WARN some caps NO';
    echo "  +     {$un}  id={$u->id}  assigned  [{$verdict}]  " . implode(' ', $results) . "\n";
}

echo "\ndone.\n";
