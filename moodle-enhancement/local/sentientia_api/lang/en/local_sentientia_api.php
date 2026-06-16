<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English strings for local_sentientia_api.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Public API';

// Capabilities.
$string['sentientia_api:read']   = 'Read the Sentientia public API (courses, enrolments, completions, skills)';
$string['sentientia_api:write']  = 'Perform write operations via the Sentientia public API';
$string['sentientia_api:manage'] = 'Manage Sentientia API and LTI registrations';
$string['sentientia_api:lti']    = 'Act as an LTI 1.3 launch endpoint';

// API state / errors.
$string['api_disabled']       = 'The Sentientia public API is currently disabled.';
$string['api_write_disabled'] = 'Write operations on the Sentientia public API are currently disabled.';
$string['api_enabled_notice'] = 'The Sentientia public API (v1) is enabled.';
$string['ratelimited']        = 'Rate limit exceeded. The budget is {$a} requests per window. Try again shortly.';
$string['error_notenant']     = 'Your account is not associated with a valid tenant; API access is denied.';
$string['error_notauthenticated'] = 'Authentication is required to call the Sentientia public API.';
$string['error_no_manual_enrol'] = 'Manual enrolment is not available on this course.';

// Landing page.
$string['rest_base']    = 'REST base URL';
$string['v1_endpoints'] = 'v1 endpoints';
$string['lti_status']   = 'LTI 1.3 status';
$string['lti_enabled']  = 'LTI 1.3 is enabled.';
$string['lti_disabled'] = 'LTI 1.3 is disabled.';

// LTI launch.
$string['lti_launch_title']    = 'LTI 1.3 launch';
$string['lti_launch_verified'] = 'LTI launch verified successfully.';
$string['lti_message_type']    = 'Message type';

// LTI errors.
$string['lti_invalid_token']   = 'The LTI token is malformed.';
$string['lti_bad_alg']         = 'Unsupported LTI token signing algorithm (RS256 required).';
$string['lti_no_key']          = 'No verification key is available for this LTI registration.';
$string['lti_bad_signature']   = 'The LTI token signature could not be verified.';
$string['lti_bad_iss']         = 'The LTI token issuer does not match the registration.';
$string['lti_bad_aud']         = 'The LTI token audience does not match this tool.';
$string['lti_expired']         = 'The LTI token has expired.';
$string['lti_bad_iat']         = 'The LTI token issued-at time is invalid.';
$string['lti_bad_nonce']       = 'The LTI token nonce is invalid, missing, or already used.';
$string['lti_no_registration'] = 'No matching LTI registration was found for this launch.';
$string['lti_no_authurl']      = 'The LTI registration has no authentication login URL configured.';

// Settings.
$string['setting_ratelimit_heading'] = 'Rate limiting';
$string['setting_ratelimit_desc']    = 'Fixed-window per-user rate limiting for the public API.';
$string['setting_rate_limit']        = 'Requests per window';
$string['setting_rate_limit_desc']   = 'Maximum number of API requests a single user may make within one window.';
$string['setting_rate_window']       = 'Window length (seconds)';
$string['setting_rate_window_desc']  = 'Length of the rate-limit window in seconds.';
$string['setting_log_retention']     = 'Request-log retention (days)';
$string['setting_log_retention_desc'] = 'Number of days to keep API request-log rows before the cleanup task prunes them.';

// Scheduled task.
$string['task_cleanup'] = 'Sentientia API cleanup (rate counters, LTI nonces, request log)';

// Privacy.
$string['privacy:metadata:log']              = 'Append-only log of public-API requests.';
$string['privacy:metadata:log:userid']       = 'The user who made the request.';
$string['privacy:metadata:log:endpoint']     = 'The API endpoint invoked.';
$string['privacy:metadata:log:status']       = 'The logical status of the request.';
$string['privacy:metadata:log:timecreated']  = 'When the request was made.';
$string['privacy:metadata:rate']             = 'Per-user rate-limit counters.';
$string['privacy:metadata:rate:userid']      = 'The user the counter belongs to.';
$string['privacy:metadata:rate:hits']        = 'Number of requests in the current window.';
$string['privacy:metadata:rate:windowstart'] = 'Start time of the current rate-limit window.';
