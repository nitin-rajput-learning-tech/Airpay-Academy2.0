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
    // author caps. Idempotent; caps whose owning plugin isn't installed yet are
    // skipped and back-filled when that plugin runs ensure() from its own
    // install (e.g. local_sentientia_skillsai, which installs after this one).
    \local_sentientia_authoring\author_role::ensure();
    return true;
}
