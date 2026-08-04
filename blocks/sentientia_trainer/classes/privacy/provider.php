<?php
/**
 * Privacy provider for block_sentientia_trainer.
 *
 * Implements null_provider — this block is a read-only dashboard over
 * classroom session data owned by the classroom plugin. It owns no
 * database tables and sets no user preferences.
 *
 * @package    block_sentientia_trainer
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_sentientia_trainer\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
