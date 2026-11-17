<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Install-time hook for local_sentientia_authoring.
 *
 * Seeds the built-in instructional-design templates so a trainer always has a
 * working starting point. Idempotent — seed_builtins() no-ops if any built-in
 * already exists.
 *
 * Also seeds the dedicated "Sentientia Author" system-context role (T-01
 * fresh-install parity — see author_role). The role + its caps were previously
 * created only in db/upgrade.php, which never runs on a fresh install, so a new
 * Sentientia customer came up with no author role. install.php closes that gap;
 * db/upgrade.php step 2026090700 does the same for existing installs.
 *
 * Moodle registers a plugin's capabilities (db/access.php -> {capabilities})
 * AFTER its db/install.php returns: lib/upgradelib.php upgrade_plugins() runs
 * install.php first and upgrade_component_updated() -> update_capabilities()
 * second. author_role::ensure() grants only capabilities already registered,
 * so until 2026111600 this hook created the role WITHOUT this plugin's own
 * :generate / :review / :managetemplates caps. They reached the role only when
 * local_sentientia_skillsai installed later in the same run and re-ran
 * ensure(); a site without skillsai, or one that had skillsai installed before
 * this plugin, kept a role without them. This hook now registers the plugin's
 * own capabilities first. The second update_capabilities() call Moodle makes
 * after this hook finds nothing new to add, so archetype defaults are applied
 * once, as before. db/upgrade.php step 2026111600 back-fills sites that were
 * fresh-installed with the gap.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run on plugin install.
 *
 * @return bool
 */
function xmldb_local_sentientia_authoring_install(): bool {
    \local_sentientia_authoring\template_manager::seed_builtins();

    // T-01 fresh-install parity: create the Sentientia Author role + grant its
    // author caps. Register this plugin's own capabilities first (underscore
    // component name: the slash form registers nothing), or ensure() skips them.
    // Caps owned by a plugin that is not installed yet are still skipped:
    // local_sentientia_aiquiz installs before this plugin (alphabetical), and
    // local_sentientia_skillsai registers its own and re-runs ensure() from its
    // install.php.
    update_capabilities('local_sentientia_authoring');
    \local_sentientia_authoring\author_role::ensure();
    return true;
}
