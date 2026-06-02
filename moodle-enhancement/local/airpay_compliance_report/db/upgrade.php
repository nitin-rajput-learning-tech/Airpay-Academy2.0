<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_compliance_report_upgrade(int $oldversion): bool {

    if ($oldversion < 2026052900) {
        // New capability local/airpay_compliance_report:export. Grant it to the
        // roles that should hold it (admins / course managers + the BizLMS
        // Compliance Officer) so existing installs match a fresh install.
        \local_airpay_compliance_report\permission::grant_export_to_default_roles();
        upgrade_plugin_savepoint(true, 2026052900, 'local', 'airpay_compliance_report');
    }

    return true;
}
