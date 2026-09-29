<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031 decision 1 for UAT (delegated by Nitin, 2026-09-26): the core
 * authority the tenant-admin role must not have at system context.
 *
 * UAT role 9 ('administrator', manager archetype) is assigned to tenant
 * admins at SYSTEM context. It inherits every manager-archetype core
 * capability, and core pages know nothing about tenants, so a tenant admin
 * could, from core pages alone:
 *   - edit any role definition, including their own (give it
 *     local/sentientia_platform:crosstenant) - moodle/role:manage;
 *   - override any capability in every tenant's categories and courses -
 *     moodle/role:override;
 *   - create, edit, delete and log in as any account in any tenant -
 *     moodle/user:create, :update, :delete, :loginas;
 *   - take over any account through core /user/edit.php (change its email,
 *     then reset its password) - moodle/user:editprofile;
 *   - create and overwrite any account with a CSV - moodle/site:uploadusers.
 * No Sentientia in-tenant flow needs any of these: the Sentientia user
 * pages do those jobs through their own capabilities and the tenant check.
 * The evidence is in
 * moodle-enhancement/docs/operations/ROLE9-CORE-CAPS-2026-09-26.md.
 *
 * PART 1 prohibits those capabilities for the role at system context.
 *
 * PART 2 closes the ways around part 1 through the role's allow matrices.
 *   - Assign: moodle/role:assign stays (the course enrol modal and the
 *     Sentientia role and API code call get_assignable_roles()), but core
 *     /admin/roles/assign.php lets its holder give, to ANY user in ANY context,
 *     every role the allow-assign matrix lists for their role. With the
 *     manager-archetype defaults that includes 'manager' and the tenant-admin
 *     role itself: a tenant admin could make a second account a site-wide
 *     manager, which holds everything part 1 removed. So the role's
 *     allow-assign rows to the roles a scoped caller may never give -
 *     \local_sentientia_courses\course_manager::scoped_forbidden_role_ids():
 *     the manager and coursecreator archetypes, the tenant-admin role, core
 *     non-course roles and any site-level role - are removed. The enrol modal
 *     already subtracts exactly that set for a tenant admin, so it offers the
 *     same roles as before.
 *   - Switch: a core "Switch role to..." inside a course evaluates ONLY the
 *     switched-to role there, so the role's PROHIBITs do not apply. Switching
 *     to a manager-type role would bring "Log in as" and overrides back in that
 *     course. Allow-switch rows to the same forbidden set are removed, except
 *     guest / authenticated user / frontpage, which only lower rights. The
 *     manager-archetype default (editingteacher, teacher, student, guest) loses
 *     nothing.
 *
 * moodle/role:safeoverride cannot reach system-context definitions and is not
 * a manager-archetype default, so it is prohibited only when the role
 * carries an explicit ALLOW for it.
 *
 * Site admins are not affected: they bypass capability checks and the
 * allow matrices, whatever roles they hold, so they keep every page and
 * "Log in as".
 *
 * Every prior value (permissions and removed allow rows) is saved to a JSON
 * file in $CFG->dataroot BEFORE anything changes, and --revert restores
 * exactly that.
 *
 * USAGE (on the UAT box):
 *   sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --dry-run
 *   sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --apply
 *   sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --revert
 *
 * Options: --role=<shortname> (default: administrator, UAT's id-9 tenant-admin role).
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle5.2/public/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

[$options] = cli_get_params(
    ['i-am-uat' => false, 'dry-run' => false, 'apply' => false, 'revert' => false,
     'role' => 'administrator'], []);
if (empty($options['i-am-uat'])) {
    cli_error('Refusing to run without --i-am-uat.');
}
if (strpos($CFG->wwwroot, 'academy2.airpay.ninja') === false) {
    cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the UAT instance.");
}
$modes = array_filter([$options['dry-run'] ? 'dry-run' : null, $options['apply'] ? 'apply' : null,
    $options['revert'] ? 'revert' : null]);
if (count($modes) !== 1) {
    cli_error('Pass exactly one of --dry-run, --apply, --revert.');
}
$mode = reset($modes);

// Capability => [rule, why]. 'prohibit' always; 'prohibit-if-allow' only
// when the role carries an explicit ALLOW at system context.
$caps = [
    'moodle/role:manage' => ['prohibit',
        'edits every role definition and the allow matrices (/admin/roles/define.php, allow.php)'],
    'moodle/role:override' => ['prohibit',
        "overrides any capability for any role in every tenant's categories and courses"],
    'moodle/role:safeoverride' => ['prohibit-if-allow',
        'no system-context reach, not a manager default; only an explicit ALLOW is prohibited'],
    'moodle/user:create' => ['prohibit',
        'core Add a new user (/user/editadvanced.php?id=-1): accounts outside any tenant'],
    'moodle/user:update' => ['prohibit',
        'core /user/editadvanced.php, Browse users, Bulk actions: edits any account'],
    'moodle/user:delete' => ['prohibit',
        'core Browse users, Bulk actions: deletes any account'],
    'moodle/user:loginas' => ['prohibit',
        'core /course/loginas.php: becomes any account in any tenant'],
    'moodle/user:editprofile' => ['prohibit',
        'core /user/edit.php?id=<anyone>: edit any account, change its email'],
    'moodle/site:uploadusers' => ['prohibit',
        'core Upload users: creates and overwrites any account from a CSV'],
];

// The allow matrices PART 2 trims: kind => [table, field, core setter, event class].
$matrices = [
    'assign' => ['role_allow_assign', 'allowassign', 'core_role_set_assign_allowed',
        '\core\event\role_allow_assign_updated'],
    'switch' => ['role_allow_switch', 'allowswitch', 'core_role_set_switch_allowed',
        '\core\event\role_allow_switch_updated'],
];

/**
 * A permission value as a word.
 *
 * @param int|null $permission
 * @return string
 */
function adr031_role9_perm_label(?int $permission): string {
    return match ($permission) {
        null => 'inherit',
        CAP_ALLOW => 'ALLOW',
        CAP_PREVENT => 'PREVENT',
        CAP_PROHIBIT => 'PROHIBIT',
        default => (string) $permission,
    };
}

/**
 * One table row.
 *
 * @param string $what
 * @param string $now
 * @param string $action
 * @param string $why
 */
function adr031_role9_row(string $what, string $now, string $action, string $why): void {
    cli_writeln(sprintf('%-30s %-9s %-19s %s', $what, $now, $action, $why));
}

/**
 * Tell the event log an allow pair changed, as admin/roles/allow.php does.
 *
 * @param string $eventclass
 * @param int $fromroleid
 * @param int $targetroleid
 * @param bool $allow
 */
function adr031_role9_allow_event(string $eventclass, int $fromroleid, int $targetroleid, bool $allow): void {
    if (class_exists($eventclass)) {
        $eventclass::create(['context' => context_system::instance(),
            'objectid' => $fromroleid, 'other' => ['targetroleid' => $targetroleid, 'allow' => $allow]])->trigger();
    }
}

global $DB;
$role = $DB->get_record('role', ['shortname' => $options['role']], '*', MUST_EXIST);
// Guard: a role every user holds would prohibit these for everybody.
$everybody = array_filter([(int) ($CFG->defaultuserroleid ?? 0), (int) ($CFG->guestroleid ?? 0),
    (int) ($CFG->notloggedinroleid ?? 0), (int) ($CFG->defaultfrontpageroleid ?? 0)]);
if (in_array((int) $role->id, $everybody, true)) {
    cli_error("Refusing: role {$role->shortname} is a site-wide default role, held by every user.");
}
$sys = context_system::instance();
$statefile = $CFG->dataroot . '/adr031_role9_core_caps_' . $role->shortname . '.json';
$shortnames = $DB->get_records_menu('role', null, '', 'id, shortname');

// Who is affected, so the operator sees it before and after.
$holders = array_map('intval', $DB->get_fieldset_sql(
    'SELECT DISTINCT userid FROM {role_assignments} WHERE roleid = :roleid', ['roleid' => $role->id]));
$siteadmins = array_filter(array_map('intval', explode(',', (string) ($CFG->siteadmins ?? ''))));
$adminholders = array_values(array_intersect($holders, $siteadmins));
cli_writeln(sprintf('Role %s (id %d, archetype %s): held by %d user(s); %d of them site admin(s) [%s], '
    . 'not affected (site admins bypass capability checks).',
    $role->shortname, $role->id, (string) $role->archetype === '' ? 'none' : $role->archetype, count($holders),
    count($adminholders), implode(', ', $adminholders)));
cli_writeln('');

if ($mode === 'revert') {
    if (!is_readable($statefile)) {
        cli_error("No saved state at {$statefile}; nothing to revert.");
    }
    $state = json_decode(file_get_contents($statefile), true);
    if (!is_array($state) || !isset($state['roleid'], $state['caps']) || !is_array($state['caps'])) {
        cli_error("Unreadable state in {$statefile}; nothing changed.");
    }
    if ((int) $state['roleid'] !== (int) $role->id) {
        cli_error("State in {$statefile} is for role id {$state['roleid']}, not {$role->id}; nothing changed.");
    }
    adr031_role9_row('capability', 'now', 'revert to', '');
    foreach ($state['caps'] as $cap => $previous) {
        $current = $DB->get_field('role_capabilities', 'permission',
            ['roleid' => $role->id, 'contextid' => $sys->id, 'capability' => $cap]);
        $now = adr031_role9_perm_label($current === false ? null : (int) $current);
        if ($previous === null) {
            unassign_capability($cap, $role->id, $sys->id);
            adr031_role9_row($cap, $now, 'inherit (removed)', '');
        } else {
            assign_capability($cap, (int) $previous, $role->id, $sys->id, true);
            adr031_role9_row($cap, $now, adr031_role9_perm_label((int) $previous), '');
        }
    }
    $restored = 0;
    foreach ($matrices as $kind => [$table, $field, $setter, $eventclass]) {
        foreach (($state['allow' . $kind . '_removed'] ?? []) as $targetid) {
            $targetid = (int) $targetid;
            $label = "may {$kind}" . ($kind === 'switch' ? ' to ' : ' ') . ($shortnames[$targetid] ?? "role {$targetid}");
            if (!isset($shortnames[$targetid])) {
                adr031_role9_row($label, '-', 'skip (role gone)', '');
            } else if ($DB->record_exists($table, ['roleid' => $role->id, $field => $targetid])) {
                adr031_role9_row($label, 'yes', 'yes (already)', '');
            } else {
                $setter($role->id, $targetid);
                adr031_role9_allow_event($eventclass, (int) $role->id, $targetid, true);
                adr031_role9_row($label, 'no', 'yes (restored)', '');
                $restored++;
            }
        }
    }
    $sys->mark_dirty();
    rename($statefile, $statefile . '.reverted-' . date('Ymd-His'));
    cli_writeln('');
    cli_writeln('Reverted ' . count($state['caps']) . " capabilities and {$restored} allow row(s) for role {$role->shortname}.");
    exit(0);
}

if ($mode === 'apply' && is_readable($statefile)) {
    cli_error("Already applied (state at {$statefile}); revert it first.");
}

// Plan first; nothing changes until the prior values are safely on disk.
cli_writeln('PART 1 - capabilities at system context');
$plan = [];
$already = 0;
adr031_role9_row('capability', 'now', 'action', 'why');
foreach ($caps as $cap => [$rule, $why]) {
    if (!get_capability_info($cap)) {
        adr031_role9_row($cap, '-', 'skip (not on site)', $why);
        continue;
    }
    $current = $DB->get_field('role_capabilities', 'permission',
        ['roleid' => $role->id, 'contextid' => $sys->id, 'capability' => $cap]);
    $current = ($current === false) ? null : (int) $current;
    if ($rule === 'prohibit-if-allow' && $current !== CAP_ALLOW) {
        adr031_role9_row($cap, adr031_role9_perm_label($current), 'leave', $why);
        continue;
    }
    if ($current === CAP_PROHIBIT) {
        $already++;
        adr031_role9_row($cap, 'PROHIBIT', 'leave (already)', $why);
        continue;
    }
    $plan[$cap] = $current;
    adr031_role9_row($cap, adr031_role9_perm_label($current), 'PROHIBIT', $why);
}
adr031_role9_row('moodle/role:assign', '(kept)', 'leave', 'course enrol modal + Sentientia roles/API use get_assignable_roles()');
cli_writeln('');

cli_writeln('PART 2 - roles it may give (/admin/roles/assign.php) or switch to (course "Switch role to"), no tenant check');
$trim = ['assign' => [], 'switch' => []];
if (!class_exists('\local_sentientia_courses\course_manager')
        || !method_exists('\local_sentientia_courses\course_manager', 'scoped_forbidden_role_ids')) {
    cli_writeln('WARNING: local_sentientia_courses (ADR-031) is not deployed here, so the forbidden set is unknown;'
        . ' part 2 skipped. Nothing in part 2 changes.');
} else {
    $forbidden = array_map('intval', \local_sentientia_courses\course_manager::scoped_forbidden_role_ids());
    $forbidden[] = (int) $role->id;
    // Switching into one of these only lowers rights, so they stay switchable.
    $lowering = [];
    foreach ($DB->get_records('role', null, '', 'id, shortname, archetype') as $r) {
        if (in_array((string) $r->archetype, ['guest', 'user', 'frontpage'], true)
                || in_array((string) $r->shortname, ['guest', 'user', 'frontpage'], true)) {
            $lowering[] = (int) $r->id;
        }
    }
    adr031_role9_row('allow row', 'now', 'action', '');
    foreach ($matrices as $kind => [$table, $field]) {
        $targets = array_map('intval',
            $DB->get_fieldset_select($table, $field, 'roleid = :roleid', ['roleid' => $role->id]));
        sort($targets);
        foreach ($targets as $targetid) {
            $label = "may {$kind}" . ($kind === 'switch' ? ' to ' : ' ') . ($shortnames[$targetid] ?? "role {$targetid}");
            if ($kind === 'switch' && in_array($targetid, $lowering, true)) {
                adr031_role9_row($label, 'yes', 'leave', 'switching to it only lowers rights');
            } else if (in_array($targetid, $forbidden, true)) {
                $trim[$kind][] = $targetid;
                adr031_role9_row($label, 'yes', 'remove', 'site-level role: a scoped caller may never get it this way');
            } else {
                adr031_role9_row($label, 'yes', 'leave', 'course-level role');
            }
        }
        if (!$targets) {
            adr031_role9_row("may {$kind}", '-', 'leave', '(no rows)');
        }
    }
}
cli_writeln('');
$trimcount = count($trim['assign']) + count($trim['switch']);

if ($mode === 'dry-run') {
    cli_writeln('DRY RUN: ' . count($plan) . " capabilities would be prohibited ({$already} already are) and "
        . "{$trimcount} allow row(s) removed for role {$role->shortname}. Nothing changed.");
    exit(0);
}

if (!$plan && $trimcount === 0) {
    cli_writeln("Nothing to change for role {$role->shortname} ({$already} capabilities already PROHIBIT); "
        . 'no state written.');
    exit(0);
}

$state = ['role' => $role->shortname, 'roleid' => (int) $role->id, 'applied' => date('c'),
    'caps' => $plan, 'allowassign_removed' => $trim['assign'], 'allowswitch_removed' => $trim['switch']];
if (file_put_contents($statefile, json_encode($state, JSON_PRETTY_PRINT)) === false) {
    cli_error("Could not write {$statefile}; nothing changed.");
}
foreach (array_keys($plan) as $cap) {
    assign_capability($cap, CAP_PROHIBIT, $role->id, $sys->id, true);
}
foreach ($matrices as $kind => [$table, $field, , $eventclass]) {
    foreach ($trim[$kind] as $targetid) {
        $DB->delete_records($table, ['roleid' => $role->id, $field => $targetid]);
        adr031_role9_allow_event($eventclass, (int) $role->id, $targetid, false);
    }
}
$sys->mark_dirty();
cli_writeln('Applied PROHIBIT to ' . count($plan) . " capabilities and removed {$trimcount}"
    . " allow row(s) for role {$role->shortname}; prior values saved to {$statefile}.");
