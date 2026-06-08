<?php
namespace local_sentientia_challenge;

defined('MOODLE_INTERNAL') || die();

/**
 * Challenge renderer — stub replacing BizLMS local_challenge.
 *
 * core_renderer.php calls render_challenge_object() on this renderer.
 * Returns empty string until challenges are fully implemented.
 *
 * @package    local_sentientia_challenge
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class challenge_renderer extends \plugin_renderer_base {

    /**
     * Render challenge element for a course.
     *
     * @param string $area   Plugin context (e.g. 'local_sentientia_courses')
     * @param int    $itemid Course ID
     * @return string HTML (empty until implemented)
     */
    public function render_challenge_object(string $area, int $itemid): string {
        // Stub — return empty until challenge system is built.
        return '';
    }
}
