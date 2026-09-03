<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * xAPI LRS endpoint — /local/sentientia_xapi/lrs/statements.php
 *
 * Implements the xAPI 1.0.3 Statements Resource:
 *   GET  /statements?statementId=<UUID>   → retrieve one statement
 *   GET  /statements                       → retrieve paged statements
 *   POST /statements                       → store one statement (or array)
 *   PUT  /statements?statementId=<UUID>    → store one statement with known id
 *
 * Authentication: Bearer token or HTTP Basic (see lrs/authenticator.php).
 * Tenant: derived from the authenticated credential's costcenterid.
 * Validation: all inbound statements pass through statement_validator.php.
 *
 * The endpoint is only available when BOTH:
 *   1. sentientia.xapi.enabled = ON
 *   2. sentientia.xapi.lrs_endpoint_enabled = ON
 *
 * Returns JSON with Content-Type: application/json.
 * Errors use HTTP status codes per xAPI spec §7.7.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No require_login() — xAPI clients authenticate via Bearer/Basic.
// We do need Moodle bootstrap.
define('NO_MOODLE_COOKIES', true);  // Stateless endpoint.
define('AJAX_SCRIPT', true);        // Suppresses HTML error pages.

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/sentientia_xapi/lib.php');

use local_sentientia_xapi\lrs\authenticator;
use local_sentientia_xapi\lrs\store;
use local_sentientia_xapi\lrs\cmi5_tracker;
use local_sentientia_xapi\lrs\rate_limiter;
use local_sentientia_xapi\lrs\rate_limit_exceeded;
use local_sentientia_xapi\model\statement;
use local_sentientia_xapi\validator\statement_validator;

/**
 * Send a JSON response and exit.
 *
 * @param mixed $data   Data to encode.
 * @param int   $status HTTP status code.
 */
function lrs_respond($data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Experience-API-Version: 1.0.3');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Feature flag gate ────────────────────────────────────────────────────────
if (class_exists('\local_sentientia_platform\feature_flags')) {
    $flags = \local_sentientia_platform\feature_flags::class;
    if (!$flags::is_enabled('sentientia.xapi.enabled')
            || !$flags::is_enabled('sentientia.xapi.lrs_endpoint_enabled')) {
        lrs_respond(['error' => get_string('error_lrs_disabled', 'local_sentientia_xapi')], 503);
    }
} else {
    // Platform plugin not installed — refuse all requests.
    lrs_respond(['error' => get_string('error_lrs_disabled', 'local_sentientia_xapi')], 503);
}

// ─── Authentication ────────────────────────────────────────────────────────────
// Gate order (fail closed, mirrors local_sentientia_api's SCIM handler):
// authenticate → 401 if not ok → per-client rate limit → route. Unauthenticated
// callers always see 401 first — the rate limiter never runs for them.
$auth   = (new authenticator())->authenticate_request();
if (!$auth['ok']) {
    header('WWW-Authenticate: Bearer realm="Sentientia LRS"');
    lrs_respond(['error' => get_string('error_lrs_auth', 'local_sentientia_xapi')], 401);
}
$costcenterid = (int) $auth['costcenterid'];

// ─── Rate limit (H3 fix — UAT-SECURITY-POSTURE-2026-09-03) ─────────────────────
// Per-client fixed-window limit, checked before any statement body is parsed
// or stored — mirrors local_sentientia_api\scim\client::rate_check(), which
// gates the SCIM endpoint in this same plugin family the same way.
try {
    rate_limiter::check_and_increment((int) $auth['clientid']);
} catch (rate_limit_exceeded $e) {
    header('Retry-After: ' . $e->retryafter);
    lrs_respond(['error' => $e->getMessage()], 429);
}

// ─── Route by HTTP method ──────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$lrs    = new store();

// ─── GET /statements ──────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt_id = optional_param('statementId', '', PARAM_ALPHANUMEXT);

    if (!empty($stmt_id)) {
        // Single statement lookup.
        if (!statement::is_valid_uuid($stmt_id)) {
            lrs_respond(['error' => 'Invalid statementId format.'], 400);
        }
        $row = $lrs->get($stmt_id, $costcenterid);
        if (!$row) {
            lrs_respond(['error' => get_string('error_lrs_not_found', 'local_sentientia_xapi')], 404);
        }
        $stmt_out = json_decode($row->actor, true);  // Rebuild full statement.
        lrs_respond([
            'id'        => $row->statementid,
            'actor'     => json_decode($row->actor, true),
            'verb'      => ['id' => $row->verb, 'display' => ['en-US' => $row->verbdisplay ?? '']],
            'object'    => json_decode($row->object, true),
            'result'    => $row->result ? json_decode($row->result, true) : null,
            'context'   => $row->context ? json_decode($row->context, true) : null,
            'timestamp' => $row->timestamp ? date('c', $row->timestamp) : null,
            'stored'    => date('c', $row->timestored),   // xAPI JSON key stays 'stored'; DB column is timestored.
        ]);
    }

    // Paged statement list.
    $limit  = min((int) optional_param('limit', 50, PARAM_INT), 500);
    $offset = (int) optional_param('offset', 0, PARAM_INT);

    $rows = $lrs->get_statements($costcenterid, [], $limit, $offset);
    $out  = [];
    foreach ($rows as $row) {
        $out[] = [
            'id'        => $row->statementid,
            'verb'      => $row->verb,
            'objectId'  => $row->objectid,
            'stored'    => date('c', $row->timestored),   // xAPI JSON key stays 'stored'; DB column is timestored.
            'voided'    => (bool) $row->voided,
        ];
    }
    lrs_respond(['statements' => $out, 'total' => count($out)]);
}

