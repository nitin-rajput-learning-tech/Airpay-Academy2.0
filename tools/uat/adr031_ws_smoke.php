<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-031 post-deploy smoke for UAT: call every READ web service of the
 * Sentientia plugins as real personas and report what happens.
 *
 * READ-ONLY by construction: only functions declared 'type' => 'read' in a
 * plugin's db/services.php are called, with no arguments (their defaults), and
 * names that export, send, generate, run, sync or test anything are skipped, as
 * are the AI-backed and external-integration plugins (no spend, no outbound
 * calls). Output is persona / function / OK + row counts or the error code -
 * never a name, email or record content.
 *
 * What it catches: SQL that MySQL 8.4 rejects but local MariaDB accepts, fatal
 * errors on ADR-031 code paths with real data, and scoping that refuses an
 * in-tenant persona (a "nopermissions"/"error_outoftenant" where a count was
 * expected).
 *
 *   sudo -u www-data php adr031_ws_smoke.php --i-am-uat
 */

define('CLI_SCRIPT', true);
require('/var/www/html/moodle5.2/public/config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['i-am-uat' => false], []);
if (empty($options['i-am-uat'])) {
    cli_error('Refusing to run without --i-am-uat.');
}
if (strpos($CFG->wwwroot, 'academy2.airpay.ninja') === false) {
    cli_error("Refusing: wwwroot is {$CFG->wwwroot}, not the UAT instance.");
}

global $DB, $CFG;

$skipcomponents = ['local_sentientia_ai', 'local_sentientia_aiquiz', 'local_sentientia_assistant',
    'local_sentientia_translate', 'local_sentientia_recommendations', 'local_sentientia_skillsai',
    'local_sentientia_whatsapp', 'local_sentientia_m365', 'local_sentientia_integrations',
    'local_sentientia_api', 'block_sentientia_recommendations'];
$skipwords = ['export', 'download', 'send', 'generate', 'run', 'sync', 'test', 'nudge', 'preview',
    'render', 'push', 'import', 'mark', 'ping', 'heartbeat', 'stream', 'poll'];

// Collect read-type functions from every Sentientia plugin's services.php.
$functions = [];
foreach (['local', 'block'] as $type) {
    foreach (core_component::get_plugin_list($type) as $name => $dir) {
        $component = "{$type}_{$name}";
        if (strpos($name, 'sentientia') !== 0 || in_array($component, $skipcomponents, true)) {
            continue;
        }
        $file = "{$dir}/db/services.php";
        if (!is_readable($file)) {
            continue;
        }
        $functions_decl = (function (string $f): array {
            $functions = [];
            include $f;
            return $functions;
        })($file);
        foreach ($functions_decl as $fname => $def) {
            if (($def['type'] ?? '') !== 'read') {
                continue;
            }
            foreach ($skipwords as $w) {
                if (stripos($fname, $w) !== false) {
                    continue 2;
                }
            }
            if ($DB->record_exists('external_functions', ['name' => $fname])) {
                $functions[] = $fname;
            }
        }
    }
}
sort($functions);

// Personas (ids only).
$admins = array_filter(array_map('intval', explode(',', (string) $CFG->siteadmins)));
$sys = context_system::instance();
$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'administrator']);
$pick = function (string $sql, array $params) use ($DB, $admins): ?int {
    foreach ($DB->get_fieldset_sql($sql, $params) as $id) {
        if (!in_array((int) $id, $admins, true)) {
            return (int) $id;
        }
    }
    return null;
};
$personas = [
    'tenantadmin' => $pick("SELECT u.id FROM {user} u JOIN {role_assignments} ra ON ra.userid = u.id
        WHERE ra.roleid = :r AND ra.contextid = :c AND u.deleted = 0 AND u.suspended = 0 ORDER BY u.id",
        ['r' => $roleid, 'c' => $sys->id]),
    // A learner: in tenant 1, holds no tenant-admin role, supervises nobody.
    'learner_1' => $pick("SELECT u.id FROM {user} u WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 2
        AND (u.open_path = '/1' OR u.open_path LIKE '/1/%')
        AND NOT EXISTS (SELECT 1 FROM {role_assignments} ra WHERE ra.userid = u.id AND ra.roleid = :r)
        AND NOT EXISTS (SELECT 1 FROM {user} x WHERE x.open_supervisorid = u.id AND x.deleted = 0)
        ORDER BY u.id", ['r' => $roleid]),
    'learner_177' => $pick("SELECT u.id FROM {user} u WHERE u.deleted = 0 AND u.suspended = 0
        AND (u.open_path = '/177' OR u.open_path LIKE '/177/%') ORDER BY u.id", []),
    'linemanager' => $pick("SELECT DISTINCT m.id FROM {user} m JOIN {user} r ON r.open_supervisorid = m.id
        WHERE m.deleted = 0 AND m.suspended = 0 AND r.deleted = 0 ORDER BY m.id", []),
];

$summary = ['ok' => 0, 'refused' => 0, 'params' => 0, 'error' => 0];
foreach ($personas as $label => $userid) {
    if (!$userid) {
        echo "\n== {$label}: no such user on this site, skipped\n";
        continue;
    }
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    \core\cron::setup_user($user);
    $root = explode('/', trim((string) $user->open_path, '/'))[0] ?: '-';
    echo "\n== {$label} (id {$userid}, tenant {$root})\n";
    foreach ($functions as $fname) {
        // call_external_function() refuses login-required functions under CLI
        // (NO_MOODLE_COOKIES), so call execute() directly with its default
        // arguments and validate the result against its declared returns,
        // which also checks the return contract.
        try {
            $info = \core_external\external_api::external_function_info($fname);
            $class = $info->classname;
            $method = $info->methodname;
            $raw = $class::$method();
            $data = \core_external\external_api::clean_returnvalue($info->returns_desc, $raw);
            $r = ['error' => false, 'data' => $data];
        } catch (\moodle_exception $e) {
            $r = ['error' => true, 'exception' => (object) ['errorcode' => $e->errorcode,
                'message' => $e->getMessage() . (!empty($e->debuginfo) ? ' | ' . $e->debuginfo : '')]];
        } catch (\Throwable $e) {
            $r = ['error' => true, 'exception' => (object) ['errorcode' => get_class($e),
                'message' => $e->getMessage()]];
        }
        if (empty($r['error'])) {
            $data = $r['data'];
            $bits = [];
            if (is_array($data)) {
                foreach (['total', 'totalcount', 'count'] as $k) {
                    if (isset($data[$k]) && is_scalar($data[$k])) {
                        $bits[] = "{$k}=" . $data[$k];
                    }
                }
                foreach (['rows', 'items', 'records', 'data'] as $k) {
                    if (isset($data[$k]) && is_array($data[$k])) {
                        $bits[] = "{$k}=" . count($data[$k]);
                    }
                }
                if (!$bits && array_is_list($data)) {
                    $bits[] = 'list=' . count($data);
                }
            }
            $summary['ok']++;
            printf("  OK       %-58s %s\n", $fname, implode(' ', $bits));
            continue;
        }
        $code = (string) ($r['exception']->errorcode ?? 'unknown');
        $msg = (string) ($r['exception']->message ?? '');
        if (in_array($code, ['invalidparameter', 'missingparam', 'ArgumentCountError', 'invalidcourse',
                'invalidcourseid'], true)
                || stripos($msg, 'invalid parameter') !== false || stripos($msg, 'missing') !== false) {
            $summary['params']++;
            printf("  PARAMS   %-58s (needs arguments)\n", $fname);
        } else if (in_array($code, ['nopermissions', 'required_capability_exception', 'error_outoftenant',
                'accessdenied', 'notlocalisederrormessage', 'requireloginerror', 'invalidrecord'], true)) {
            $summary['refused']++;
            printf("  REFUSED  %-58s %s\n", $fname, $code);
        } else {
            $summary['error']++;
            // Error messages can carry SQL but never record content; trim to one line.
            printf("  ERROR    %-58s %s: %s\n", $fname, $code, substr(preg_replace('/\s+/', ' ', $msg), 0, 220));
        }
    }
}
\core\cron::setup_user();
echo "\nSUMMARY: " . count($functions) . " read functions x " . count(array_filter($personas)) . " personas; "
    . "ok={$summary['ok']} refused={$summary['refused']} needs-args={$summary['params']} ERROR={$summary['error']}\n";
