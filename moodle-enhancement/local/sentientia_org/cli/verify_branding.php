<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Branding verification — checks Moodle is fully Airpay-branded.
 *
 * Usage: php local/sentientia_org/cli/verify_branding.php
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

cli_heading('Airpay Branding Verification');
cli_writeln('');

$pass = 0;
$fail = 0;
$warn = 0;

// ═══════════════════════════════════════════════════════
// 1. wwwroot — no /moodle/ in URL
// ═══════════════════════════════════════════════════════
$check = 'wwwroot has no /moodle/ path';
if (strpos($CFG->wwwroot, '/moodle') === false) {
    cli_pass($check, $CFG->wwwroot);
    $pass++;
} else {
    cli_fail($check, $CFG->wwwroot . ' — update $CFG->wwwroot in config.php');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// 2. Site name — should be "airpay academy" or similar
// ═══════════════════════════════════════════════════════
$sitename = get_config('core', 'fullname');
$check = 'Site name is Airpay-branded';
if (stripos($sitename, 'moodle') === false) {
    cli_pass($check, $sitename);
    $pass++;
} else {
    cli_fail($check, $sitename . ' — change in Site Admin → General → Site name');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// 3. Short site name
// ═══════════════════════════════════════════════════════
$shortname = get_config('core', 'shortname');
$check = 'Short site name is Airpay-branded';
if (stripos($shortname, 'moodle') === false) {
    cli_pass($check, $shortname);
    $pass++;
} else {
    cli_fail($check, $shortname . ' — change in Site Admin → General → Short name');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// 4. Active theme is airpayux
// ═══════════════════════════════════════════════════════
$theme = get_config('core', 'theme');
$check = 'Active theme is airpayux';
if ($theme === 'airpayux') {
    cli_pass($check, $theme);
    $pass++;
} else {
    cli_fail($check, $theme . ' — change in Site Admin → Appearance → Theme selector');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// 5. No email ever (local dev safety)
// ═══════════════════════════════════════════════════════
$check = 'noemailever is set (local dev safety)';
if (!empty($CFG->noemailever)) {
    cli_pass($check, 'enabled');
    $pass++;
} else {
    cli_warn($check, 'disabled — OK for production, ensure SMTP is configured');
    $warn++;
}

// ═══════════════════════════════════════════════════════
// 6. Airpay org plugin installed
// ═══════════════════════════════════════════════════════
$check = 'local_sentientia_org plugin installed';
$pluginman = \core_plugin_manager::instance();
$plugininfo = $pluginman->get_plugin_info('local_sentientia_org');
if ($plugininfo) {
    cli_pass($check, 'v' . ($plugininfo->release ?? $plugininfo->versiondisk));
    $pass++;
} else {
    cli_fail($check, 'not found — run Admin → Notifications');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// 7. Migration completed (sentientia_org table has data)
// ═══════════════════════════════════════════════════════
$check = 'local_sentientia_org table has data';
$dbman = $DB->get_manager();
if ($dbman->table_exists('local_sentientia_org')) {
    $count = $DB->count_records('local_sentientia_org');
    if ($count > 0) {
        cli_pass($check, "{$count} records");
        $pass++;
    } else {
        cli_warn($check, 'empty — run php local/sentientia_org/cli/migrate_all.php');
        $warn++;
    }
} else {
    cli_fail($check, 'table does not exist — run Admin → Notifications');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// 8. Capability migration
// ═══════════════════════════════════════════════════════
$check = 'Airpay capabilities exist in role_capabilities';
$airpay_caps = $DB->count_records_select('role_capabilities',
    "capability LIKE 'local/airpay_%'");
if ($airpay_caps > 0) {
    cli_pass($check, "{$airpay_caps} rows");
    $pass++;
} else {
    cli_warn($check, '0 rows — run migrate_all.php or assign capabilities manually');
    $warn++;
}

// ═══════════════════════════════════════════════════════
// 9. Favicon exists
// ═══════════════════════════════════════════════════════
$check = 'Custom favicon exists';
$faviconpath = $CFG->dirroot . '/theme/airpayux/pix/favicon.ico';
if (file_exists($faviconpath)) {
    cli_pass($check, $faviconpath);
    $pass++;
} else {
    // Check for PNG favicon.
    $pngpath = $CFG->dirroot . '/theme/airpayux/pix/favicon.png';
    if (file_exists($pngpath)) {
        cli_pass($check, $pngpath);
        $pass++;
    } else {
        cli_warn($check, 'not found — uses Moodle default favicon');
        $warn++;
    }
}

// ═══════════════════════════════════════════════════════
// 10. Default logo exists
// ═══════════════════════════════════════════════════════
$check = 'Default logo exists';
$logopath = $CFG->dirroot . '/theme/airpayux/pix/default_logo.png';
if (file_exists($logopath)) {
    cli_pass($check, $logopath);
    $pass++;
} else {
    cli_fail($check, 'not found — navbar will show broken image');
    $fail++;
}

// ═══════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════
cli_writeln('');
cli_heading('Results');
cli_writeln("  PASS: {$pass}  |  FAIL: {$fail}  |  WARN: {$warn}");

if ($fail === 0) {
    cli_writeln('');
    cli_writeln('  Branding verification PASSED.');
} else {
    cli_writeln('');
    cli_writeln("  {$fail} issue(s) require attention before production deploy.");
}

// ═══════════════════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════════════════

function cli_pass(string $check, string $detail): void {
    cli_writeln("  [PASS] {$check}: {$detail}");
}

function cli_fail(string $check, string $detail): void {
    cli_writeln("  [FAIL] {$check}: {$detail}");
}

function cli_warn(string $check, string $detail): void {
    cli_writeln("  [WARN] {$check}: {$detail}");
}
