<?php
/**
 * Tenant branding configuration for email templates.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

class tenant_config {

    /** Known tenant IDs. */
    const AIRPAY = 1;
    const PUBLIC_TENANT = 77;
    const ZEEA = 177;

    /**
     * Get branding config for a tenant.
     *
     * @param int $costcenterid
     * @return array {name, logo_filename, primary_color, accent_color}
     */
    public static function get(int $costcenterid): array {
        $tenants = [
            self::AIRPAY => [
                'name'          => 'Airpay Academy',
                'logo_filename' => 'academy-logo-350.png',
                'primary_color' => '#0066A7',
                'accent_color'  => '#0f7a73',
            ],
            self::PUBLIC_TENANT => [
                'name'          => 'Airpay Learning',
                'logo_filename' => 'academy-logo-350.png',
                'primary_color' => '#0066A7',
                'accent_color'  => '#0f7a73',
            ],
            self::ZEEA => [
                'name'          => 'ZEEA Mafunzo',
                'logo_filename' => 'academy-logo-350.png',
                'primary_color' => '#0066A7',
                'accent_color'  => '#0f7a73',
            ],
        ];
        return $tenants[$costcenterid] ?? $tenants[self::AIRPAY];
    }

    /**
     * Get tenant config for a specific user.
     *
     * @param int $userid
     * @return array branding config
     */
    public static function get_for_user(int $userid): array {
        global $DB;
        try {
            $openpath = $DB->get_field('user', 'open_path', ['id' => $userid]);
            $parts = explode('/', trim($openpath ?? '', '/'));
            $costcenterid = (int)($parts[0] ?? 0);
        } catch (\Exception $e) {
            $costcenterid = 0;
        }
        return self::get($costcenterid);
    }

    /**
     * Build full logo URL for a tenant.
     *
     * @param int $costcenterid
     * @return string absolute URL
     */
    public static function get_logo_url(int $costcenterid = 0): string {
        global $CFG;
        $config = self::get($costcenterid);
        return $CFG->wwwroot . '/theme/sentientia/pix/brand/' . $config['logo_filename'];
    }
}
