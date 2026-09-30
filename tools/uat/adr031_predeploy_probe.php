<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031 pre-deploy probe for UAT. READ-ONLY: prints counts, ids and
 * versions, never names or emails, and writes nothing.
 *
 *   sudo -u www-data php adr031_predeploy_probe.php --i-am-uat
 *
 * On the migration target (any box that is not UAT) name it instead of
 * --i-am-uat; the two are mutually exclusive, and the script refuses unless
 * the config's $CFG->wwwroot equals --target exactly:
 *   sudo -u www-data php adr031_predeploy_probe.php --target=https://<wwwroot> \
 *       --config=/absolute/path/to/config.php
 *
 * Answers the deploy gates in the ADR-031 notes:
 *  - recompletion rules with costcenterid 0 (a tenant admin's rule made before
 *    the fix resets every tenant on cron);
 *  - global (tenant 0) challenges and leaderboard boards;
 *  - featured course rows with costcenterid 0 (what the courses 2026092501
 *    upgrade step will rehome or leave global);
 *  - tenantless users and site admins' open_path (SCIM / write targets);
 *  - courses / classrooms / programs / paths with no open_path;
 *  - whether the org after_config hook and the BizLMS learnerscript block are
 *    present (tree-drift baseline: the hook exists only in the top-level tree);
 *  - every local_sentientia_* / block_sentientia_* installed version.
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

[$options] = cli_get_params(['i-am-uat' => false, 'target' => '', 'config' => ''], []);
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

global $DB;
$dbman = $DB->get_manager();
$has = fn(string $t): bool => $dbman->table_exists($t);
$col = fn(string $t, string $c): bool => isset($DB->get_columns($t)[$c]);
$h = function (string $t): void { echo "\n== {$t}\n"; };

$h('recompletion rules with costcenterid 0');
if ($has('local_sentientia_recompletion_rules')) {
    foreach ($DB->get_records('local_sentientia_recompletion_rules', ['costcenterid' => 0]) as $r) {
        $flags = [];
        foreach (['enabled', 'status', 'courseid', 'timemodified'] as $f) {
            if (property_exists($r, $f)) {
                $flags[] = "{$f}={$r->$f}";
            }
        }
        echo "  id={$r->id} " . implode(' ', $flags) . "\n";
    }
    echo '  total rules: ', $DB->count_records('local_sentientia_recompletion_rules'), "\n";
}

$h('global challenges / leaderboard boards');
if ($has('local_sentientia_challenge_challenges')) {
    echo '  challenges costcenterid=0: ',
        implode(',', array_keys($DB->get_records('local_sentientia_challenge_challenges', ['costcenterid' => 0], '', 'id'))),
        ' of ', $DB->count_records('local_sentientia_challenge_challenges'), "\n";
}
if ($has('local_sentientia_lb_boards') && $col('local_sentientia_lb_boards', 'tenantid')) {
    echo '  boards tenantid=0: ',
        implode(',', array_keys($DB->get_records('local_sentientia_lb_boards', ['tenantid' => 0], '', 'id'))),
        ' of ', $DB->count_records('local_sentientia_lb_boards'), "\n";
}

$h('featured course rows with costcenterid 0');
// A tenant admin could only pin to "All tenants" before ADR-031. Upgrade step
// local_sentientia_courses 2026092501 moves each such row to its course's
// tenant; the shared, legacy and duplicate ones stay global (see its config log).
if ($has('local_sentientia_featured_courses')) {
    $pathsel = $col('course', 'open_path') ? 'c.open_path' : 'NULL';
    $shared = $has('local_sentientia_courses_tenant_share')
        ? "(SELECT COUNT(1) FROM {local_sentientia_courses_tenant_share} s
             WHERE s.courseid = f.courseid AND s.status = 'active')" : '0';
    foreach ($DB->get_records_sql("SELECT f.id, f.courseid, {$pathsel} AS open_path, {$shared} AS shares
            FROM {local_sentientia_featured_courses} f LEFT JOIN {course} c ON c.id = f.courseid
           WHERE f.costcenterid = 0 ORDER BY f.id") as $f) {
        $seg = explode('/', trim((string) $f->open_path, '/'))[0];
        echo "  id={$f->id} courseid={$f->courseid} course_tenant=" . ($seg === '' ? 'none' : $seg)
            . " active_shares={$f->shares}\n";
    }
    echo '  total featured rows: ', $DB->count_records('local_sentientia_featured_courses'), "\n";
}

