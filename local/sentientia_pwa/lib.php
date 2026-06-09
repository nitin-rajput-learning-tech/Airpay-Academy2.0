<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Sentientia LMS PWA — library callbacks.
 *
 * Phase B.2.b additions:
 *   - extend_navigation_user_settings  → adds "Browser notifications" to
 *                                          the user profile settings nav
 *   - user_preferences                  → declares the boolean push opt-in
 *                                          preference (used by Phase B.3
 *                                          when wiring into the message
 *                                          processor pipeline)
 *
 * @package local_sentientia_pwa
 */

/**
 * Add a "Browser notifications" link under the user's settings nav.
 *
 * Standard Moodle hook — fired when rendering the user profile settings
 * block. Only adds the link when:
 *   - The user is viewing their OWN profile settings (we don't expose
 *     this admin-side; users manage their own browsers)
 *   - The PWA parent feature flag is on
 *
 * @param navigation_node $usernode Parent settings node for the user.
 * @param stdClass        $user     User whose settings are being rendered.
 * @param context_user    $usercontext
 * @param stdClass        $course
 * @param context_course  $coursecontext
 * @return void
 */
function local_sentientia_pwa_extend_navigation_user_settings(
    navigation_node $usernode,
    \stdClass $user,
    \context_user $usercontext,
    \stdClass $course,
    \context_course $coursecontext
): void {
    global $USER;

    // Only add the node for the user themselves — admins editing another
    // user's profile can't toggle that user's browser subscriptions
    // (the subscription lives in the browser, not the server).
    if ((int) $user->id !== (int) $USER->id) {
        return;
    }

    // Respect the parent PWA feature flag.
    if (class_exists('\\local_sentientia_platform\\feature_flags')) {
        if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.pwa.enabled')) {
            return;
        }
    }

    $url = new \moodle_url('/local/sentientia_pwa/preferences.php');
    $label = get_string('nav_label', 'local_sentientia_pwa');

    $node = navigation_node::create(
        $label,
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'sentientia_pwa_prefs',
        new \pix_icon('i/notifications', '')
    );

    // Insert AFTER the "Notification preferences" node if it exists so
    // the two related items sit together. Otherwise append.
    $usernode->add_node($node);
}

// Legacy `before_standard_top_of_body_html` callback — Moodle 5.1 only.
//
// Moodle 5.2 introduced
// `\core\hook\output\before_standard_top_of_body_html_generation` and
// scans every plugin's lib.php for `<component>_<oldcallback>` function
// names; if it finds one, it fires it AND prints a deprecation notice
// (independent of whether a new-style hook subscription exists).
//
// To stay clean on 5.2 we CONDITIONALLY DEFINE the legacy function:
// only when the new hook class is not present (i.e. we're on 5.1).
// On 5.2 the canonical entry point is
// `\local_sentientia_pwa\hook_callbacks::before_standard_top_of_body_html_generation()`
// wired via `db/hooks.php`.
if (!class_exists('\\core\\hook\\output\\before_standard_top_of_body_html_generation')) {
    /**
     * Inject the PWA Install CTA + auto-load install_prompt AMD module
     * above the standard top-of-body HTML on the user dashboard (Phase D.1.b).
     *
     * @deprecated since Moodle 5.2 — see classes/hook_callbacks.php +
     *   db/hooks.php. This function is conditionally compiled out on 5.2
     *   to suppress the `process_legacy_callbacks()` deprecation notice.
     *
     * @return string HTML to inject. Empty string when the feature flag
     *                is off / page isn't mydashboard / user dismissed
     *                recently.
     */
    function local_sentientia_pwa_before_standard_top_of_body_html(): string {
        // Delegate to the same builder the hook class uses — single
        // source of truth across 5.1 and 5.2.
        if (class_exists('\\local_sentientia_pwa\\hook_callbacks')) {
            return \local_sentientia_pwa\hook_callbacks::build_install_cta_html();
        }
        return '';
    }
}

/**
 * Declare user preferences this plugin uses.
 *
 * Phase B.2.b: just one bool preference indicating whether the user has
 * consented to push notifications (set when they click "Enable browser
 * notifications"). Phase B.3 reads this from the message processor.
 *
 * @return array Mapping of preference name → metadata.
 */
function local_sentientia_pwa_user_preferences(): array {
    return [
        'local_sentientia_pwa_push_optin' => [
            'type'    => PARAM_BOOL,
            'null'    => NULL_NOT_ALLOWED,
            'default' => 0,
            'choices' => [0, 1],
        ],
        // Phase D.1.b (2026-05-22 fix) — UNIX timestamp of the
        // user's last "Not now" click on the install CTA. Used by
        // local_sentientia_pwa_before_standard_top_of_body_html() to
        // gate the server-side render for 7 days.
        'local_sentientia_pwa_install_dismissed_at' => [
            'type'    => PARAM_INT,
            'null'    => NULL_NOT_ALLOWED,
            'default' => 0,
        ],
    ];
}
