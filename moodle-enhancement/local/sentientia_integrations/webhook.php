<?php
/**
 * KeKa HRMS Webhook Endpoint.
 *
 * Receives JML (Joiner-Mover-Leaver) events from KeKa and processes them.
 * URL: https://www.airpay.academy/local/sentientia_integrations/webhook.php
 *
 * 2026-08-07 hardening:
 *  - Gated behind keka_client::webhook_enabled() — the platform flag
 *    sentientia.hrms.webhook.enabled (default OFF) AND the hrms_enable
 *    admin setting. Previously the endpoint was live the moment
 *    webhook_secret was configured.
 *  - Secret comparison uses hash_equals() (constant-time; the old !==
 *    compare leaked timing information).
 *  - The ?secret= GET-param path is GONE — query strings end up in access
 *    logs, proxies, and browser history. Header X-Webhook-Secret only.
 *  - Log row is updated by its insert id (the old MAX(id) lookup raced
 *    under concurrent deliveries) and failures now record errormsg.
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');

// Gate 1: feature flag (default OFF) + hrms_enable admin setting.
if (!\local_sentientia_integrations\keka_client::webhook_enabled()) {
    http_response_code(403);
    echo json_encode(['error' => 'HRMS webhook is disabled']);
    exit;
}

// Gate 2: shared-secret auth — X-Webhook-Secret header ONLY, compared in
// constant time. A ?secret= query param is deliberately NOT accepted.
$secret = (string) get_config('local_sentientia_integrations', 'webhook_secret');
$provided = (string) ($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '');

if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Parse incoming payload.
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (empty($payload) || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$event_type = substr((string) ($payload['event'] ?? $payload['eventType'] ?? 'unknown'), 0, 100);

// Log the webhook (append-only audit trail, replayable).
$logid = $DB->insert_record('local_sentientia_integration_log', (object) [
    'source'      => 'keka_webhook',
    'event_type'  => $event_type,
    'payload'     => $input,
    'status'      => 'received',
    'timecreated' => time(),
]);

// Process the event.
try {
    $result = \local_sentientia_integrations\keka_client::handle_webhook($event_type, $payload);
} catch (\Throwable $e) {
    $result = ['success' => false, 'message' => 'Unhandled error: ' . $e->getMessage()];
}

// Update log status by the insert id (no MAX(id) race).
$DB->update_record('local_sentientia_integration_log', (object) [
    'id'       => $logid,
    'status'   => $result['success'] ? 'processed' : 'failed',
    'errormsg' => $result['success'] ? null : ($result['message'] ?? null),
]);

http_response_code($result['success'] ? 200 : 422);
echo json_encode($result);
