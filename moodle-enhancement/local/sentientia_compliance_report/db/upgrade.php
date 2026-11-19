<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_compliance_report_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2026052900) {
        // New capability local/sentientia_compliance_report:export. Grant it to the
        // roles that should hold it (admins / course managers + the BizLMS
        // Compliance Officer) so existing installs match a fresh install.
        \local_sentientia_compliance_report\permission::grant_export_to_default_roles();
        upgrade_plugin_savepoint(true, 2026052900, 'local', 'sentientia_compliance_report');
    }

    // 2026111702 - take :export back from a role 9 that is not the org admin.
    //
    // Until now the install step granted the capability (bulk export of every
    // employee's name, email, employee id, department and compliance status)
    // to role id 9, whatever that role was. In the Airpay data id 9 is the
    // BizLMS org-admin role 'administrator', the intended holder. On a fresh
    // Sentientia install it is the first role a plugin creates, the Sentientia
    // Author role, so every author could export the matrix. The grant now
    // looks the role up by shortname; this step removes the old grant where id
    // 9 is some other role. It does not grant anything.
    //
    // Numbered 2026111702, above 2026111701 (the courseid-0 exclusion fix,
    // written in parallel with no upgrade step), so this runs on a site that
    // took 2026111701 first, whichever change ships first. Idempotent.
    if ($oldversion < 2026111702) {
        \local_sentientia_compliance_report\permission::revoke_misdirected_export_grant();
        upgrade_plugin_savepoint(true, 2026111702, 'local', 'sentientia_compliance_report');
    }

    // 2026111901 - A1 / D4 (post-demo plan, 2026-11-20): a tracked course can
    // be restricted to one cohort (POSH for IC Members to the IC cohort).
    // Schema only, additive: every existing row gets 0 (no restriction), so
    // nothing changes until cli/pin_compliance_courses.php --execute sets a
    // cohort. The data fix itself is never an upgrade step.
    if ($oldversion < 2026111901) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_compliance_courses');
        $field = new xmldb_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'costcenterid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026111901, 'local', 'sentientia_compliance_report');
    }

    return true;
}
