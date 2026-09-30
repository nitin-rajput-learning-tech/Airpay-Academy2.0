<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031 decision 2 for UAT (delegated by Nitin, 2026-09-26): the platform
 * role that carries cross-tenant authority.
 *
 * ADR-031 makes a caller cross-tenant only if they are a site admin or hold
 * local/sentientia_platform:crosstenant at system context. That capability has
 * no archetype default, so nobody holds it until a role is made for it. This
 * script makes that role and nothing more:
 *
 *   shortname 'sentientiaplatform', name 'Sentientia platform administrator',
 *   no archetype, assignable only at system context, with ONLY
 *   local/sentientia_platform:crosstenant ALLOW, assigned to NOBODY.
 *
 * Who gets it is Nitin's decision; assign it at system context from
 * /admin/roles/assign.php?contextid=1 as a site admin. Until then nobody but
 * the site admins crosses tenants, which is the safe default.
 *
 * It also makes sure the tenant-admin role cannot assign it (no
 * role_allow_assign row from that role to it). That only holds while the
 * tenant-admin role lacks moodle/role:manage (which edits the allow matrix and
 * every role definition), so the script reports that too:
 * tools/uat/adr031_role9_core_caps.php prohibits it. It also reports, read-only,
 * any OTHER role that ALLOWs the capability (every holder of such a role is
 * cross-tenant), every user other than a site admin who holds
 * moodle/role:manage at system context through any role (each could grant
 * themselves the capability), and what the tenant-admin role may still assign
 * through core.
 *
 * Editing role definitions through Sentientia (local/sentientia_roles:manage,
 * no default grant since ADR-031) stays with the site admins unless Nitin
 * grants that capability to the platform role; this script never does.
 *
 * Idempotent: a second --apply changes nothing. It never removes capabilities,
 * assignments or allow rows it did not make, except the tenant-admin role's
 * allow-assign row to this role; anything unexpected is reported as a WARNING
 * and the exit code is 2.
 *
 * USAGE (on the UAT box):
 *   sudo -u www-data php adr031_crosstenant_role.php --i-am-uat --dry-run
 *   sudo -u www-data php adr031_crosstenant_role.php --i-am-uat --apply
 *
 * On the migration target (any box that is not UAT) name it instead of
 * --i-am-uat; the two are mutually exclusive, and the script refuses unless
 * the config's $CFG->wwwroot equals --target exactly:
 *   sudo -u www-data php adr031_crosstenant_role.php --target=https://<wwwroot> \
 *       --config=/absolute/path/to/config.php --dry-run
 *
 * Options: --tenant-admin-role=<shortname> (default: administrator, UAT's id-9 role).
 */

// Which box is this for? Exactly one of --i-am-uat, or --target + --config.
// cli_get_params() needs Moodle, so these are read from $argv before config.php
// is loaded. (Same block in the four adr031_* UAT scripts.)
$adr031uat = false;
$adr031target = null;
$adr031config = null;
foreach (array_slice($argv, 1) as $adr031arg) {
    if ($adr031arg === '--i-am-uat') {
        $adr031uat = true;
    } else if ($adr031arg === '--target' || $adr031arg === '--config') {
        fwrite(STDERR, "Refusing: {$adr031arg} needs a value, as {$adr031arg}=<value>.\n");
        exit(1);
    } else if (strpos($adr031arg, '--target=') === 0) {
        $adr031target = rtrim(substr($adr031arg, strlen('--target=')), '/');
    } else if (strpos($adr031arg, '--config=') === 0) {
        $adr031config = substr($adr031arg, strlen('--config='));
    }
}
if ($adr031uat) {
    if ($adr031target !== null || $adr031config !== null) {
        fwrite(STDERR, "Refusing: --i-am-uat and --target/--config are mutually exclusive.\n");
        exit(1);
    }
    $adr031configpath = '/var/www/html/moodle5.2/public/config.php';
} else if ($adr031target !== null || $adr031config !== null) {
    if ($adr031target === null || $adr031target === '' || $adr031config === null || $adr031config === '') {
        fwrite(STDERR, "Refusing: --target=<wwwroot> and --config=<absolute path to config.php> go together.\n");
        exit(1);
    }
    if (!preg_match('~^(?:/|[A-Za-z]:[\\\\/]|\\\\\\\\)~', $adr031config)
            || basename(str_replace('\\', '/', $adr031config)) !== 'config.php'
            || !is_readable($adr031config)) {
        fwrite(STDERR, "Refusing: --config must be the absolute path of a readable config.php.\n");
        exit(1);
    }
    $adr031configpath = $adr031config;
} else {
    fwrite(STDERR, "Refusing to run without --i-am-uat (UAT) or --target=<wwwroot> "
        . "--config=<absolute path to config.php> (migration target).\n");
    exit(1);
}

define('CLI_SCRIPT', true);
require($adr031configpath);
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/accesslib.php');

[$options] = cli_get_params(
    ['i-am-uat' => false, 'target' => '', 'config' => '', 'dry-run' => false, 'apply' => false,
     'tenant-admin-role' => 'administrator'], []);
if ($adr031uat) {
    if (strpos($CFG->wwwroot, 'academy2.airpay.ninja') === false) {
        cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the UAT instance.");
    }
} else {
    if (rtrim($CFG->wwwroot, '/') !== $adr031target) {
        cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the requested target {$adr031target}.");
    }
    cli_writeln("TARGET MODE: wwwroot {$CFG->wwwroot} (config {$adr031config})");
}
if ((bool) $options['dry-run'] === (bool) $options['apply']) {
    cli_error('Pass exactly one of --dry-run, --apply.');
}
$apply = (bool) $options['apply'];

const ADR031_PLATFORM_SHORTNAME = 'sentientiaplatform';
const ADR031_PLATFORM_NAME = 'Sentientia platform administrator';
const ADR031_PLATFORM_DESCRIPTION = 'ADR-031: may see and act across ALL tenants. Holds only '
    . 'local/sentientia_platform:crosstenant. Assign deliberately, at system context, by a site admin.';
const ADR031_CROSSTENANT_CAP = 'local/sentientia_platform:crosstenant';

global $DB;
if (!get_capability_info(ADR031_CROSSTENANT_CAP)) {
    cli_error(ADR031_CROSSTENANT_CAP . ' is not declared on this site: deploy local_sentientia_platform (ADR-031) first.');
}
$sys = context_system::instance();
$tenantadmin = $DB->get_record('role', ['shortname' => $options['tenant-admin-role']], '*', MUST_EXIST);
$role = $DB->get_record('role', ['shortname' => ADR031_PLATFORM_SHORTNAME]);
$warnings = [];
$changed = 0;
$verb = $apply ? '' : 'would ';

// 1. The role itself.
if (!$role) {
    cli_writeln("role " . ADR031_PLATFORM_SHORTNAME . ": missing -> {$verb}create '" . ADR031_PLATFORM_NAME . "', no archetype");
    if ($apply) {
        $roleid = create_role(ADR031_PLATFORM_NAME, ADR031_PLATFORM_SHORTNAME, ADR031_PLATFORM_DESCRIPTION, '');
        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $changed++;
    }
} else {
    cli_writeln("role " . ADR031_PLATFORM_SHORTNAME . ": exists (id {$role->id}, name '{$role->name}')");
    if ((string) $role->archetype !== '') {
        $warnings[] = "role has archetype '{$role->archetype}'; ADR-031 expects none (a role reset would restore that "
            . 'archetype\'s defaults). Not changed.';
    }
}

if ($role) {
    // 2. Assignable only at system context.
    $levels = array_map('intval', array_values(get_role_contextlevels($role->id)));
    sort($levels);
    if ($levels !== [CONTEXT_SYSTEM]) {
        cli_writeln('context levels: [' . implode(', ', $levels) . "] -> {$verb}set to system only");
        if ($apply) {
            set_role_contextlevels($role->id, [CONTEXT_SYSTEM]);
            $changed++;
        }
    } else {
        cli_writeln('context levels: system only (ok)');
    }

    // 3. The one capability.
    $perm = $DB->get_field('role_capabilities', 'permission',
        ['roleid' => $role->id, 'contextid' => $sys->id, 'capability' => ADR031_CROSSTENANT_CAP]);
    if ($perm === false || (int) $perm !== CAP_ALLOW) {
        cli_writeln(ADR031_CROSSTENANT_CAP . ': ' . ($perm === false ? 'inherit' : $perm) . " -> {$verb}ALLOW at system context");
        if ($apply) {
            assign_capability(ADR031_CROSSTENANT_CAP, CAP_ALLOW, $role->id, $sys->id, true);
            $changed++;
        }
    } else {
        cli_writeln(ADR031_CROSSTENANT_CAP . ': ALLOW (ok)');
    }
    $others = $DB->get_records_select('role_capabilities', 'roleid = :roleid AND capability <> :cap',
        ['roleid' => $role->id, 'cap' => ADR031_CROSSTENANT_CAP], 'capability ASC', 'id, contextid, capability, permission');
    if ($others) {
        $list = array_map(fn($rc) => "{$rc->capability}@ctx{$rc->contextid}={$rc->permission}", $others);
        $warnings[] = 'role carries ' . count($others) . ' other capability row(s), expected none. Not removed: '
            . implode(', ', $list);
    } else {
        cli_writeln('other capabilities: none (ok)');
    }

    // 4. Assigned to nobody.
    $assignments = $DB->get_records('role_assignments', ['roleid' => $role->id], 'id ASC', 'id, userid, contextid');
    if ($assignments) {
        $list = array_map(fn($ra) => "user {$ra->userid}@ctx{$ra->contextid}", $assignments);
        $warnings[] = 'role is assigned ' . count($assignments) . ' time(s): ' . implode(', ', $list)
            . '. Not changed: who holds it is a deliberate decision. Check it was one.';
    } else {
        cli_writeln('assignments: nobody (ok)');
    }

    // 5. The tenant-admin role cannot assign it.
    $allows = $DB->get_records('role_allow_assign', ['allowassign' => $role->id], 'roleid ASC');
    $tenantadmincan = false;
    foreach ($allows as $allow) {
        if ((int) $allow->roleid === (int) $tenantadmin->id) {
            $tenantadmincan = true;
            cli_writeln("allow-assign: role {$tenantadmin->shortname} (id {$tenantadmin->id}) CAN assign it -> {$verb}remove that row");
            if ($apply) {
                $DB->delete_records('role_allow_assign', ['id' => $allow->id]);
                if (class_exists('\core\event\role_allow_assign_updated')) {
                    \core\event\role_allow_assign_updated::create(['context' => $sys, 'objectid' => $tenantadmin->id,
                        'other' => ['targetroleid' => $role->id, 'allow' => false]])->trigger();
                }
                $changed++;
            }
        } else {
            $from = $DB->get_field('role', 'shortname', ['id' => $allow->roleid]);
            $warnings[] = "role '{$from}' (id {$allow->roleid}) can assign it. Not changed: remove it at "
                . '/admin/roles/allow.php?mode=assign unless that is deliberate.';
        }
    }
    if (!$tenantadmincan) {
        cli_writeln("allow-assign: role {$tenantadmin->shortname} (id {$tenantadmin->id}) cannot assign it (no role_allow_assign row) (ok)");
    }
} else {
    cli_writeln('context levels: ' . $verb . 'set to system only');
    cli_writeln(ADR031_CROSSTENANT_CAP . ': ' . $verb . 'ALLOW at system context');
    cli_writeln('other capabilities: none (new role)');
    cli_writeln('assignments: nobody (new role)');
    cli_writeln("allow-assign: role {$tenantadmin->shortname} (id {$tenantadmin->id}) cannot assign it (new role: no role_allow_assign rows)");
}

// 6. No other role carries the capability: ADR-031 wants exactly one
// deliberate role (read-only; a grant elsewhere is reported, never removed).
$carriers = $DB->get_records_sql(
    'SELECT rc.id, rc.roleid, rc.contextid, r.shortname
       FROM {role_capabilities} rc
       JOIN {role} r ON r.id = rc.roleid
      WHERE rc.capability = :cap AND rc.permission = :allow
   ORDER BY rc.roleid ASC', ['cap' => ADR031_CROSSTENANT_CAP, 'allow' => CAP_ALLOW]);
$othercarriers = array_filter($carriers, fn($rc) => !$role || (int) $rc->roleid !== (int) $role->id);
if ($othercarriers) {
    $list = array_map(fn($rc) => "{$rc->shortname} (id {$rc->roleid})@ctx{$rc->contextid}", $othercarriers);
    $warnings[] = 'other role(s) ALLOW ' . ADR031_CROSSTENANT_CAP . ': ' . implode(', ', $list)
        . '. Not changed: every holder of those roles is cross-tenant. Check each was deliberate.';
} else {
    cli_writeln('other roles with ' . ADR031_CROSSTENANT_CAP . ' ALLOW: none (ok)');
}

// 7. The guarantee in 5 holds only while the tenant-admin role cannot edit the
// allow matrix or role definitions.
$manage = $DB->get_field('role_capabilities', 'permission',
    ['roleid' => $tenantadmin->id, 'contextid' => $sys->id, 'capability' => 'moodle/role:manage']);
if ($manage !== false && (int) $manage === CAP_ALLOW) {
    $warnings[] = "role {$tenantadmin->shortname} still holds moodle/role:manage (ALLOW): a tenant admin could add the "
        . 'allow-assign row back, or give their own role ' . ADR031_CROSSTENANT_CAP . ' directly. Run '
        . 'tools/uat/adr031_role9_core_caps.php --i-am-uat --apply.';
} else {
    cli_writeln("role {$tenantadmin->shortname}: moodle/role:manage is "
        . ($manage === false ? 'not granted' : ((int) $manage === CAP_PROHIBIT ? 'PROHIBIT' : $manage)) . ' (ok)');
}

// 7b. The same power through ANY role (read-only, 2026-09-29): every user who
// is not a site admin and holds moodle/role:manage at system context can edit
// every role definition, their own included, and so give themselves the
// cross-tenant capability. get_users_by_capability() applies PROHIBITs, so a
// role-9 holder who also holds another role that ALLOWs it is not listed once
// adr031_role9_core_caps.php has run. Reported, never changed.
$siteadminids = array_filter(array_map('intval', explode(',', (string) ($CFG->siteadmins ?? ''))));
$managers = get_users_by_capability($sys, 'moodle/role:manage', 'u.id, u.username', 'u.id ASC');
$nonadminmanagers = array_values(array_filter($managers,
    fn($u) => !in_array((int) $u->id, $siteadminids, true)));
if ($nonadminmanagers) {
    $grantors = $DB->get_records_sql(
        'SELECT r.id, r.shortname
           FROM {role_capabilities} rc
           JOIN {role} r ON r.id = rc.roleid
          WHERE rc.capability = :cap AND rc.contextid = :sysctx AND rc.permission = :allow
       ORDER BY r.id ASC', ['cap' => 'moodle/role:manage', 'sysctx' => $sys->id, 'allow' => CAP_ALLOW]);
    $users = array_map(fn($u) => "{$u->username} (id {$u->id})", array_slice($nonadminmanagers, 0, 20));
    $warnings[] = count($nonadminmanagers) . ' user(s) who are not site admins hold moodle/role:manage at system'
        . ' context: ' . implode(', ', $users) . (count($nonadminmanagers) > 20 ? ', ...' : '')
        . '. Roles that ALLOW it there: '
        . (implode(', ', array_map(fn($r) => "{$r->shortname} (id {$r->id})", $grantors)) ?: 'none (check overrides)')
        . '. Not changed: each can edit every role definition, including giving their own role '
        . ADR031_CROSSTENANT_CAP . '. Check each was deliberate.';
} else {
    cli_writeln('users other than site admins with moodle/role:manage at system context: none (ok)');
}

// 8. For review (read-only): what the tenant-admin role CAN assign through core
// /admin/roles/assign.php. moodle/role:assign stays (the course enrol modal needs
// it), and core applies no tenant check, so any site-level role listed here is a
// remaining cross-tenant path. See ROLE9-CORE-CAPS-2026-09-26.md.
$targets = $DB->get_records_sql(
    'SELECT r.id, r.shortname, r.archetype
       FROM {role_allow_assign} raa
       JOIN {role} r ON r.id = raa.allowassign
      WHERE raa.roleid = :roleid
   ORDER BY r.sortorder ASC', ['roleid' => $tenantadmin->id]);
$labels = array_map(fn($r) => $r->shortname . ((string) $r->archetype !== '' ? " ({$r->archetype})" : ''), $targets);
cli_writeln("NOTE role {$tenantadmin->shortname} may assign (core, no tenant check): "
    . ($labels ? implode(', ', $labels) : 'nothing'));

if ($apply && $changed > 0) {
    $sys->mark_dirty();
}
cli_writeln('');
foreach ($warnings as $warning) {
    cli_writeln('WARNING: ' . $warning);
}
if ($apply) {
    cli_writeln("Applied: {$changed} change(s)" . ($role ? " to role " . ADR031_PLATFORM_SHORTNAME . " (id {$role->id})" : '') . '.');
} else {
    cli_writeln('DRY RUN: nothing changed.');
}
exit($warnings ? 2 : 0);
