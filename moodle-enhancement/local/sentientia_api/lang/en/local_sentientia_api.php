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

// ── Outbound webhooks (ADR-030 Wave A) ──────────────────────────────────
$string['sentientia_api:webhooks_manage'] = 'Manage outbound webhook subscriptions and the delivery log';
$string['webhooks_title']         = 'Outbound webhooks';
$string['webhooks_intro']         = 'Register https endpoints that receive signed JSON POSTs when learning events happen. Each subscription has its own HMAC-SHA256 secret (header X-Sentientia-Signature: t=&lt;unix&gt;,v1=&lt;hmac of "t.body"&gt;; reject if older than 5 minutes). Deliveries retry with exponential backoff and are dead-lettered after 5 attempts.';
$string['webhooks_subscriptions'] = 'Subscriptions';
$string['webhooks_deliveries']    = 'Recent deliveries';
$string['webhooks_none']          = 'No subscriptions yet.';
$string['webhooks_nodeliveries']  = 'No deliveries yet.';
$string['webhook_name']           = 'Name';
$string['webhook_url']            = 'Endpoint URL (https)';
$string['webhook_events']         = 'Events';
$string['webhook_event']          = 'Event';
$string['webhook_tenant']         = 'Tenant root id';
$string['webhook_tenant_help']    = 'Restrict this subscription to one tenant root id, or 0 to receive events from every tenant of the customer.';
$string['webhook_enabled']        = 'Enabled';
$string['webhook_lastsuccess']    = 'Last success';
$string['webhook_lastfailure']    = 'Last failure';
$string['webhook_status']         = 'Status';
$string['webhook_attempts']       = 'Attempts';
$string['webhook_nextattempt']    = 'Next attempt';
$string['webhook_httpstatus']     = 'HTTP';
$string['webhook_lasterror']      = 'Last error';
$string['webhook_all_tenants']    = 'All tenants';
$string['webhook_never']          = 'Never';
$string['webhook_add']            = 'Add subscription';
$string['webhook_created']        = 'Subscription created.';
$string['webhook_deleted']        = 'Subscription and its delivery history deleted.';
$string['webhook_toggled']        = 'Subscription updated.';
$string['webhook_retried']        = 'Delivery re-queued for immediate retry.';
$string['webhook_secret_shown']   = 'Signing secret for "{$a}" (shown once - store it in the receiving system now):';
$string['webhook_action_enable']  = 'Enable';
$string['webhook_action_disable'] = 'Disable';
$string['webhook_action_delete']  = 'Delete';
$string['webhook_action_rotate']  = 'Rotate secret';
$string['webhook_action_retry']   = 'Retry';
$string['webhook_confirm_delete'] = 'Delete this subscription and all of its delivery history?';
$string['webhook_counts']         = 'Deliveries - queued: {$a->queued}, sent: {$a->sent}, failed (retrying): {$a->failed}, dead: {$a->dead}';
$string['webhook_flag_off_notice'] = 'The outbound-webhooks feature flag is OFF in the global scope. Subscriptions are stored, but nothing is queued or sent until sentientia.api.enabled and sentientia.api.webhooks.enabled are switched on for the relevant customer/tenant.';
$string['webhook_name_required']  = 'A subscription name is required.';
$string['webhook_events_required'] = 'Select at least one event.';
$string['webhook_url_invalid']    = 'The endpoint must be an absolute https:// URL.';
$string['webhook_url_blocked']    = 'That endpoint host is blocked by the site\'s outbound-request security policy (private or internal address).';
$string['event_course_completed']   = 'Course completed';
$string['event_enrolment_created']  = 'Enrolment created';
$string['event_certificate_issued'] = 'Certificate issued';
$string['task_webhook_drain']     = 'Sentientia API outbound webhook delivery';
$string['privacy:metadata:whdel']             = 'Queued and delivered outbound webhook events.';
$string['privacy:metadata:whdel:userid']      = 'The user the event concerns.';
$string['privacy:metadata:whdel:eventkey']    = 'The type of learning event.';
$string['privacy:metadata:whdel:status']      = 'Delivery status of the event.';
$string['privacy:metadata:whdel:timecreated'] = 'When the event was queued.';
$string['privacy:metadata:webhook_endpoint']  = 'Event metadata (user id, course id, timestamps - no names or emails) is POSTed to customer-registered https endpoints.';
