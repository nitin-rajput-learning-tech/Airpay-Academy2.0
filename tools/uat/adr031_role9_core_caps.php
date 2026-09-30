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
 *     guest / authenticated user / frontpage, which only lower rights - and
 *     only while that role ALLOWs none of course_manager::SITE_LEVEL_CAPABILITIES
 *     at system context (a customised one that does is removed like the rest).
 *     The manager-archetype default (editingteacher, teacher, student, guest)
 *     loses nothing.
 *   - PART 2 needs local_sentientia_courses (ADR-031). Without it --dry-run
 *     warns and --apply REFUSES: PART 1 alone can be bypassed by assigning
 *     'manager' through core.
 *
 * Holders below system context (2026-09-29): the PROHIBITs and the trimmed
 * allow rows are part of the role's DEFINITION, so they apply wherever the
 * role is assigned, not only at system context. A holder at a course category
 * or course loses, inside that category or course: "Log in as" its course
 * participants, role overrides, and assigning (or switching to)
 * manager / coursecreator / administrator or any other site-level role
 * through core. Before the plan the script prints the role's assignments by
 * context level with user counts, and a WARNING when any is below system
 * context. --apply then refuses unless --accept-nonsystem-holders is passed:
 * that is a decision for the site owner (Nitin), not for the operator.
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
 * exactly that. --revert gives moodle/role:manage back, and with it the way
 * to undo the platform-role guarantee: re-run adr031_crosstenant_role.php
 * --dry-run afterwards.
 *
 * USAGE (on the UAT box):
 *   sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --dry-run
 *   sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --apply [--accept-nonsystem-holders]
 *   sudo -u www-data php adr031_role9_core_caps.php --i-am-uat --revert
 *
 * On the migration target (any box that is not UAT) name it instead of
 * --i-am-uat; the two are mutually exclusive, and the script refuses unless
 * the config's $CFG->wwwroot equals --target exactly (--role= names the
 * target's tenant-admin role if it is not 'administrator'):
 *   sudo -u www-data php adr031_role9_core_caps.php --target=https://<wwwroot> \
 *       --config=/absolute/path/to/config.php --dry-run
 *
 * Options: --role=<shortname> (default: administrator, UAT's id-9 tenant-admin role).
 *          --accept-nonsystem-holders: let --apply proceed although the role is
 *          assigned below system context (read the WARNING first).
 * Exit codes: 0 done / clean dry run; 1 refused or error; 2 dry run with WARNINGs.
 */

// Which box is this for? Exactly one of --i-am-uat, or --target + --config.
// cli_get_params() needs Moodle, so these are read from $argv before config.php
// is loaded. (Same block in the four adr031_* UAT scripts.)
$adr031uat = false;
$adr031target = null;
$adr031config = null;
foreach (array_slice($argv, 1) as $adr031arg) {
    if ($adr031arg === '--i-am-uat' || $adr031arg === '--i-am-uat=1') {
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
     'revert' => false, 'role' => 'administrator', 'accept-nonsystem-holders' => false], []);
if ($adr031uat) {
    if (strpos($CFG->wwwroot, 'academy2.airpay.ninja') === false) {
        cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the UAT instance.");
    }
} else {
    if (rtrim($CFG->wwwroot, '/') !== $adr031target) {
        cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the requested target {$adr031target}.");
    }
    // The wwwroot is not enough on its own: the migration target and the live
    // BizLMS box can answer to the same name before the repoint. A Sentientia
    // install has local_sentientia_platform on disk; the live BizLMS box does not.
    if (core_component::get_component_directory('local_sentientia_platform') === null) {
        cli_error('Refusing: local_sentientia_platform is not on disk for this config, '
            . 'so it is not a Sentientia install (is it the live BizLMS box?).');
    }
    // Print which database this is, so the operator can see it before anything runs.
    cli_writeln("TARGET MODE: wwwroot {$CFG->wwwroot} (config {$adr031config})");
    cli_writeln("  database {$CFG->dbhost} / {$CFG->dbname}, prefix {$CFG->prefix}, "
        . "Moodle {$CFG->release} (branch {$CFG->branch})");
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

/**
 * The role's assignments grouped by context level: system, coursecat, course
 * and other (user, activity, block, or a context row that no longer exists).
 *
 * @param int $roleid
 * @return array<string, array{assignments: int, users: int[], contexts: array<int, int[]>}>
 *         contexts maps context id => the user ids assigned there
 */
function adr031_role9_assignments_by_level(int $roleid): array {
    global $DB;
    $groups = [];
    foreach (['system', 'coursecat', 'course', 'other'] as $group) {
        $groups[$group] = ['assignments' => 0, 'users' => [], 'contexts' => []];
    }
    $rows = $DB->get_records_sql(
        'SELECT ra.id, ra.userid, ra.contextid, ctx.contextlevel
           FROM {role_assignments} ra
      LEFT JOIN {context} ctx ON ctx.id = ra.contextid
          WHERE ra.roleid = :roleid
       ORDER BY ra.contextid ASC, ra.userid ASC', ['roleid' => $roleid]);
    foreach ($rows as $ra) {
        $group = match ((int) ($ra->contextlevel ?? 0)) {
            CONTEXT_SYSTEM => 'system',
            CONTEXT_COURSECAT => 'coursecat',
            CONTEXT_COURSE => 'course',
            default => 'other',
        };
        $groups[$group]['assignments']++;
        $groups[$group]['users'][(int) $ra->userid] = (int) $ra->userid;
        $groups[$group]['contexts'][(int) $ra->contextid][] = (int) $ra->userid;
    }
    return $groups;
}

/**
 * A context by id, as a short label for the operator.
 *
 * @param int $contextid
 * @return string
 */
function adr031_role9_context_label(int $contextid): string {
    $context = context::instance_by_id($contextid, IGNORE_MISSING);
    if (!$context) {
        return "context {$contextid} (missing)";
    }
    return $context->get_context_name(true, true, false) . " (context {$contextid}, level {$context->contextlevel})";
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
    if (array_key_exists('moodle/role:manage', $state['caps'])) {
        cli_writeln("WARNING: role {$role->shortname} has moodle/role:manage back: its holders can again edit every"
            . ' role definition and the allow matrices, so they could give their own role'
            . ' local/sentientia_platform:crosstenant or let it assign the platform role. Re-run'
            . ' adr031_crosstenant_role.php --i-am-uat --dry-run now.');
    }
    exit(0);
}

if ($mode === 'apply' && is_readable($statefile)) {
    cli_error("Already applied (state at {$statefile}); revert it first.");
}

$warnings = 0;

// Where the role is assigned. The changes below are made to the role's
// definition, so they reach every holder, at whatever context they hold it.
cli_writeln("Assignments of role {$role->shortname}, by context level:");
$bylevel = adr031_role9_assignments_by_level((int) $role->id);
$labels = ['system' => 'system', 'coursecat' => 'course category', 'course' => 'course',
    'other' => 'other (user, activity, block, missing)'];
foreach ($bylevel as $group => $g) {
    $admins = count(array_intersect($g['users'], $siteadmins));
    cli_writeln(sprintf('  %-40s %d assignment(s), %d user(s)%s', $labels[$group], $g['assignments'], count($g['users']),
        $admins ? ", {$admins} of them site admin(s)" : ''));
    if ($group === 'system') {
        continue;
    }
    $shown = 0;
    foreach ($g['contexts'] as $contextid => $userids) {
        if (++$shown > 20) {
            cli_writeln('      ... and ' . (count($g['contexts']) - 20) . ' more context(s)');
            break;
        }
        cli_writeln('      ' . adr031_role9_context_label((int) $contextid) . ': user(s) ' . implode(', ', $userids));
    }
}
$nonsystem = $bylevel['coursecat']['assignments'] + $bylevel['course']['assignments'] + $bylevel['other']['assignments'];
$nonsystemusers = array_unique(array_merge($bylevel['coursecat']['users'], $bylevel['course']['users'],
    $bylevel['other']['users']));
if ($nonsystem > 0) {
    $warnings++;
    cli_writeln('');
    cli_writeln("WARNING: role {$role->shortname} is assigned {$nonsystem} time(s) BELOW system context"
        . " (course category: {$bylevel['coursecat']['assignments']}, course: {$bylevel['course']['assignments']},"
        . " other: {$bylevel['other']['assignments']}; " . count($nonsystemusers) . ' user(s), '
        . count(array_intersect($nonsystemusers, $siteadmins)) . ' of them site admin(s), not affected).');
    cli_writeln('  The PROHIBITs and the allow-row trim change the role\'s DEFINITION, so they apply wherever the'
        . ' role is assigned. Inside the category or course they hold it in, those holders lose:');
    cli_writeln('  - "Log in as" for the participants of the courses there (moodle/user:loginas, checked at'
        . ' course context);');
    cli_writeln('  - role overrides in that category or course, its courses and their activities'
        . ' (moodle/role:override);');
    cli_writeln('  - assigning manager, coursecreator, administrator or any other site-level role there through core'
        . ' /admin/roles/assign.php (the allow-assign trim), and core "Switch role to" such a role (the'
        . ' allow-switch trim);');
    cli_writeln('  - and there a PROHIBIT also beats an ALLOW from any other role they hold (a manager role in one'
        . ' of those courses, say).');
    cli_writeln('  An assignment at another level loses whichever of these is checked there (moodle/user:editprofile'
        . ' in a user context, for example). The rest of PART 1 is checked at system context, which a category or'
        . ' course assignment never reached.');
    cli_writeln('  Whether those holders should lose this is the site owner\'s decision. --apply refuses unless'
        . ' --accept-nonsystem-holders is passed.');
}
cli_writeln('');

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
$part2ok = class_exists('\local_sentientia_courses\course_manager')
    && method_exists('\local_sentientia_courses\course_manager', 'scoped_forbidden_role_ids')
    && defined('local_sentientia_courses\course_manager::SITE_LEVEL_CAPABILITIES');
if (!$part2ok) {
    $warnings++;
    cli_writeln('WARNING: local_sentientia_courses (ADR-031) is not deployed here, so the forbidden set is unknown'
        . ' and part 2 cannot be computed. ' . ($mode === 'apply' ? '--apply refuses (below).'
        : '--apply will refuse until it is deployed: PART 1 alone can be bypassed by assigning manager through core.'));
} else {
    $forbidden = array_map('intval', \local_sentientia_courses\course_manager::scoped_forbidden_role_ids());
    $forbidden[] = (int) $role->id;
    // Roles that ALLOW a site-level capability at system context: switching
    // into one of them never only lowers rights, whatever it is called.
    [$capsql, $capparams] = $DB->get_in_or_equal(
        \local_sentientia_courses\course_manager::SITE_LEVEL_CAPABILITIES, SQL_PARAMS_NAMED, 'slcap');
    $sitelevel = array_map('intval', $DB->get_fieldset_sql(
        "SELECT DISTINCT roleid
           FROM {role_capabilities}
          WHERE contextid = :sysctx AND permission = :allow AND capability {$capsql}",
        ['sysctx' => $sys->id, 'allow' => CAP_ALLOW] + $capparams));
    // Switching into a guest / authenticated-user / frontpage role only lowers
    // rights, so it stays switchable - unless it is also site-level.
    $lowering = [];
    $lowlooking = [];
    foreach ($DB->get_records('role', null, '', 'id, shortname, archetype') as $r) {
        if (in_array((string) $r->archetype, ['guest', 'user', 'frontpage'], true)
                || in_array((string) $r->shortname, ['guest', 'user', 'frontpage'], true)) {
            $lowlooking[] = (int) $r->id;
            if (!in_array((int) $r->id, $sitelevel, true)) {
                $lowering[] = (int) $r->id;
            }
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
            } else if ($kind === 'switch' && in_array($targetid, $lowlooking, true)) {
                $trim[$kind][] = $targetid;
                adr031_role9_row($label, 'yes', 'remove',
                    'guest/user/frontpage type, but it ALLOWs a site-level capability at system context');
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
        . ($part2ok ? "{$trimcount}" : 'an unknown number of') . " allow row(s) removed for role {$role->shortname}."
        . ' Nothing changed.');
    if ($warnings) {
        cli_writeln("{$warnings} WARNING(s) above; read them before --apply.");
    }
    exit($warnings ? 2 : 0);
}

// --apply: refuse before anything is written.
if (!$part2ok) {
    cli_error('Refusing --apply: PART 2 cannot be computed because local_sentientia_courses (ADR-031) is not'
        . ' deployed. PART 1 alone can be bypassed: a tenant admin could still make a second account a site-wide'
        . ' manager through core /admin/roles/assign.php. Deploy it first. Nothing changed.');
}
if ($nonsystem > 0) {
    if (empty($options['accept-nonsystem-holders'])) {
        cli_error("Refusing --apply: role {$role->shortname} is assigned {$nonsystem} time(s) below system context"
            . ' (WARNING above). Pass --accept-nonsystem-holders once it is agreed that those holders lose what the'
            . ' WARNING lists. Nothing changed.');
    }
    cli_writeln("--accept-nonsystem-holders given: proceeding although {$nonsystem} assignment(s) are below system"
        . ' context.');
}

if (!$plan && $trimcount === 0) {
    cli_writeln("Nothing to change for role {$role->shortname} ({$already} capabilities already PROHIBIT); "
        . 'no state written.');
    exit(0);
}

$state = ['role' => $role->shortname, 'roleid' => (int) $role->id, 'applied' => date('c'),
    'caps' => $plan, 'allowassign_removed' => $trim['assign'], 'allowswitch_removed' => $trim['switch'],
    'assignments_by_level' => array_map(fn($g) => ['assignments' => $g['assignments'], 'users' => count($g['users'])],
        $bylevel),
    'accepted_nonsystem_holders' => $nonsystem > 0];
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
