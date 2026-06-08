<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Subscribe-to-push renderable (Phase B.2.b).
 *
 * Rendered on /local/sentientia_pwa/preferences.php and any other surface
 * that wants to expose the subscribe button. The template injects the
 * VAPID public key into a data- attribute on the root div; the AMD
 * module reads it from there.
 *
 * If no keypair exists yet (admin hasn't run generate_vapid_keys.php),
 * a "not set up" message renders instead of the button.
 *
 * @package local_sentientia_pwa
 */
class subscribe_widget implements \renderable, \templatable {

    /**
     * @param \renderer_base $output
     * @return array Mustache context.
     */
    public function export_for_template(\renderer_base $output): array {
        $public_key = \local_sentientia_pwa\vapid_key_manager::get_public_key();
        $isready = !empty($public_key);

        return [
            'vapidPublicKey' => $public_key ?? '',
            'isready'        => $isready,
            'pluginnotsetup' => !$isready,
            'pushflag_on'    => $this->is_push_flag_on(),
            'wwwroot'        => (new \moodle_url('/'))->out(false),
        ];
    }

    /**
     * Is the push feature flag ON?
     *
     * Even if the keypair exists, the flag may be off (kill-switch). The
     * template hides the button if the flag is off so users don't get a
     * misleading "subscribed" status that won't actually deliver pushes.
     */
    private function is_push_flag_on(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            // No flag system available — fall open for dev environments.
            return true;
        }
        return \local_sentientia_platform\feature_flags::is_enabled('sentientia.pwa.push.enabled');
    }
}