// ─── POST /statements — store one or many ────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    $raw_body = file_get_contents('php://input');
    if (empty($raw_body)) {
        lrs_respond(['error' => get_string('error_lrs_invalid_json', 'local_sentientia_xapi')], 400);
    }

    $decoded = json_decode($raw_body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        lrs_respond(['error' => get_string('error_lrs_invalid_json', 'local_sentientia_xapi')], 400);
    }

    // Support single statement or array of statements.
    $is_array = isset($decoded[0]) || $decoded === [];
    $stmts    = $is_array ? $decoded : [$decoded];

    // For PUT, the statementId MUST match the URL param.
    if ($method === 'PUT') {
        $put_id = optional_param('statementId', '', PARAM_ALPHANUMEXT);
        if (empty($put_id) || !statement::is_valid_uuid($put_id)) {
            lrs_respond(['error' => 'PUT requires a valid statementId query parameter.'], 400);
        }
        if (!empty($stmts[0]['id']) && $stmts[0]['id'] !== $put_id) {
            lrs_respond(['error' => 'PUT statementId query param must match statement body id.'], 409);
        }
        $stmts[0]['id'] = $put_id;
    }

    $validator = new statement_validator();
    $cmi5      = new cmi5_tracker();
    $cmi5_on   = class_exists('\local_sentientia_platform\feature_flags')
        && \local_sentientia_platform\feature_flags::is_enabled('sentientia.xapi.cmi5_enabled');

    $stored_ids = [];

    foreach ($stmts as $stmt_data) {
        // Validate.
        if (!$validator->validate($stmt_data)) {
            $errs = $validator->errors_as_string();
            lrs_respond(['error' => get_string('error_lrs_invalid_stmt', 'local_sentientia_xapi', $errs)], 400);
        }

        $stmt_obj = new statement($stmt_data);

        // Resolve actor to Moodle userid.
        $actor_data = $stmt_data['actor'] ?? [];
        $actorid    = $lrs->resolve_actor_userid($actor_data);

        // Store.
        $uuid         = $lrs->put($stmt_obj, $costcenterid, $actorid, store::SOURCE_LRS);
        $stored_ids[] = $uuid;

        // cmi5 tracking.
        if ($cmi5_on && $actorid !== null) {
            $cmi5->process($stmt_data, $costcenterid, $actorid);
        }
    }

    $status = $method === 'PUT' ? 204 : 200;
    lrs_respond($is_array ? $stored_ids : ($stored_ids[0] ?? null), $status);
}

// ─── Method not supported ─────────────────────────────────────────────────────
lrs_respond(['error' => get_string('error_lrs_method', 'local_sentientia_xapi')], 405);
