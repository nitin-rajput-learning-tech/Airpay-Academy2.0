<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sentientia_core',
        get_string('pluginname', 'local_sentientia_core')
    );
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_sentientia_core/tenant_identity_heading',
        get_string('settings_tenant_identity', 'local_sentientia_core'),
        ''
    ));

    // Default-ON legacy flag — the ADR-019 Wave-2 seam toggle.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_core/tenant_identity_legacy',
        get_string('setting_legacy_openpath', 'local_sentientia_core'),
        get_string('setting_legacy_openpath_desc', 'local_sentientia_core'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_core/org_heading',
        get_string('settings_org', 'local_sentientia_core'),
        ''
    ));

    // Default-ON legacy flag — the ADR-020 Wave-3.1 org seam toggle.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_core/org_legacy',
        get_string('setting_org_legacy', 'local_sentientia_core'),
        get_string('setting_org_legacy_desc', 'local_sentientia_core'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'local_sentientia_core/tenant_registry_heading',
        get_string('settings_tenant_registry', 'local_sentientia_core'),
        ''
    ));

    // Default-ON legacy flag — the ADR-021 Wave-4 tenant-registry toggle.
    $settings->add(new admin_setting_configcheckbox(
        'local_sentientia_core/tenant_registry_legacy',
        get_string('setting_legacy_registry', 'local_sentientia_core'),
        get_string('setting_legacy_registry_desc', 'local_sentientia_core'),
        1
    ));

    // ADR-021 Wave 4 — tenant registry admin UI (gated by managetenants capability).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_sentientia_core_tenants',
        get_string('managetenants', 'local_sentientia_core'),
        $CFG->wwwroot . '/local/sentientia_core/manage_tenants.php',
        'local/sentientia_core:managetenants'
    ));
}
