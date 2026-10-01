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
 * Disable BizLMS plugins — marks them as disabled in config.
 *
 * Does NOT delete files or tables — only disables the plugins so
 * Moodle stops loading them. Can be reversed by re-enabling.
 *
 * Run AFTER the BizLMS import (local_sentientia_platform/cli/import_bizlms.php, ADR-032)
 * succeeds and the smoke test passes.
 *
 * Usage:
 *   php local/sentientia_org/cli/disable_bizlms.php
 *   php local/sentientia_org/cli/disable_bizlms.php --dry-run
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$dryrun = in_array('--dry-run', $argv ?? []);

cli_heading('Disable BizLMS Plugins');
if ($dryrun) {
    cli_writeln('  ** DRY RUN — no changes will be made **');
}
cli_writeln('');

// ADR-032 (mapping doc section 14, code fix 9): local_skillrepository is "merged into sentientia_skills" only once
// the skills import has finished (its completion marker). Before that the message must not claim a merge.
$skillsimported = class_exists('\local_sentientia_platform\bizlms\legacymap')
    && \local_sentientia_platform\bizlms\legacymap::feature_complete('skills');

// BizLMS plugins to disable — grouped by replacement status.
$plugins = [
    // Replaced by Airpay (Phase 1-5).
    'local_costcenter'      => 'Replaced by local_sentientia_org; the table stays and is read in place (ADR-032)',
    'local_users'           => 'Replaced by local_sentientia_users',
    'local_courses'         => 'Replaced by local_sentientia_courses',
    'local_classroom'       => 'Replaced by local_sentientia_classroom',
    'local_onlineexams'     => 'Replaced by local_sentientia_exams',
    'local_learningplan'    => 'Replaced by local_sentientia_learningpath',
    'local_search'          => 'Replaced by local_sentientia_catalog',
    'local_custom_category' => 'Replaced by category_manager in sentientia_catalog',

    // Already replaced (Phase 0 — pre-fork).
    'local_biz_cart'        => 'Replaced by sentientia_catalog commerce',
    'local_notifications'   => 'Replaced by sentientia_notifications',
    'local_myteam'          => 'Replaced by sentientia_manager',

    // Not replaced by an import target of their own, but NOT unused: their data is live (ADR-032 mapping doc,
    // section 3, code fix 5). Disabling the code never touches the tables, which stay as the legacy archive.
    'local_forum'           => 'No Sentientia replacement; its tables stay as the legacy archive (ADR-032)',
    'local_groups'          => 'Holds live data: the cohort_scope import carries it (ADR-032)',
    'local_tags'            => 'Holds live data: the course_tags import remaps the tag instances (ADR-032)',

    // BizLMS support plugins.
    'local_ratings'         => 'Optional — guarded by file_exists()',
    'local_challenge'       => 'Optional — guarded by core_component check',
    'local_skillrepository' => $skillsimported
        ? 'Merged into sentientia_skills (skills import complete)'
        : 'NOT merged: the skills import has not completed (import_bizlms.php --apply --feature=skills)',
    'local_evaluation'      => 'Not actively used',
    'local_assignroles'     => 'Not actively used',
    'local_program'         => 'Not actively used',
    'local_includes'        => 'BizLMS bootstrap — guarded by file_exists()',
];

$disabled = 0;
$skipped = 0;

foreach ($plugins as $component => $reason) {
    $plugintype = 'local';
    $pluginname = str_replace('local_', '', $component);

    // Check if plugin directory exists.
    $plugindir = \core_component::get_plugin_directory($plugintype, $pluginname);
    if (!$plugindir || !is_dir($plugindir)) {
        cli_writeln("  SKIP: {$component} — not installed");
        $skipped++;
        continue;
    }

    // Check if already disabled.
    $disabledplugins = get_config('core', 'disabledlocal') ?: '';
    $disabledlist = array_filter(explode(',', $disabledplugins));
    if (in_array($pluginname, $disabledlist)) {
        cli_writeln("  SKIP: {$component} — already disabled");
        $skipped++;
        continue;
    }

    if (!$dryrun) {
        $disabledlist[] = $pluginname;
        set_config('disabledlocal', implode(',', array_unique($disabledlist)), 'core');
    }

    cli_writeln("  DISABLED: {$component} — {$reason}" . ($dryrun ? ' (dry-run)' : ''));
    $disabled++;
}

cli_writeln('');
cli_writeln("Done: {$disabled} disabled, {$skipped} skipped.");

if (!$dryrun && $disabled > 0) {
    cli_writeln('');
    cli_writeln('Next steps:');
    cli_writeln('  1. Purge caches: php admin/cli/purge_caches.php');
    cli_writeln('  2. Smoke test: login as all 5 roles, check dashboard + catalog + profile');
    cli_writeln('  3. If anything breaks: re-enable by removing plugin from disabledlocal config');
}

if ($dryrun) {
    cli_writeln('');
    cli_writeln('** DRY RUN — run without --dry-run to apply changes **');
}
