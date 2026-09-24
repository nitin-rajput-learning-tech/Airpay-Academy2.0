<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Test double: the classroom privacy provider on a site whose database has no
 * local_sentientia_classroom_waitlist table: installed fresh before
 * db/install.xml declared it (2026-09-24), and not yet upgraded to 2026092400.
 *
 * The real table stays in the PHPUnit database, so a test can seed a row and
 * prove that no provider path reads or writes it once the guard says it is
 * absent. Dropping the real table inside a test would outlive the test.
 *
 * @package    local_sentientia_classroom
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_without_waitlist extends provider {

    protected static function waitlist_exists(): bool {
        return false;
    }
}
