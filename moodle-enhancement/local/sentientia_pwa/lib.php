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
    if (class_exists('\\local_airpay_core\\feature_flags')) {
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.pwa.enabled')) {
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

/**
 * Inject the PWA Install CTA + auto-load install_prompt AMD module
 * above the standard top-of-body HTML on every page where the
 * `sentientia.pwa.install.enabled` flag is ON. Phase D.1.b.
 *
 * Mounts a `.sentientia-install-cta` banner; the AMD module reveals
 * it when the browser fires beforeinstallprompt and hides it
 * otherwise (so this is invisible noise until the user is on a
 * browser that supports the install flow).
 *
 * Returns an empty string when the flag is off — zero-cost when the
 * feature isn't active.
 *
 * @return string HTML to inject
 */
function local_sentientia_pwa_before_standard_top_of_body_html(): string {
    global $PAGE, $OUTPUT;

    if (!class_exists('\\local_airpay_core\\feature_flags')) {
        return '';
    }
    try {
        if (!\local_airpay_core\feature_flags::is_enabled('sentientia.pwa.install.enabled')) {
            return '';
        }
    } catch (\Throwable $e) {
        return '';
    }

    // Only show on pages with a 'standard' layout — login pages,
    // SCORM viewers, popup pages don't need an install nag.
    $layout = $PAGE && $PAGE->pagelayout ? $PAGE->pagelayout : '';
    if (!in_array($layout, ['standard', 'mydashboard', 'mycourses', 'course'], true)) {
        return '';
    }

    // Skip when the page is itself the manifest or the SW (defensive).
    $url_path = $PAGE && $PAGE->url ? (string) $PAGE->url->out_omit_querystring() : '';
    if (str_contains($url_path, '/local/sentientia_pwa/')) {
        return '';
    }

    // Queue the AMD module init for this page.
    $PAGE->requires->js_call_amd('local_sentientia_pwa/install_prompt', 'init');

    // Render the hidden banner — AMD module reveals when beforeinstallprompt fires.
    try {
        return $OUTPUT->render_from_template('local_sentientia_pwa/install_cta', []);
    } catch (\Throwable $e) {
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
    ];
}
