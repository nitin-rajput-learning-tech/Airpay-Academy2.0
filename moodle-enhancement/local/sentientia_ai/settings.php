<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for the Sentientia AI Gateway.
 *
 *   api_key               Central Anthropic key (passwordunmask) — the ONE
 *                         key to rotate. Per-plugin legacy keys still work
 *                         as fallback during migration (gateway::resolve_api_key).
 *   default_model         Model used when the caller doesn't specify.
 *   daily_tokens_global   Live tokens/day across ALL customers. 0/empty =
 *                         NO live calls (fail-closed, never unlimited).
 *   daily_tokens_customer Live tokens/day per customer. Same fail-closed rule.
 *   monthly_cost_cap_usd  Estimated-USD/month ceiling. Same fail-closed rule.
 *
 * The signed Addendum-A decision (memo 2026-08-04) approved a broader
 * multi-feature budget with the cap figure TBD — these defaults are
 * deliberately modest placeholders until Nitin sets the real numbers.
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_ai',
        get_string('pluginname', 'local_sentientia_ai')
    );

    $settings->add(new admin_setting_heading(
        'local_sentientia_ai/heading_api',
        get_string('settings_heading_api', 'local_sentientia_ai'),
        get_string('settings_heading_api_desc', 'local_sentientia_ai')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_ai/api_key',
        get_string('setting_api_key', 'local_sentientia_ai'),
        get_string('setting_api_key_desc', 'local_sentientia_ai'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_ai/default_model',
        get_string('setting_default_model', 'local_sentientia_ai'),
        get_string('setting_default_model_desc', 'local_sentientia_ai'),
        'claude-sonnet-4-6',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_ai/heading_quotas',
        get_string('settings_heading_quotas', 'local_sentientia_ai'),
        get_string('settings_heading_quotas_desc', 'local_sentientia_ai')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_ai/daily_tokens_global',
        get_string('setting_daily_tokens_global', 'local_sentientia_ai'),
        get_string('setting_daily_tokens_global_desc', 'local_sentientia_ai'),
        '200000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_ai/daily_tokens_customer',
        get_string('setting_daily_tokens_customer', 'local_sentientia_ai'),
        get_string('setting_daily_tokens_customer_desc', 'local_sentientia_ai'),
        '100000',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_ai/monthly_cost_cap_usd',
        get_string('setting_monthly_cost_cap', 'local_sentientia_ai'),
        get_string('setting_monthly_cost_cap_desc', 'local_sentientia_ai'),
        '25',
        PARAM_FLOAT
    ));

    $ADMIN->add('localplugins', $settings);

    $ADMIN->add('reports', new admin_externalpage(
        'local_sentientia_ai_ledger',
        get_string('ledger_title', 'local_sentientia_ai'),
        new moodle_url('/local/sentientia_ai/index.php'),
        'local/sentientia_ai:viewledger'
    ));
}
