<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_airpay_cart',
        get_string('pluginname', 'local_airpay_cart'));

    // ── General ────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('local_airpay_cart/general_heading',
        get_string('settings_general', 'local_airpay_cart'), ''));

    $settings->add(new admin_setting_configtext('local_airpay_cart/enabled_tenants',
        get_string('settings_enabled_tenants', 'local_airpay_cart'),
        get_string('settings_enabled_tenants_desc', 'local_airpay_cart'),
        '77,177', PARAM_TEXT));

    $settings->add(new admin_setting_configselect('local_airpay_cart/currency',
        get_string('settings_currency', 'local_airpay_cart'),
        '',
        'INR',
        ['INR' => 'Indian Rupee (₹)', 'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'Pound (£)']));

    // ── Payment gateway ────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('local_airpay_cart/payment_heading',
        get_string('settings_gateway_airpay', 'local_airpay_cart'), ''));

    $settings->add(new admin_setting_configtext('local_airpay_cart/airpay_endpoint',
        get_string('settings_gateway_airpay_endpoint', 'local_airpay_cart'),
        get_string('settings_gateway_airpay_endpoint_desc', 'local_airpay_cart'),
        'https://payments.airpay.co.in/pay/index.php', PARAM_URL));

    $settings->add(new admin_setting_configtext('local_airpay_cart/airpay_merchantid',
        get_string('settings_gateway_airpay_merchantid', 'local_airpay_cart'),
        '', '', PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_configpasswordunmask('local_airpay_cart/airpay_secret',
        get_string('settings_gateway_airpay_secret', 'local_airpay_cart'),
        get_string('settings_gateway_airpay_secret_desc', 'local_airpay_cart'),
        ''));

    // Phase 8.1 B11 fix: optional IP allow-list for callback.php. CSV of
    // CIDR ranges or single IPs. Empty = accept from anywhere (legacy
    // behaviour). If configured, callbacks from non-listed sources are
    // silently dropped with a 404 — invisible to attackers scanning.
    $settings->add(new admin_setting_configtext('local_airpay_cart/airpay_callback_iplist',
        get_string('settings_callback_iplist', 'local_airpay_cart'),
        get_string('settings_callback_iplist_desc', 'local_airpay_cart'),
        '', PARAM_TEXT));

    // ── Tax & invoicing ────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('local_airpay_cart/tax_heading',
        get_string('settings_tax', 'local_airpay_cart'), ''));

    $settings->add(new admin_setting_configtext('local_airpay_cart/gst_rate',
        get_string('settings_gstrate', 'local_airpay_cart'),
        'GST percentage (combined CGST+SGST or IGST). Default 18.',
        '18', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_airpay_cart/our_gstn',
        get_string('settings_gstn', 'local_airpay_cart'),
        'Our company GSTN (shown on invoices)',
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_airpay_cart/company_name',
        get_string('settings_companyname', 'local_airpay_cart'),
        '', 'Airpay Payment Services Pvt Ltd', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('local_airpay_cart/company_address',
        get_string('settings_companyaddress', 'local_airpay_cart'),
        '',
        "Airpay House\nA-wing, Office Building\nMumbai 400063, India",
        PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_airpay_cart/invoice_prefix',
        get_string('settings_invoiceprefix', 'local_airpay_cart'),
        'Prefix for invoice numbers. E.g. AIRPAY → AIRPAY-2026-0001',
        'AIRPAY', PARAM_ALPHANUMEXT));

    $ADMIN->add('localplugins', $settings);
}
