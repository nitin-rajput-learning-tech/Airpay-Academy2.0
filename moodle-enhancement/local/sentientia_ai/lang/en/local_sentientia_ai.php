<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English strings for local_sentientia_ai (the AI gateway).
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia AI Gateway';

// Capabilities.
$string['sentientia_ai:viewledger'] = 'View the AI spend ledger';
$string['sentientia_ai:manage'] = 'Manage AI gateway runtime controls';

// Settings.
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'The ONE central key every Sentientia AI feature uses. Per-plugin legacy keys keep working as a fallback during migration; consolidate onto this key and clear the legacy ones.';
$string['setting_api_key'] = 'API key';
$string['setting_api_key_desc'] = 'Anthropic API key. Never committed, never logged, never included in error messages. Live calls additionally require both gateway feature flags ON and quota headroom.';
$string['setting_default_model'] = 'Default model';
$string['setting_default_model_desc'] = 'Model used when the calling feature does not specify one (e.g. claude-sonnet-4-6).';
$string['settings_heading_quotas'] = 'Fail-closed spend quotas';
$string['settings_heading_quotas_desc'] = 'Hard ceilings enforced BEFORE every live call, from the spend ledger. A zero or empty value means NO live calls (never unlimited) — the approved AI budget is a hard ceiling by decision (memo 2026-08-04, Addendum A). Windows are server-local calendar days/months. Costs are pricing-map ESTIMATES for budgeting, not invoices.';
$string['setting_daily_tokens_global'] = 'Daily token cap (global)';
$string['setting_daily_tokens_global_desc'] = 'Live tokens (input + output) allowed per calendar day across all customers and features. 0 = live calls blocked.';
$string['setting_daily_tokens_customer'] = 'Daily token cap (per customer)';
$string['setting_daily_tokens_customer_desc'] = 'Live tokens allowed per calendar day for any single customer. 0 = live calls blocked.';
$string['setting_monthly_cost_cap'] = 'Monthly cost cap (USD, estimated)';
$string['setting_monthly_cost_cap_desc'] = 'Estimated spend ceiling per calendar month. When the ledger\'s month-to-date estimate reaches this, further live calls are denied. 0 = live calls blocked.';

// Ledger admin page.
$string['ledger_title'] = 'AI spend ledger';
$string['ledger_intro'] = 'Every gateway call — mock, live, failed or denied — is one row. Quotas are enforced from these numbers before each live call.';
$string['ledger_today'] = 'Today (live tokens)';
$string['ledger_month'] = 'Month-to-date (estimated USD)';
$string['ledger_bycomponent'] = 'Last 30 days by feature';
$string['ledger_recent'] = 'Most recent calls';
$string['ledger_col_time'] = 'Time';
$string['ledger_col_component'] = 'Feature';
$string['ledger_col_purpose'] = 'Purpose';
$string['ledger_col_user'] = 'User';
$string['ledger_col_model'] = 'Model';
$string['ledger_col_tokens'] = 'Tokens (in/out)';
$string['ledger_col_cost'] = 'Est. cost';
$string['ledger_col_mode'] = 'Mode';
$string['ledger_col_calls'] = 'Calls';
$string['ledger_empty'] = 'No gateway calls recorded yet.';

// Privacy API.
$string['privacy:exportpath'] = 'AI gateway usage';
$string['privacy:metadata:ledger'] = 'The AI gateway spend ledger records one row per AI call a user triggers. Prompt and response text are never stored — only usage accounting.';
$string['privacy:metadata:ledger:userid'] = 'The user whose action triggered the AI call.';
$string['privacy:metadata:ledger:component'] = 'The plugin that made the call.';
$string['privacy:metadata:ledger:purpose'] = 'The declared purpose of the call (e.g. quiz_generation).';
$string['privacy:metadata:ledger:model'] = 'The AI model used.';
$string['privacy:metadata:ledger:prompttokens'] = 'Input tokens reported by the API.';
$string['privacy:metadata:ledger:completiontokens'] = 'Output tokens reported by the API.';
$string['privacy:metadata:ledger:estcost'] = 'Estimated cost of the call in USD.';
$string['privacy:metadata:ledger:mode'] = 'Whether the call was mock, live, failed or denied.';
$string['privacy:metadata:ledger:timecreated'] = 'When the call was made.';
$string['privacy:metadata:anthropic'] = 'When live mode is enabled, prompt text is sent to the Anthropic API to generate a response. Employee PII must not be included in prompts (enforced by the calling features\' input rules).';
$string['privacy:metadata:anthropic:prompttext'] = 'The prompt text of the AI request.';
