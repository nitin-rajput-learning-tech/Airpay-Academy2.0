<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Fresh-install hook for local_sentientia_analytics.
 *
 * A fresh install never runs db/upgrade.php, so the capability back-fill has
 * to be invoked from both places or new customers get an analytics dashboard
 * no non-admin can open. (UAT Stage A cost us a day to this exact
 * install.php-versus-upgrade.php split.)
 */
function xmldb_local_sentientia_analytics_install() {
    \local_sentientia_analytics\permission::grant_to_default_roles();
}
