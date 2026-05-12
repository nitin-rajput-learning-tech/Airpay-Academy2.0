<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Database upgrade hooks for local_airpay_org.
 *
 * @package    local_airpay_org
 */
function xmldb_local_airpay_org_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ── 2026051100: Per-tenant branding fields beyond colors+logo. ─────
    // Adds footer text, email-from, favicon, support contact, custom CSS,
    // landing-page hero. Used by tenant_settings.php to give external
    // tenants distinct branding without forking the theme.
    if ($oldversion < 2026051100) {

        $table = new xmldb_table('local_airpay_org');

        $newfields = [
            ['favicon',         XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'org_logo'],
            ['footer_text',     XMLDB_TYPE_TEXT,    null, null, null, null, null, 'theme_scheme'],
            ['email_from_name', XMLDB_TYPE_CHAR,    '200', null, null, null, null, 'footer_text'],
            ['email_from_addr', XMLDB_TYPE_CHAR,    '200', null, null, null, null, 'email_from_name'],
            ['support_email',   XMLDB_TYPE_CHAR,    '200', null, null, null, null, 'email_from_addr'],
            ['help_url',        XMLDB_TYPE_CHAR,    '255', null, null, null, null, 'support_email'],
            ['hero_title',      XMLDB_TYPE_CHAR,    '255', null, null, null, null, 'help_url'],
            ['hero_subtitle',   XMLDB_TYPE_TEXT,    null, null, null, null, null, 'hero_title'],
            ['custom_css',      XMLDB_TYPE_TEXT,    null, null, null, null, null, 'hero_subtitle'],
        ];

        foreach ($newfields as $fdef) {
            $field = new xmldb_field(...$fdef);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026051100, 'local', 'airpay_org');
    }

    return true;
}
