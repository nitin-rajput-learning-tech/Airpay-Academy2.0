<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * CLI diagnostic for Learning Path admin UX issues.
 *
 * Background
 * ----------
 * On 2026-05-13 our LMS Admin reported on the live production site
 * (airpay.academy) that "adding and removal of courses from learning
 * path is not allowed today". A code review confirmed that
 * `local_sentientia_learningpath` does have the full UX — view.php,
 * templates/view.mustache with an "Add Courses" button, ten external
 * web services covering assign/unassign/reorder/enrol/unenrol/list,
 * AMD module `path_actions.js` for the modals + AJAX, and all four
 * capabilities (`:manage`, `:update`, `:enrol`, `:create`) declared
 * in db/access.php with `archetype => ['manager' => CAP_ALLOW]`.
 *
 * So if the admin can't see those buttons on production, one of three
 * things is wrong:
 *
 *   1. The plugin isn't deployed (production server hasn't pulled
 *      from git OR the upgrade hasn't been run).
 *   2. The user's role doesn't have `local/sentientia_learningpath:update`
 *      at the system context — either because role assignments
 *      pre-date the plugin install, or the role is custom and was
 *      never granted the new caps.
 *   3. The user is hitting the wrong URL — index.php (the list page,
 *      which has no add-courses button) instead of view.php?id=N.
 *
 * This script walks all three checks and emits a diagnosis with a
 * specific fix instruction. With `--fix-caps` it will also grant the
 * missing capability to the `manager` archetype (idempotent — won't
 * double-grant).
 *
 * USAGE
 *   php local/sentientia_learningpath/cli/diagnose_admin_ux.php
 *     -- run all diagnostic checks, report pass/fail per check.
 *
 *   php local/sentientia_learningpath/cli/diagnose_admin_ux.php --user=academy@airpay.co.in
 *     -- diagnose AS IF this user were trying to use the feature
 *        (resolves their role + checks their caps).
 *
 *   php local/sentientia_learningpath/cli/diagnose_admin_ux.php --fix-caps
 *     -- repair: grant the four manage/update/enrol/create capabilities
 *        to the `manager` archetype role, idempotent.
 *
 *   php local/sentientia_learningpath/cli/diagnose_admin_ux.php --json
 *     -- machine-readable output.
 *
 * EXIT CODES
 *   0  all checks pass — admin should be able to add/remove courses
 *   1  at least one check failed — see report for fix instructions
 *   2  invalid arguments
 *
 * @package local_sentientia_learningpath
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help'      => false,
    'user'      => false,
    'fix-caps'  => false,
    'json'      => false,
], [
    'h' => 'help',
    'u' => 'user',
]);

if ($unrecognized) {
    cli_error('Unknown argument(s): ' . implode(', ', $unrecognized), 2);
}

if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__));
    exit(0);
}

global $DB, $CFG;

// ── helper: format one check result ──────────────────────────────────
$checks = [];

function record_check(string $name, bool $passed, string $detail,
                       string $fix = ''): void {
    global $checks;
    $checks[] = [
        'name'   => $name,
        'passed' => $passed,
        'detail' => $detail,
        'fix'    => $fix,
    ];
}

// ── Check 1: plugin row in {config_plugins} ──────────────────────────
$installed_version = $DB->get_field('config_plugins', 'value', [
    'plugin' => 'local_sentientia_learningpath',
    'name'   => 'version',
]);

if ($installed_version) {
    record_check(
        'Plugin installed in DB',
        true,
        "local_sentientia_learningpath version $installed_version"
    );
} else {
    record_check(
        'Plugin installed in DB',
        false,
        'No row in {config_plugins} for local_sentientia_learningpath — '
            . 'the plugin code may be on disk but `php admin/cli/upgrade.php` '
            . 'has not been run.',
        'Run: php admin/cli/upgrade.php --non-interactive  '
            . '(or visit Site Administration → Notifications)'
    );
}

// ── Check 2: tables exist ────────────────────────────────────────────
$dbman = $DB->get_manager();
$needed_tables = [
    'local_sentientia_learningpath',
    'local_sentientia_learningpath_courses',
    'local_sentientia_learningpath_users',
];
$missing_tables = [];
foreach ($needed_tables as $tn) {
    if (!$dbman->table_exists($tn)) {
        $missing_tables[] = $tn;
    }
}
if (empty($missing_tables)) {
    record_check(
        'DB tables present',
        true,
        '3/3 tables (' . implode(', ', $needed_tables) . ') exist'
    );
} else {
    record_check(
        'DB tables present',
        false,
        count($missing_tables) . ' table(s) missing: '
            . implode(', ', $missing_tables),
        'Run the Moodle upgrade: php admin/cli/upgrade.php'
    );
}

