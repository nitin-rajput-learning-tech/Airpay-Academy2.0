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
    return true;
}
