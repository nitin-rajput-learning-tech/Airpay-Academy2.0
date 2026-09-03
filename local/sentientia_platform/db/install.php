<?php
// This file is part of Sentientia LMS.
//
// Sentientia LMS is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by the Free
// Software Foundation, either version 3 of the License, or (at your option)
// any later version. Distributed WITHOUT ANY WARRANTY. See the GNU GPL for
// more details. <http://www.gnu.org/licenses/>.

/**
 * Fresh-install hook for local_sentientia_platform.
 *
 * db/install.xml carries feature_flags / feature_flag_audit / customer_brand only; the ADR-017
 * user-type tables were created in db/upgrade.php step 2026052801 and therefore never existed on a
 * from-scratch install (UAT Stage A finding, 2026-09-03). This hook creates them on install so
 * fresh and upgraded sites share one schema. See classes/schema/user_type_tables.php.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create the user-type tables on a fresh install.
 *
 * @return bool
 */
function xmldb_local_sentientia_platform_install(): bool {
    global $DB;
    \local_sentientia_platform\schema\user_type_tables::ensure($DB->get_manager());
    return true;
}
