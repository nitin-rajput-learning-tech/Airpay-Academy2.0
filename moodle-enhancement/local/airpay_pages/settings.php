<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Admin settings for local_airpay_pages.
 *
 * C10 P1 / Gap 3 (2026-05-29) — adds:
 *   - cert_template_tenant_map : JSON map {templateid: tenantid} that
 *     scopes tool_certificate templates to tenants. Consumed by
 *     certificate_templates.php when the
 *     sentientia.certificate.tenant_scope.enabled flag is ON.
 *   - an external-page link to the tenant-scoped template browser.
 *
 * @package local_airpay_pages
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_airpay_pages',
        get_string('pluginname', 'local_airpay_pages')
    );

    $settings->add(new admin_setting_heading(
        'local_airpay_pages/heading_cert_scope',
        get_string('cert_scope_heading', 'local_airpay_pages'),
        get_string('cert_scope_heading_desc', 'local_airpay_pages')
    ));

    // JSON map: {"5": 1, "8": 177, "11": 0}  (tenantid 0 / absent = global).
    // Validated as PARAM_RAW here; certificate_templates.php json_decodes
    // defensively and ignores malformed input (falls back to "all global").
    $settings->add(new admin_setting_configtextarea(
        'local_airpay_pages/cert_template_tenant_map',
        get_string('cert_template_tenant_map', 'local_airpay_pages'),
        get_string('cert_template_tenant_map_desc', 'local_airpay_pages'),
        '',
        PARAM_RAW
    ));

    $ADMIN->add('localplugins', $settings);

    // Link to the tenant-scoped template browser.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_airpay_pages_cert_templates',
        get_string('cert_templates_title', 'local_airpay_pages'),
        new moodle_url('/local/airpay_pages/certificate_templates.php'),
        'moodle/site:config'
    ));
}
