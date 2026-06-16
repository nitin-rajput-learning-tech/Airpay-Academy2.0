<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LTI 1.3 OIDC login initiation endpoint (provider side — third-party
 * initiated login). The platform redirects the browser here first; we mint a
 * one-time nonce + state bound to the resolved registration, then redirect
 * back to the platform's authorization endpoint with those values.
 *
 * Returns 404 unless the LTI feature flag is ON.
 *
 * Required platform params (OIDC third-party init):
 *   iss            — platform issuer
 *   login_hint     — opaque hint echoed back
 *   target_link_uri— final launch URL
 *   client_id      — (optional) tool client id
 *
 * @package local_sentientia_api
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/sentientia_api/lib.php');

if (!local_sentientia_api_lti_is_enabled()) {
    header('HTTP/1.1 404 Not Found');
    die();
}

$iss            = required_param('iss', PARAM_RAW_TRIMMED);
$loginhint      = required_param('login_hint', PARAM_RAW_TRIMMED);
$targetlinkuri  = required_param('target_link_uri', PARAM_URL);
$clientid       = optional_param('client_id', '', PARAM_RAW_TRIMMED);

// Resolve the tenant from the current session if any; LTI login is pre-auth,
// so admins must scope registrations per tenant. We attempt a global match
// (costcenterid 0 = any) and let registration::find narrow by client id.
$costcenterid = 0;
if (!empty($USER->id)) {
    $costcenterid = \local_sentientia_platform\tenant::root_for_current_user();
}

$reg = null;
if ($clientid !== '') {
    $reg = \local_sentientia_api\lti\registration::find($iss, $clientid, $costcenterid);
} else {
    // Best-effort: a single enabled registration for this issuer in the tenant.
    global $DB;
    $conditions = ['issuer' => $iss, 'enabled' => 1];
    if ($costcenterid > 0) {
        $conditions['costcenterid'] = $costcenterid;
    }
    $recs = $DB->get_records('local_sentientia_api_lti_reg', $conditions, '', '*', 0, 2);
    if (count($recs) === 1) {
        $reg = reset($recs);
    }
}

if (!$reg) {
    throw new moodle_exception('lti_no_registration', 'local_sentientia_api');
}

$nonceinfo = \local_sentientia_api\lti\registration::new_nonce((int) $reg->id);

if (empty($reg->authloginurl)) {
    throw new moodle_exception('lti_no_authurl', 'local_sentientia_api');
}

// Build the OIDC auth request back to the platform.
$redirecturi = new moodle_url('/local/sentientia_api/lti/launch.php');
$authparams = [
    'scope'            => 'openid',
    'response_type'    => 'id_token',
    'response_mode'    => 'form_post',
    'prompt'           => 'none',
    'client_id'        => $reg->clientid,
    'redirect_uri'     => $redirecturi->out(false),
    'login_hint'       => $loginhint,
    'state'            => $nonceinfo['state'],
    'nonce'            => $nonceinfo['nonce'],
];
$authurl = new moodle_url($reg->authloginurl, $authparams);
redirect($authurl);
