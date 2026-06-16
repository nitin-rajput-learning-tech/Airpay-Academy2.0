<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English language strings — local_sentientia_xapi.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Sentientia xAPI / LRS';

// ─── Admin settings ──────────────────────────────────────────────────────────
$string['settings_pagetitle']          = 'Sentientia xAPI / LRS Settings';
$string['setting_lrs_token']           = 'LRS Bearer token';
$string['setting_lrs_token_desc']      = 'Secret token for authenticating external xAPI clients (e.g. SCORM content, cmi5 AU). Minimum 32 characters. Keep secret — rotate after any suspected exposure.';
$string['setting_lrs_basic_user']      = 'LRS basic-auth username';
$string['setting_lrs_basic_user_desc'] = 'Optional HTTP Basic authentication username accepted by the /lrs/statements endpoint in addition to the Bearer token. Leave blank to disable basic-auth.';
$string['setting_lrs_basic_pass']      = 'LRS basic-auth password';
$string['setting_lrs_basic_pass_desc'] = 'HTTP Basic authentication password (pair with username above). Leave blank to disable basic-auth.';
$string['setting_retention_days']      = 'Statement retention (days)';
$string['setting_retention_days_desc'] = 'xAPI statements older than this many days are purged by the nightly cleanup task. Set 0 to keep forever. Default: 730 (2 years).';
$string['setting_emit_login']          = 'Emit statement on login';
$string['setting_emit_login_desc']     = 'When ON, a "experienced" verb statement is emitted whenever a user logs in. High-volume sites may turn this off.';

// ─── Capability strings ───────────────────────────────────────────────────────
$string['xapi:viewstatements']  = 'View xAPI statements in the LRS viewer';
$string['xapi:deletestatements'] = 'Delete xAPI statements from the LRS';
$string['xapi:managelrs']       = 'Manage LRS settings and credentials';

// ─── LRS endpoint messages ────────────────────────────────────────────────────
$string['error_lrs_disabled']       = 'The xAPI LRS is currently disabled. Contact your administrator.';
$string['error_lrs_auth']           = 'Authentication failed. Provide a valid Bearer token or Basic credentials.';
$string['error_lrs_invalid_json']   = 'Request body is not valid JSON.';
$string['error_lrs_invalid_stmt']   = 'Statement validation failed: {$a}';
$string['error_lrs_tenant']         = 'Tenant resolution failed. Ensure the actor account homePage matches a registered tenant.';
$string['error_lrs_method']         = 'HTTP method not supported on this endpoint.';
$string['error_lrs_not_found']      = 'Statement not found.';

// ─── Statement model / verbs ──────────────────────────────────────────────────
$string['verb_completed']   = 'completed';
$string['verb_experienced'] = 'experienced';
$string['verb_passed']      = 'passed';
$string['verb_failed']      = 'failed';
$string['verb_attempted']   = 'attempted';
$string['verb_answered']    = 'answered';
$string['verb_launched']    = 'launched';
$string['verb_initialized'] = 'initialized';
$string['verb_terminated']  = 'terminated';
$string['verb_suspended']   = 'suspended';
$string['verb_resumed']     = 'resumed';
$string['verb_satisfied']   = 'satisfied';

// ─── Validation messages ──────────────────────────────────────────────────────
$string['validate_actor_required']           = 'Actor is required.';
$string['validate_actor_missing_objecttype'] = 'Actor must have objectType Agent or Group.';
$string['validate_actor_missing_ifi']        = 'Actor must have exactly one IFI (mbox, mbox_sha1sum, openid, or account).';
$string['validate_actor_mbox_format']        = 'Actor mbox must be a mailto: URI.';
$string['validate_actor_account_missing']    = 'Actor account must have homePage and name.';
$string['validate_verb_required']            = 'Verb is required.';
$string['validate_verb_id_required']         = 'Verb must have an id (IRI).';
$string['validate_verb_id_iri']              = 'Verb id must be a valid IRI.';
$string['validate_object_required']          = 'Object is required.';
$string['validate_object_id_required']       = 'Object must have an id (IRI).';
$string['validate_object_id_iri']            = 'Object id must be a valid IRI.';
$string['validate_result_score_range']       = 'Result score scaled must be between -1.0 and 1.0.';
$string['validate_result_score_raw_max']     = 'Result score raw must not exceed max.';
$string['validate_context_registration_uuid'] = 'Context registration must be a valid UUID.';
$string['validate_timestamp_format']         = 'Timestamp must be a valid ISO 8601 date-time string.';
$string['validate_id_uuid']                  = 'Statement id must be a valid UUID.';

// ─── cmi5 strings ─────────────────────────────────────────────────────────────
$string['cmi5_session']           = 'cmi5 session';
$string['cmi5_session_initialized'] = 'Session initialized';
$string['cmi5_session_terminated']  = 'Session terminated';
$string['cmi5_au_passed']           = 'Assignable Unit passed';
$string['cmi5_au_failed']           = 'Assignable Unit failed';
$string['cmi5_au_completed']        = 'Assignable Unit completed';

// ─── Admin UI ─────────────────────────────────────────────────────────────────
$string['lrs_viewer_title']      = 'xAPI Statement Viewer';
$string['lrs_viewer_heading']    = 'LRS — Recent Statements';
$string['lrs_col_timestamp']     = 'Timestamp';
$string['lrs_col_actor']         = 'Actor';
$string['lrs_col_verb']          = 'Verb';
$string['lrs_col_object']        = 'Object';
$string['lrs_col_score']         = 'Score';
$string['lrs_col_success']       = 'Success';
$string['lrs_col_tenant']        = 'Tenant';
$string['lrs_no_statements']     = 'No statements recorded yet.';
$string['lrs_endpoint_label']    = 'LRS endpoint URL';
$string['lrs_endpoint_desc']     = 'Use this URL as the LRS endpoint in your xAPI / cmi5 content or LRS client.';

// ─── Privacy ──────────────────────────────────────────────────────────────────
$string['privacy:metadata:local_sentientia_xapi_statements']          = 'xAPI statements emitted by or for this user.';
$string['privacy:metadata:local_sentientia_xapi_statements:actorid']  = 'Moodle user id linked to the xAPI actor.';
$string['privacy:metadata:local_sentientia_xapi_statements:actor']    = 'JSON actor object (may contain email or account identifier).';
$string['privacy:metadata:local_sentientia_xapi_statements:verb']     = 'xAPI verb IRI.';
$string['privacy:metadata:local_sentientia_xapi_statements:object']   = 'JSON object (activity, agent, etc.).';
$string['privacy:metadata:local_sentientia_xapi_statements:result']   = 'JSON result (score, success, completion).';
$string['privacy:metadata:local_sentientia_xapi_statements:context']  = 'JSON context.';
$string['privacy:metadata:local_sentientia_xapi_statements:stored']   = 'Timestamp when the statement was stored in the LRS.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions']              = 'cmi5 session tracking records.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:userid']       = 'Moodle user id.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:registration'] = 'cmi5 registration UUID.';

// ─── Scheduled tasks ─────────────────────────────────────────────────────────
$string['task_purge_old_statements'] = 'Purge old xAPI statements';