// ── Check 3: required files on disk ──────────────────────────────────
$plugin_root = $CFG->dirroot . '/local/sentientia_learningpath';
$needed_files = [
    'view.php',                       // detail page with add-courses UI
    'index.php',                      // list page
    'templates/view.mustache',        // template with the Add Courses button
    'amd/build/path_actions.min.js',  // compiled JS module
    'classes/external/assign_courses.php',
    'classes/external/unassign_course.php',
    'classes/external/reorder_courses.php',
    'db/access.php',
    'db/services.php',
];
$missing_files = [];
foreach ($needed_files as $rel) {
    if (!file_exists("$plugin_root/$rel")) {
        $missing_files[] = $rel;
    }
}
if (empty($missing_files)) {
    record_check(
        'Plugin files on disk',
        true,
        count($needed_files) . '/' . count($needed_files) . ' required files present'
    );
} else {
    record_check(
        'Plugin files on disk',
        false,
        count($missing_files) . ' file(s) missing: '
            . implode(', ', $missing_files),
        'Deploy the latest from github.com/nitin-rajput-learning-tech/Airpay-Academy2.0 '
            . '(branch: production). After file copy, run: php admin/cli/purge_caches.php'
    );
}

// ── Check 4: web services are registered + enabled ──────────────────
$service_functions = [
    'local_sentientia_learningpath_assign_courses',
    'local_sentientia_learningpath_unassign_course',
    'local_sentientia_learningpath_reorder_courses',
    'local_sentientia_learningpath_list_path_courses',
];
$missing_services = [];
foreach ($service_functions as $fn) {
    if (!$DB->record_exists('external_functions', ['name' => $fn])) {
        $missing_services[] = $fn;
    }
}
if (empty($missing_services)) {
    record_check(
        'Web services registered',
        true,
        count($service_functions) . '/' . count($service_functions)
            . ' WS functions registered in {external_functions}'
    );
} else {
    record_check(
        'Web services registered',
        false,
        count($missing_services) . ' WS function(s) not registered: '
            . implode(', ', $missing_services),
        'Run: php admin/cli/upgrade.php  (this re-reads db/services.php)'
    );
}

// ── Check 5: capabilities declared in DB ─────────────────────────────
$caps = [
    'local/sentientia_learningpath:view',
    'local/sentientia_learningpath:create',
    'local/sentientia_learningpath:update',
    'local/sentientia_learningpath:enrol',
    'local/sentientia_learningpath:manage',
    'local/sentientia_learningpath:delete',
];
$missing_caps = [];
foreach ($caps as $cap) {
    if (!$DB->record_exists('capabilities', ['name' => $cap])) {
        $missing_caps[] = $cap;
    }
}
if (empty($missing_caps)) {
    record_check(
        'Capabilities defined',
        true,
        count($caps) . '/' . count($caps)
            . ' capabilities declared in {capabilities}'
    );
} else {
    record_check(
        'Capabilities defined',
        false,
        count($missing_caps) . ' capability/ies missing: '
            . implode(', ', $missing_caps),
        'Run: php admin/cli/upgrade.php  (re-applies db/access.php)'
    );
}

// ── Check 6: capability is granted to the manager role ──────────────
$manager_role = $DB->get_record('role', ['shortname' => 'manager']);
$systemctx = context_system::instance();

$grant_status = [];   // capname => bool granted (any role at any context)
$manager_grant = [];  // capname => bool granted to manager at system

if ($manager_role) {
    foreach (['local/sentientia_learningpath:update', 'local/sentientia_learningpath:enrol',
              'local/sentientia_learningpath:manage', 'local/sentientia_learningpath:create'] as $cap) {
        $row = $DB->get_record('role_capabilities', [
            'roleid'     => $manager_role->id,
            'capability' => $cap,
            'contextid'  => $systemctx->id,
        ]);
        $manager_grant[$cap] = ($row && (int)$row->permission === CAP_ALLOW);
    }
    $missing_grants = array_keys(array_filter($manager_grant, fn($v) => !$v));

    if (empty($missing_grants)) {
        record_check(
            'manager role has plugin caps',
            true,
            "Role 'manager' has all 4 write caps at system context (assign/enrol/manage/create)"
        );
    } else {
        record_check(
            'manager role has plugin caps',
            false,
            "Role 'manager' is MISSING " . count($missing_grants)
                . ' cap(s): ' . implode(', ', $missing_grants),
            "Re-run upgrade (php admin/cli/upgrade.php) OR run THIS cli with --fix-caps "
                . "to grant the missing caps idempotently."
        );
    }
} else {
    record_check(
        "manager role exists",
        false,
        "No role with shortname='manager' — unusual; Moodle ships this role by default",
        'Check Site Administration → Users → Permissions → Define roles'
    );
}

