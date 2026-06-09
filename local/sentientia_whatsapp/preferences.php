<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * User-facing communication preferences page — Phase A1 iter 1.
 *
 * Lets a learner opt in/out of WhatsApp + SMS delivery, capture their
 * mobile number, choose a primary channel, and give DLT consent.
 * Tenant-scoped via the Phase A0 feature_flags resolver — channels
 * the super admin has disabled for this tenant render as inactive
 * sections with a "contact your administrator" hint.
 *
 * @package local_sentientia_whatsapp
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

global $USER, $DB, $OUTPUT, $PAGE;

// Per the plan, this is a self-service page — only the user themselves
// can edit their preferences. No admin-on-behalf-of in iter 1.
$context = context_user::instance($USER->id);

$PAGE->set_url(new moodle_url('/local/sentientia_whatsapp/preferences.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('preferences_pagetitle', 'local_sentientia_whatsapp'));
$PAGE->set_heading(get_string('preferences_heading', 'local_sentientia_whatsapp'));
$PAGE->set_pagelayout('standard');

// Resolve per-tenant feature flag state. Phase A0's feature_flags::
// is_enabled() reads the user's open_path and applies the right
// tenant override. Falls back to "always disabled" if sentientia_platform
// isn't installed (e.g. during initial setup).
$whatsapp_enabled = class_exists('\\local_sentientia_platform\\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled('engagement.whatsapp.enabled');
$sms_enabled = class_exists('\\local_sentientia_platform\\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled('engagement.sms.enabled');

$prefs = \local_sentientia_whatsapp\preference_manager::get($USER->id);

// Handle POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // Read inputs with strict typing. PARAM_TEXT strips multi-byte
    // garbage, optional_param applies a sane default.
    $mobile        = optional_param('mobile_number', '', PARAM_TEXT);
    $whatsapp      = optional_param('whatsapp_optin', 0, PARAM_BOOL);
    $sms           = optional_param('sms_optin', 0, PARAM_BOOL);
    $email_optin   = optional_param('email_optin', 1, PARAM_BOOL);
    $prefer        = optional_param('prefer_channel', 'email', PARAM_ALPHA);
    $consent_box   = optional_param('dlt_consent', 0, PARAM_BOOL);

    // Force-disable channels that the tenant flag is off for. The user
    // can tick the box but the gate is enforced server-side so a stale
    // browser session can't bypass admin control.
    if (!$whatsapp_enabled) {
        $whatsapp = 0;
    }
    if (!$sms_enabled) {
        $sms = 0;
    }

    // Validate prefer_channel — if user picked a disabled channel,
    // silently downgrade to email rather than error out.
    if ($prefer === 'whatsapp' && !$whatsapp_enabled) {
        $prefer = 'email';
    }
    if ($prefer === 'sms' && !$sms_enabled) {
        $prefer = 'email';
    }
    if (!in_array($prefer, \local_sentientia_whatsapp\preference_manager::VALID_CHANNELS, true)) {
        $prefer = 'email';
    }

    $values = [
        'mobile_number'  => $mobile,
        'whatsapp_optin' => $whatsapp,
        'sms_optin'      => $sms,
        'email_optin'    => $email_optin,
        'prefer_channel' => $prefer,
    ];

    // Snapshot the consent text when either WhatsApp or SMS is opted IN
    // for the first time (i.e. user ticked the consent box). DLT requires
    // we preserve the exact wording the user agreed to.
    if (($whatsapp || $sms) && $consent_box && empty($prefs->dlt_consent_at)) {
        $values['dlt_consent_text'] = get_string('dlt_consent_body',
            'local_sentientia_whatsapp');
        // dlt_consent_at is auto-stamped by preference_manager::set when
        // dlt_consent_text is being set for the first time.
    }

    try {
        \local_sentientia_whatsapp\preference_manager::set(
            $USER->id,
            $values,
            $USER->id,
            'self-service via /preferences.php'
        );
        redirect(
            new moodle_url('/local/sentientia_whatsapp/preferences.php'),
            get_string('preferences_saved', 'local_sentientia_whatsapp'),
            2,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\moodle_exception $e) {
        redirect(
            new moodle_url('/local/sentientia_whatsapp/preferences.php'),
            $e->getMessage(),
            5,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Build template data for GET.
$data = [
    'sesskey'           => sesskey(),
    'action_url'        => (new moodle_url('/local/sentientia_whatsapp/preferences.php'))->out(false),

    'mobile_number'     => $prefs->mobile_number ?? '',
    'email_optin'       => !empty($prefs->email_optin),

    // WhatsApp section
    'whatsapp_enabled'  => $whatsapp_enabled,    // tenant flag
    'whatsapp_optin'    => !empty($prefs->whatsapp_optin),

    // SMS section
    'sms_enabled'       => $sms_enabled,
    'sms_optin'         => !empty($prefs->sms_optin),

    // Primary channel preference
    'prefer_email'      => ($prefs->prefer_channel ?? 'email') === 'email',
    'prefer_whatsapp'   => ($prefs->prefer_channel ?? '') === 'whatsapp',
    'prefer_sms'        => ($prefs->prefer_channel ?? '') === 'sms',

    // Consent state
    'has_consent'       => !empty($prefs->dlt_consent_at),
    'consent_logged_at' => !empty($prefs->dlt_consent_at)
        ? userdate($prefs->dlt_consent_at)
        : null,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_whatsapp/preferences', $data);
echo $OUTPUT->footer();
