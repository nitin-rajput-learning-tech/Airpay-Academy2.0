<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Database upgrade hooks for local_sentientia_org.
 *
 * @package    local_sentientia_org
 */
function xmldb_local_sentientia_org_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // ── 2026051100: Per-tenant branding fields beyond colors+logo. ─────
    // Adds footer text, email-from, favicon, support contact, custom CSS,
    // landing-page hero. Used by tenant_settings.php to give external
    // tenants distinct branding without forking the theme.
    if ($oldversion < 2026051100) {

        $table = new xmldb_table('local_sentientia_org');

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

        upgrade_plugin_savepoint(true, 2026051100, 'local', 'sentientia_org');
    }

    // ── 2026061700: Revised airpay Brand Book 2026-06 — repaint retired
    // Tailwind violet out of per-tenant brand config. The Public ("external")
    // tenant carried #7c3aed (brand_color/button_color), #5b21b6 (hover) and a
    // violet navbar-shadow rgba(124,58,237) in custom_css — all pre-date the
    // brand revamp and render off-brand on every Public-tenant surface. The
    // install/defaults() are already #0066A7, so this only touches rows that
    // still hold the EXACT retired hexes; it cannot clobber a colour an admin
    // legitimately chose, and re-running matches nothing (idempotent).
    if ($oldversion < 2026061700) {
        $colmap = [
            'brand_color'  => ['#7c3aed' => '#0066A7'],
            'button_color' => ['#7c3aed' => '#0066A7'],
            'hover_color'  => ['#5b21b6' => '#004d80'],
        ];
        foreach ($colmap as $col => $map) {
            foreach ($map as $old => $new) {
                $n = $DB->count_records('local_sentientia_org', [$col => $old]);
                if ($n > 0) {
                    $DB->set_field('local_sentientia_org', $col, $new, [$col => $old]);
                    mtrace("  sentientia_org: repainted {$n} {$col} {$old} -> {$new}");
                }
            }
        }
        // custom_css is admin free-text: swap only the retired violet
        // navbar-shadow rgba (both comma-spacing variants), preserving the rest.
        $like = $DB->sql_like('custom_css', ':v', false);
        $rows = $DB->get_records_select('local_sentientia_org', $like,
            ['v' => '%124%58%237%'], '', 'id, custom_css');
        foreach ($rows as $r) {
            $fixed = str_replace(
                ['rgba(124,58,237', 'rgba(124, 58, 237'],
                'rgba(0,102,167',
                $r->custom_css
            );
            if ($fixed !== $r->custom_css) {
                $DB->set_field('local_sentientia_org', 'custom_css', $fixed, ['id' => $r->id]);
                mtrace("  sentientia_org: de-violeted navbar-shadow in custom_css on row {$r->id}");
            }
        }
        upgrade_plugin_savepoint(true, 2026061700, 'local', 'sentientia_org');
    }

    return true;
}