// ── Check 7: target user's specific capabilities (if --user= given) ──
if ($options['user']) {
    $email = $options['user'];
    $target = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    if (!$target) {
        record_check(
            "Target user lookup",
            false,
            "No active user with email '$email'",
            "Check the email spelling, or try a username via 'username=' if your "
                . "Moodle uses non-email logins."
        );
    } else {
        $can_update = has_capability(
            'local/sentientia_learningpath:update', $systemctx, $target);
        $can_manage = has_capability(
            'local/sentientia_learningpath:manage', $systemctx, $target);
        $can_enrol  = has_capability(
            'local/sentientia_learningpath:enrol',  $systemctx, $target);
        $is_admin   = is_siteadmin($target->id);

        $can_see_button = $is_admin || $can_update || $can_manage;
        if ($can_see_button) {
            record_check(
                "User '$email' can use feature",
                true,
                "Will see Add/Remove Courses button "
                    . "(admin=" . ($is_admin ? 'Y' : 'N')
                    . " update=" . ($can_update ? 'Y' : 'N')
                    . " manage=" . ($can_manage ? 'Y' : 'N') . ')'
            );
        } else {
            record_check(
                "User '$email' can use feature",
                false,
                "User cannot see the Add Courses button (no relevant caps)",
                "Either: (a) make them a site admin, or (b) assign them the "
                    . "'manager' role at system context, or (c) grant "
                    . "local/sentientia_learningpath:update directly to their role. "
                    . "Site Admin → Users → Permissions → Assign user roles → "
                    . "search '$email'."
            );
        }
    }
}

// ── Check 8 (optional auto-fix): grant missing caps to manager role ─
if ($options['fix-caps']) {
    if (!$manager_role) {
        cli_error("Cannot fix-caps: no 'manager' role found.", 1);
    }
    $granted_now = [];
    $caps_to_grant = [
        'local/sentientia_learningpath:update',
        'local/sentientia_learningpath:enrol',
        'local/sentientia_learningpath:manage',
        'local/sentientia_learningpath:create',
    ];
    foreach ($caps_to_grant as $cap) {
        // Skip if the cap definition itself doesn't exist.
        if (!$DB->record_exists('capabilities', ['name' => $cap])) {
            continue;
        }
        // Idempotent: insert if missing, update if present.
        $existing = $DB->get_record('role_capabilities', [
            'roleid'     => $manager_role->id,
            'capability' => $cap,
            'contextid'  => $systemctx->id,
        ]);
        if ($existing) {
            if ((int)$existing->permission !== CAP_ALLOW) {
                $existing->permission   = CAP_ALLOW;
                $existing->timemodified = time();
                $DB->update_record('role_capabilities', $existing);
                $granted_now[] = "$cap (updated)";
            }
        } else {
            $DB->insert_record('role_capabilities', (object) [
                'contextid'    => $systemctx->id,
                'roleid'       => $manager_role->id,
                'capability'   => $cap,
                'permission'   => CAP_ALLOW,
                'timemodified' => time(),
                'modifierid'   => 0,
            ]);
            $granted_now[] = "$cap (granted)";
        }
    }
    // Bust caps cache so the change is visible immediately.
    if (function_exists('reload_all_capabilities')) {
        reload_all_capabilities();
    }
    if (empty($granted_now)) {
        record_check('Fix-caps applied', true,
            'No changes needed — manager already has all 4 caps');
    } else {
        record_check('Fix-caps applied', true,
            count($granted_now) . ' cap(s) granted: ' . implode(', ', $granted_now));
    }
}

// ── Report ──────────────────────────────────────────────────────────
if ($options['json']) {
    echo json_encode([
        'plugin'  => 'local_sentientia_learningpath',
        'when'    => date('c'),
        'checks'  => $checks,
        'all_pass' => array_reduce($checks, fn($a, $c) => $a && $c['passed'], true),
    ], JSON_PRETTY_PRINT) . "\n";
} else {
    cli_writeln('');
    cli_writeln('  Airpay Learning Path — admin UX diagnostic');
    cli_writeln('  ' . str_repeat('-', 56));
    foreach ($checks as $c) {
        $mark = $c['passed'] ? '[PASS]' : '[FAIL]';
        cli_writeln(sprintf('  %s  %s', $mark, $c['name']));
        cli_writeln('         ' . $c['detail']);
        if (!$c['passed'] && $c['fix']) {
            cli_writeln('         FIX → ' . $c['fix']);
        }
    }
    cli_writeln('  ' . str_repeat('-', 56));
    $any_fail = array_filter($checks, fn($c) => !$c['passed']);
    if (empty($any_fail)) {
        cli_writeln('  ALL CHECKS PASS — the feature should work on this server.');
        cli_writeln('  If the admin still cannot see the buttons, ask them to');
        cli_writeln('  visit:  ' . $CFG->wwwroot
            . '/local/sentientia_learningpath/index.php');
        cli_writeln('  then click a path → Courses tab → "Add Courses" button.');
    } else {
        cli_writeln(sprintf('  %d check(s) FAILED — fix the items above.',
            count($any_fail)));
    }
    cli_writeln('');
}

exit(empty($any_fail) ? 0 : 1);
