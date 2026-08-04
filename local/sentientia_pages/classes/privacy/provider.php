<?php
/**
 * Privacy provider for local_sentientia_pages.
 *
 * Implements null_provider — this plugin owns no database tables and
 * sets no user preferences. The QR attendance scan page writes rows
 * into the classroom plugin's attendance table, which is described by
 * that plugin's own privacy provider.
 *
 * @package   local_sentientia_pages
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_pages\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
