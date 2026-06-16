<?php
/**
 * Privacy API implementation for local_sentientia_content_market.
 *
 * The content marketplace stores no personal data — it indexes third-party
 * course catalog metadata. Sync logs store provider-level statistics only,
 * not user-linked records. Skill maps link items to taxonomy terms, not users.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_content_market\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return get_string('privacy:metadata', 'local_sentientia_content_market');
    }
}
