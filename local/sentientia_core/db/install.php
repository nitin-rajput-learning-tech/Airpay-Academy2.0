<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Fresh-install hook for local_sentientia_core.
 *
 * Moodle runs db/install.php ONLY on a from-scratch install and db/upgrade.php
 * ONLY when an already-installed plugin's version is bumped. The tenant
 * substrate (the BizLMS-compatible open_* columns on {user} / {course}, owned
 * first-party by classes/substrate.php since ADR-024 Wave 2) was wired into
 * upgrade.php alone (step 2026060400). Every site that already had the plugin
 * therefore received the columns, while a brand-new site came up without them:
 * UAT Stage A (2026-09-03, Moodle 5.2 on MySQL 8.4) failed every open_path
 * query - "Unknown column 'open_path' in 'field list'" in the analytics cache
 * task, the guest landing page, the org tree, audience enrolment ...
 *
 * This hook closes the gap: a fresh install provisions the substrate the same
 * way an upgrade does. Idempotent + additive (only absent columns are added),
 * so it is a no-op on a database restored from the Airpay production backup.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provision the open_* tenant substrate on a fresh install.
 *
 * @return bool
 */
function xmldb_local_sentientia_core_install(): bool {
    \local_sentientia_core\substrate::ensure_all(false);
    return true;
}
