<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Install-time hook for local_sentientia_skillsai.
 *
 * T-01 fresh-install parity (UAT persona walk 2026-09-07). The Sentientia
 * Author role's canonical cap set (see local_sentientia_authoring\author_role)
 * includes this plugin's :extract / :review caps. On a fresh install Moodle
 * installs plugins alphabetically, so local_sentientia_authoring installs
 * BEFORE local_sentientia_skillsai — meaning authoring's own install-time
 * author_role::ensure() runs before this plugin's caps are registered and skips
 * them (the ensure() guard avoids orphan rows). Because skillsai installs after
 * authoring, re-running ensure() here — when ALL author caps are registered —
 * fills the skillsai caps onto the role. Idempotent and guarded: it no-ops if
 * the authoring plugin (which owns the role) isn't installed.
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
        \local_sentientia_authoring\author_role::ensure();
    }
    return true;
}
