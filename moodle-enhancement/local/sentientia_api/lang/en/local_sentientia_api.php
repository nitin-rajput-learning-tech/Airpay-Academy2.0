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

// ── SCIM 2.0 provisioning (ADR-030 Wave B) ──────────────────────────────
$string['sentientia_api:scim_manage'] = 'Manage SCIM 2.0 provisioning clients';
$string['scim_title']              = 'SCIM 2.0 provisioning clients';
$string['scim_intro']              = 'Register one client per identity provider (Entra ID, Okta). Each client receives a bearer token (shown once) and is bound to a tenant: users it creates land in that tenant, and it can only see, update or deactivate users inside it. Deactivation (SCIM DELETE or active=false) suspends the account and ends its sessions; learning history is retained.';
$string['scim_endpoint_url']       = 'Tenant URL for the identity provider';
$string['scim_clients']            = 'Clients';
$string['scim_none']               = 'No SCIM clients yet.';
$string['scim_client_name']        = 'Name';
$string['scim_client_tenant']      = 'Tenant root id';
$string['scim_client_tenant_help'] = 'Users provisioned by this client are placed in this tenant root and the client is confined to it. 0 = site-level client (all tenants) - use only for the platform operator.';
$string['scim_client_auth']        = 'Authentication method for created users';
$string['scim_client_auth_help']   = 'The Moodle authentication plugin assigned to accounts this client creates - normally the single-sign-on method the same identity provider offers (oauth2 / oidc / saml2). Users then sign in through the IdP; no password is set.';
$string['scim_client_ratelimit']   = 'Requests per window';
$string['scim_client_ratelimit_help'] = 'Per-client request budget for one rate-limit window (the window length is the plugin-wide setting). 0 uses the plugin-wide budget.';
$string['scim_client_enabled']     = 'Enabled';
$string['scim_client_lastseen']    = 'Last seen';
$string['scim_mappings']           = 'Mapped users';
$string['scim_client_add']         = 'Add client';
$string['scim_client_created']     = 'Client created.';
$string['scim_client_deleted']     = 'Client and its externalId mappings deleted (user accounts are untouched).';
$string['scim_client_toggled']     = 'Client updated.';
$string['scim_client_token_shown'] = 'Bearer token for "{$a}" (shown once - paste it into the identity provider now):';
$string['scim_action_enable']      = 'Enable';
$string['scim_action_disable']     = 'Disable';
$string['scim_action_delete']      = 'Delete';
$string['scim_action_rotate']      = 'Rotate token';
$string['scim_confirm_delete']     = 'Delete this client? Its token stops working immediately and its externalId mappings are removed. User accounts are not affected.';
$string['scim_client_name_required'] = 'A client name is required.';
$string['scim_client_auth_invalid']  = 'That authentication method is not permitted for SCIM-created users.';
$string['scim_flag_off_notice']    = 'The SCIM feature flag is OFF in the global scope. The endpoint answers 503 until sentientia.api.enabled and sentientia.api.scim.enabled are switched on for the relevant customer/tenant.';
$string['scim_unauthorized']       = 'A valid bearer token is required.';
$string['scim_disabled']           = 'SCIM provisioning is not enabled for this client.';
$string['scim_notfound']           = 'Resource not found.';
$string['scim_conflict_username']  = 'A user with this userName already exists.';
$string['scim_conflict_email']     = 'A user with this email address already exists.';
$string['scim_bad_json']           = 'The request body must be a JSON object.';
$string['scim_internal']           = 'The provisioning request could not be completed.';
$string['privacy:metadata:scimmap']             = 'Links between Sentientia users and the identifier assigned by an external identity provider.';
$string['privacy:metadata:scimmap:userid']      = 'The Sentientia user.';
$string['privacy:metadata:scimmap:externalid']  = 'The identity provider\'s identifier for the user.';
$string['privacy:metadata:scimmap:timecreated'] = 'When the link was created.';

// ── SCIM Groups + attestation (ADR-030 Wave C) ──────────────────────────
$string['scim_events']            = 'Provisioning events (attestation)';
$string['scim_events_intro']      = 'Every account the identity provider created, reactivated, deactivated, updated or moved through SCIM, with time and client - the deprovisioning evidence auditors ask for.';
$string['scim_events_none']       = 'No provisioning events yet.';
$string['scim_event_time']        = 'When (UTC)';
$string['scim_event_action']      = 'Action';
$string['scim_event_client']      = 'Client';
$string['scim_event_user']        = 'User';
$string['scim_event_external']    = 'externalId';
$string['scim_event_detail']      = 'Detail';
$string['scim_export_csv']        = 'Download attestation CSV';
$string['scim_action_created']     = 'Created';
$string['scim_action_reactivated'] = 'Reactivated';
$string['scim_action_deactivated'] = 'Deactivated';
$string['scim_action_updated']     = 'Updated';
$string['scim_action_moved']       = 'Moved group';
$string['scim_groups_readonly']    = 'Groups mirror the organisation tree and cannot be created, renamed or deleted through SCIM; manage the hierarchy in the Organisation administration. Membership changes are accepted.';
$string['scim_groups_unavailable'] = 'Group membership requires the organisation-tree user columns, which this site does not have.';
$string['scim_member_outside_scope'] = 'One or more members are not in this client\'s tenant.';
$string['privacy:metadata:scimevt']             = 'Attestation log of account changes made by identity providers through SCIM.';
$string['privacy:metadata:scimevt:userid']      = 'The affected user.';
$string['privacy:metadata:scimevt:action']      = 'What the identity provider did (created, reactivated, deactivated, updated, moved).';
$string['privacy:metadata:scimevt:externalid']  = 'The identity provider\'s identifier at the time.';
$string['privacy:metadata:scimevt:timecreated'] = 'When it happened.';
