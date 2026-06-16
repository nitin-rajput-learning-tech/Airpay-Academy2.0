<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LTI 1.3 JWKS endpoint (consumer side — our public keys for tools to verify
 * our outgoing requests, e.g. service calls / deep-linking responses).
 *
 * Returns 404 unless the LTI feature flag is ON. The scaffold returns an
 * empty key set; a production build publishes the RSA public key(s) generated
 * for this platform here.
 *
 * @package local_sentientia_api
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/sentientia_api/lib.php');

if (!local_sentientia_api_lti_is_enabled()) {
    header('HTTP/1.1 404 Not Found');
    die();
}

header('Content-Type: application/json; charset=utf-8');
// Scaffold: empty key set. Production publishes generated RSA public keys.
echo json_encode(['keys' => []]);
die();
