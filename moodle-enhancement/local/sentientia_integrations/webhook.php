<?php
/**
 * KeKa HRMS Webhook Endpoint.
 *
 * Receives JML (Joiner-Mover-Leaver) events from KeKa and processes them.
 * URL: https://www.airpay.academy/local/sentientia_integrations/webhook.php
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Verify webhook secret.
$secret = get_config('local_sentientia_integrations', 'webhook_secret');
$provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '';

if (empty($secret) || $provided !== $secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Parse incoming payload.
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$event_type = $payload['event'] ?? $payload['eventType'] ?? $_GET['event'] ?? 'unknown';

// Log the webhook.
$DB->insert_record('local_sentientia_integration_log', (object)[
    'source'      => 'keka_webhook',
    'event_type'  => $event_type,
    'payload'     => $input,
    'status'      => 'received',
    'timecreated' => time(),
]);

// Process the event.
$result = \local_sentientia_integrations\keka_client::handle_webhook($event_type, $payload);

// Update log status.
$logid = $DB->get_field_sql(
    "SELECT MAX(id) FROM {local_sentientia_integration_log} WHERE source = 'keka_webhook'");
if ($logid) {
    $DB->set_field('local_sentientia_integration_log', 'status',
        $result['success'] ? 'processed' : 'failed', ['id' => $logid]);
}

http_response_code($result['success'] ? 200 : 422);
echo json_encode($result);
