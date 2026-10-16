<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sentientia_cart',
        get_string('pluginname', 'local_sentientia_cart'));

    // ── General ────────────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('local_sentientia_cart/general_heading',
        get_string('settings_general', 'local_sentientia_cart'), ''));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/enabled_tenants',
        get_string('settings_enabled_tenants', 'local_sentientia_cart'),
        get_string('settings_enabled_tenants_desc', 'local_sentientia_cart'),
        '77,177', PARAM_TEXT));

    $settings->add(new admin_setting_configselect('local_sentientia_cart/currency',
        get_string('settings_currency', 'local_sentientia_cart'),
        '',
        'INR',
        ['INR' => 'Indian Rupee (₹)', 'USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'Pound (£)']));

    // ── Payment gateway ────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('local_sentientia_cart/payment_heading',
        get_string('settings_gateway_airpay', 'local_sentientia_cart'), ''));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/airpay_endpoint',
        get_string('settings_gateway_airpay_endpoint', 'local_sentientia_cart'),
        get_string('settings_gateway_airpay_endpoint_desc', 'local_sentientia_cart'),
        'https://payments.airpay.co.in/pay/index.php', PARAM_URL));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/airpay_merchantid',
        get_string('settings_gateway_airpay_merchantid', 'local_sentientia_cart'),
        '', '', PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_configpasswordunmask('local_sentientia_cart/airpay_secret',
        get_string('settings_gateway_airpay_secret', 'local_sentientia_cart'),
        get_string('settings_gateway_airpay_secret_desc', 'local_sentientia_cart'),
        ''));

    // Phase 8.1 B11 fix: optional IP allow-list for callback.php. CSV of
    // CIDR ranges or single IPs. Empty = accept from anywhere (legacy
    // behaviour). If configured, callbacks from non-listed sources are
    // silently dropped with a 404 — invisible to attackers scanning.
    $settings->add(new admin_setting_configtext('local_sentientia_cart/airpay_callback_iplist',
        get_string('settings_callback_iplist', 'local_sentientia_cart'),
        get_string('settings_callback_iplist_desc', 'local_sentientia_cart'),
        '', PARAM_TEXT));

    // ── Tax & invoicing ────────────────────────────────────────────────
    $settings->add(new admin_setting_heading('local_sentientia_cart/tax_heading',
        get_string('settings_tax', 'local_sentientia_cart'), ''));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/gst_rate',
        get_string('settings_gstrate', 'local_sentientia_cart'),
        'GST percentage (combined CGST+SGST or IGST). Default 18.',
        '18', PARAM_INT));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/our_gstn',
        get_string('settings_gstn', 'local_sentientia_cart'),
        'Our company GSTN (shown on invoices)',
        '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/company_name',
        get_string('settings_companyname', 'local_sentientia_cart'),
        '', 'Airpay Payment Services Pvt Ltd', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('local_sentientia_cart/company_address',
        get_string('settings_companyaddress', 'local_sentientia_cart'),
        '',
        "Airpay House\nA-wing, Office Building\nMumbai 400063, India",
        PARAM_TEXT));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/invoice_prefix',
        get_string('settings_invoiceprefix', 'local_sentientia_cart'),
        'Prefix for invoice numbers. E.g. AIRPAY → AIRPAY-2026-0001',
        'AIRPAY', PARAM_ALPHANUMEXT));

    // ── GST tax invoices and credit notes (2026-10-14; spec GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md) ──
    // Used only while sentientia.cart.gst_tax_invoices is ON. Invoicing refuses to start while the company name, the
    // company address, our GSTN or the SAC code is empty, or the supplier state is not a valid GST state code; the
    // documents then stay due, and the site administrators are warned once a day.
    $settings->add(new admin_setting_heading('local_sentientia_cart/gst_heading',
        get_string('settings_gst', 'local_sentientia_cart'),
        get_string('settings_gst_desc', 'local_sentientia_cart')));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/sac_code',
        get_string('settings_saccode', 'local_sentientia_cart'),
        get_string('settings_saccode_desc', 'local_sentientia_cart'),
        '', PARAM_ALPHANUM));

    $settings->add(new admin_setting_configselect('local_sentientia_cart/supplier_state_code',
        get_string('settings_supplierstate', 'local_sentientia_cart'),
        get_string('settings_supplierstate_desc', 'local_sentientia_cart'),
        '',
        ['' => get_string('settings_supplierstate_fromgstn', 'local_sentientia_cart')]
            + \local_sentientia_cart\gst_states::options()));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/credit_note_prefix',
        get_string('settings_creditnoteprefix', 'local_sentientia_cart'),
        get_string('settings_creditnoteprefix_desc', 'local_sentientia_cart'),
        \local_sentientia_cart\gst_settings::DEFAULT_CREDIT_NOTE_PREFIX, PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_configselect('local_sentientia_cart/series_period',
        get_string('settings_seriesperiod', 'local_sentientia_cart'),
        get_string('settings_seriesperiod_desc', 'local_sentientia_cart'),
        'calendar',
        [
            'calendar'  => get_string('settings_seriesperiod_calendar', 'local_sentientia_cart'),
            'financial' => get_string('settings_seriesperiod_financial', 'local_sentientia_cart'),
        ]));

    $settings->add(new admin_setting_configcheckbox('local_sentientia_cart/einvoice_b2b',
        get_string('settings_einvoiceb2b', 'local_sentientia_cart'),
        get_string('settings_einvoiceb2b_desc', 'local_sentientia_cart'),
        1));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/invoice_retention_years',
        get_string('settings_retentionyears', 'local_sentientia_cart'),
        get_string('settings_retentionyears_desc', 'local_sentientia_cart'),
        (string) \local_sentientia_cart\gst_settings::DEFAULT_RETENTION_YEARS, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_sentientia_cart/signatory_text',
        get_string('settings_signatory', 'local_sentientia_cart'),
        get_string('settings_signatory_desc', 'local_sentientia_cart'),
        'Authorised signatory', PARAM_TEXT));

    $settings->add(new admin_setting_description('local_sentientia_cart/einvoice_link',
        get_string('einvoice_link', 'local_sentientia_cart'),
        html_writer::link(new moodle_url('/local/sentientia_cart/einvoice.php'),
            get_string('einvoice_title', 'local_sentientia_cart'))));

    $ADMIN->add('localplugins', $settings);
}
