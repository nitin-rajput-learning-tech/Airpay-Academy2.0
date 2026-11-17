<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Install-time hook for local_sentientia_skillsai.
 *
 * T-01 fresh-install parity (UAT persona walk 2026-09-07). The Sentientia
 * Author role's canonical cap set (see local_sentientia_authoring\author_role)
 * includes this plugin's :extract / :review caps.
 *
 * Moodle registers a plugin's capabilities (db/access.php -> {capabilities})
 * AFTER its db/install.php returns: lib/upgradelib.php upgrade_plugins() runs
 * install.php first and upgrade_component_updated() -> update_capabilities()
 * second. author_role::ensure() grants only capabilities already registered,
 * so calling it here on its own skipped this plugin's two caps on every fresh
 * install. Until 2026111600 this hook did exactly that, while this comment
 * claimed the opposite; the role came up without :extract / :review. This hook
 * therefore registers the plugin's own capabilities first, then runs ensure().
 * The second update_capabilities() call Moodle makes after this hook finds
 * nothing new to add, so archetype defaults are applied once, as before.
 *
 * Sites that were fresh-installed with the gap are back-filled by
 * db/upgrade.php step 2026111600 (local_sentientia_skillsai_backfill_author_caps()).
 *
 * Guarded: it no-ops if local_sentientia_authoring (which owns the role) is
 * not installed.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run on plugin install.
 *
 * @return bool
 */
function xmldb_local_sentientia_skillsai_install(): bool {
    if (class_exists('\\local_sentientia_authoring\\author_role')) {
        // Underscore component name: the slash form registers nothing.
        update_capabilities('local_sentientia_skillsai');
        \local_sentientia_authoring\author_role::ensure();
    }
    return true;
}
