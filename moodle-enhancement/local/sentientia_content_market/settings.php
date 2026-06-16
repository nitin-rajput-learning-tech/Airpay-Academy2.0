<?php
/**
 * Admin settings page for local_sentientia_content_market.
 *
 * Credentials for each provider are stored as Moodle plugin config
 * (encrypted password fields where possible). All values come from
 * get_config() at runtime — never hardcoded.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_content_market',
        get_string('pluginname', 'local_sentientia_content_market')
    );

    // ═══ Plugin description + flag status ════════════════════════════
    $settings->add(new admin_setting_heading(
        'general_heading',
        get_string('settings_general_heading', 'local_sentientia_content_market'),
        get_string('settings_general_desc', 'local_sentientia_content_market')
    ));

    // ═══ GO1 ═══════════════════════════════════════════════════════
    $settings->add(new admin_setting_heading(
        'go1_heading',
        get_string('settings_go1_heading', 'local_sentientia_content_market'),
        get_string('settings_go1_desc', 'local_sentientia_content_market')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_content_market/go1_api_key',
        get_string('settings_go1_api_key', 'local_sentientia_content_market'),
        get_string('settings_go1_api_key_desc', 'local_sentientia_content_market'),
        ''
    ));

    // ═══ UDEMY BUSINESS ════════════════════════════════════════════
    $settings->add(new admin_setting_heading(
        'udemy_heading',
        get_string('settings_udemy_heading', 'local_sentientia_content_market'),
        get_string('settings_udemy_desc', 'local_sentientia_content_market')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_content_market/udemy_account_id',
        get_string('settings_udemy_account_id', 'local_sentientia_content_market'),
        get_string('settings_udemy_account_id_desc', 'local_sentientia_content_market'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_content_market/udemy_api_key',
        get_string('settings_udemy_api_key', 'local_sentientia_content_market'),
        get_string('settings_udemy_api_key_desc', 'local_sentientia_content_market'),
        ''
    ));

    // ═══ COURSERA ════════════════════════════════════════════════════
    $settings->add(new admin_setting_heading(
        'coursera_heading',
        get_string('settings_coursera_heading', 'local_sentientia_content_market'),
        get_string('settings_coursera_desc', 'local_sentientia_content_market')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_content_market/coursera_client_id',
        get_string('settings_coursera_client_id', 'local_sentientia_content_market'),
        get_string('settings_coursera_client_id_desc', 'local_sentientia_content_market'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_content_market/coursera_client_secret',
        get_string('settings_coursera_client_secret', 'local_sentientia_content_market'),
        get_string('settings_coursera_client_secret_desc', 'local_sentientia_content_market'),
        ''
    ));

    // ═══ SKILLSOFT PERCIPIO ══════════════════════════════════════════
    $settings->add(new admin_setting_heading(
        'skillsoft_heading',
        get_string('settings_skillsoft_heading', 'local_sentientia_content_market'),
        get_string('settings_skillsoft_desc', 'local_sentientia_content_market')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_content_market/skillsoft_subdomain',
        get_string('settings_skillsoft_subdomain', 'local_sentientia_content_market'),
        get_string('settings_skillsoft_subdomain_desc', 'local_sentientia_content_market'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_content_market/skillsoft_org_id',
        get_string('settings_skillsoft_org_id', 'local_sentientia_content_market'),
        get_string('settings_skillsoft_org_id_desc', 'local_sentientia_content_market'),
        '', PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sentientia_content_market/skillsoft_api_key',
        get_string('settings_skillsoft_api_key', 'local_sentientia_content_market'),
        get_string('settings_skillsoft_api_key_desc', 'local_sentientia_content_market'),
        ''
    ));

    $ADMIN->add('localplugins', $settings);
}
