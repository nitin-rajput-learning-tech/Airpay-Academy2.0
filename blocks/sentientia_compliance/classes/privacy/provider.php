<?php
/**
 * Privacy provider for block_sentientia_compliance.
 *
 * Implements null_provider — this block is a read-only presentation
 * layer over course completion data owned by core Moodle. It owns no
 * database tables and sets no user preferences.
 *
 * @package    block_sentientia_compliance
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_sentientia_compliance\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