$h('users');
$emptypath = "(open_path IS NULL OR open_path = '')";
echo '  tenantless active users (id>2): ',
    $DB->count_records_select('user', "deleted = 0 AND suspended = 0 AND id > 2 AND {$emptypath}"), "\n";
foreach (array_filter(array_map('intval', explode(',', (string) $CFG->siteadmins))) as $a) {
    $p = $DB->get_field('user', 'open_path', ['id' => $a]);
    echo "  site admin id={$a} open_path=" . var_export($p, true) . "\n";
}
$roleid = $DB->get_field('role', 'id', ['shortname' => 'administrator']);
if ($roleid) {
    $n = $DB->count_records_sql("SELECT COUNT(DISTINCT ra.userid) FROM {role_assignments} ra
        WHERE ra.roleid = :r AND ra.contextid = :c", ['r' => $roleid, 'c' => context_system::instance()->id]);
    echo "  'administrator' role id={$roleid}: {$n} system-context holders\n";
}
if (get_capability_info('local/sentientia_platform:crosstenant')) {
    $holders = get_users_by_capability(context_system::instance(), 'local/sentientia_platform:crosstenant', 'u.id');
    echo '  :crosstenant holders (incl. admins): ', count($holders), "\n";
} else {
    echo "  local/sentientia_platform:crosstenant not installed yet\n";
}

$h('role assignments of the tenant-admin role by context level');
if ($roleid) {
    foreach ($DB->get_records_sql("SELECT ctx.contextlevel, COUNT(1) AS n, COUNT(DISTINCT ra.userid) AS users
            FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid
           WHERE ra.roleid = :r GROUP BY ctx.contextlevel ORDER BY ctx.contextlevel", ['r' => $roleid]) as $r) {
        echo "  contextlevel {$r->contextlevel}: {$r->n} assignment(s), {$r->users} user(s)\n";
    }
}

$h('feature-flag overrides for the PWA');
if ($has('local_sentientia_feature_flags')) {
    $rows = $DB->get_records_select('local_sentientia_feature_flags',
        $DB->sql_like('flag_key', ':k'), ['k' => 'sentientia.pwa%'], 'flag_key, customer_id, tenant_id',
        'id, flag_key, customer_id, tenant_id, is_enabled');
    foreach ($rows as $r) {
        echo "  {$r->flag_key} customer={$r->customer_id} tenant={$r->tenant_id} enabled={$r->is_enabled}\n";
    }
    echo '  override rows: ', count($rows), "\n";
}

$h('items with no open_path');
foreach (['course' => 'id > 1', 'local_sentientia_classroom' => '1=1', 'local_sentientia_programs' => '1=1',
        'local_sentientia_learningpath' => '1=1'] as $t => $base) {
    if ($has($t) && $col($t, 'open_path')) {
        $ids = $DB->get_fieldset_select($t, 'id', "{$base} AND {$emptypath}");
        echo "  {$t}: " . count($ids) . ' of ' . $DB->count_records_select($t, $base)
            . (count($ids) ? ' ids ' . implode(',', array_slice($ids, 0, 20)) : '') . "\n";
    }
}

$h('org hook + BizLMS vendor blocks');
$pub = $CFG->dirroot;
foreach (['local/sentientia_org/db/hooks.php', 'local/sentientia_org/classes/hook_callbacks.php',
        'local/sentientia_org/classes/compat/bizlms_lib.php', 'blocks/learnerscript/version.php',
        'local/costcenter/version.php'] as $f) {
    echo '  ', $f, ': ', is_file("{$pub}/{$f}") ? 'present' : 'absent', "\n";
}
echo '  block_learnerscript installed: ', get_config('block_learnerscript', 'version') ?: 'no', "\n";

$h('installed versions');
foreach (['local', 'block'] as $type) {
    foreach (core_component::get_plugin_list($type) as $name => $dir) {
        if (strpos($name, 'sentientia') === 0) {
            echo "  {$type}_{$name} ", get_config("{$type}_{$name}", 'version'), "\n";
        }
    }
}
echo "  theme_sentientia ", get_config('theme_sentientia', 'version'), "\n";
echo "\nopcache.validate_timestamps (CLI) = ", ini_get('opcache.validate_timestamps'), "\n";
