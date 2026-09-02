<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SCIM 2.0 endpoint — /local/sentientia_api/scim/v2.php{/Users,/Users/{id},...}
 *
 * IdP "Tenant URL": https://<site>/local/sentientia_api/scim/v2.php
 * Routing uses PATH_INFO (Apache: AcceptPathInfo On); a ?path= fallback is
 * accepted for servers that strip it. Bearer auth only; real HTTP status codes
 * (this is why SCIM is not a Moodle web-service function — see ADR-030).
 *
 * @package local_sentientia_api
 */

define('NO_MOODLE_COOKIES', true);   // Stateless, server-to-server.
define('AJAX_SCRIPT', true);         // No HTML error pages.

require_once(__DIR__ . '/../../../config.php');

$pathinfo = (string) ($_SERVER['PATH_INFO'] ?? '');
if ($pathinfo === '') {
    $pathinfo = optional_param('path', '/', PARAM_RAW_TRIMMED);
}
$query = [
    'filter'     => optional_param('filter', '', PARAM_RAW_TRIMMED),
    'startIndex' => optional_param('startIndex', 1, PARAM_INT),
    'count'      => optional_param('count', 100, PARAM_INT),
];
$method  = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$rawbody = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? (string) file_get_contents('php://input') : null;
$auth    = \local_sentientia_api\scim\authenticator::header_from_server($_SERVER);
$baseurl = (new moodle_url('/local/sentientia_api/scim/v2.php'))->out(false);

$resp = (new \local_sentientia_api\scim\handler($baseurl))->handle($method, $pathinfo, $query, $rawbody, $auth);

http_response_code((int) $resp['status']);
foreach ($resp['headers'] as $h => $v) {
    header($h . ': ' . $v);
}
if ($resp['body'] !== null) {
    header('Content-Type: ' . \local_sentientia_api\scim\response::CONTENT_TYPE);
    echo json_encode($resp['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
exit;
