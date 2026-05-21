<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_sentientia_pwa',
        get_string('pluginname', 'local_sentientia_pwa'));

    // ── VAPID status (read-only display) ──────────────────────────────
    $public_key = \local_sentientia_pwa\vapid_key_manager::get_public_key();
    $generated_at = \local_sentientia_pwa\vapid_key_manager::get_generated_at();
    $sub_count = 0;
    try {
        global $DB;
        $sub_count = $DB->count_records('local_sentientia_push_subs');
    } catch (\Throwable $e) {
        // Plugin not fully installed yet — count stays 0.
        $sub_count = 0;
    }

    $vapid_status_html = '';
    if ($public_key) {
        $vapid_status_html .= \html_writer::tag('p',
            get_string('settings_vapid_ready', 'local_sentientia_pwa'),
            ['class' => 'alert alert-success']);
        $vapid_status_html .= \html_writer::tag('p',
            '<strong>' . get_string('settings_vapid_public_label', 'local_sentientia_pwa') . ':</strong> ' .
            '<code style="word-break:break-all">' . s($public_key) . '</code>');
        if ($generated_at) {
            $vapid_status_html .= \html_writer::tag('p',
                '<strong>' . get_string('settings_vapid_generated_label', 'local_sentientia_pwa') . ':</strong> ' .
                userdate($generated_at));
        }
        $vapid_status_html .= \html_writer::tag('p',
            '<strong>' . get_string('settings_active_subs_label', 'local_sentientia_pwa') . ':</strong> ' .
            (int) $sub_count);
    } else {
        $vapid_status_html .= \html_writer::tag('p',
            get_string('settings_vapid_not_setup', 'local_sentientia_pwa'),
            ['class' => 'alert alert-warning']);
        $vapid_status_html .= \html_writer::tag('p',
            get_string('settings_vapid_cli_instruction', 'local_sentientia_pwa'),
            ['class' => 'small text-muted']);
        $vapid_status_html .= \html_writer::tag('pre',
            'cd ' . s($CFG->dirroot) . '\n' .
            'php local/sentientia_pwa/cli/generate_vapid_keys.php',
            ['class' => 'p-2 bg-light']);
    }

    $settings->add(new admin_setting_heading(
        'local_sentientia_pwa/vapid_status',
        get_string('settings_vapid_heading', 'local_sentientia_pwa'),
        $vapid_status_html
    ));

    // ── VAPID subject — operator contact for push providers ──
    // Uses PARAM_RAW_TRIMMED, not PARAM_URL, because the RFC 8292
    // contract requires a mailto: scheme, and PARAM_URL rejects
    // anything that is not http: / https:. The push_sender will
    // re-validate the scheme at send-time, so an invalid value here
    // simply causes that delivery to fall back to DEFAULT_SUBJECT
    // (no security risk from a permissive input filter).
    $settings->add(new admin_setting_configtext(
        'local_sentientia_pwa/vapid_subject',
        get_string('settings_vapid_subject_label', 'local_sentientia_pwa'),
        get_string('settings_vapid_subject_desc', 'local_sentientia_pwa'),
        \local_sentientia_pwa\vapid_key_manager::DEFAULT_SUBJECT,
        PARAM_RAW_TRIMMED
    ));

    // ── Push notification defaults ──
    $settings->add(new admin_setting_heading(
        'local_sentientia_pwa/push_defaults',
        get_string('settings_push_defaults_heading', 'local_sentientia_pwa'),
        get_string('settings_push_defaults_desc', 'local_sentientia_pwa')
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_pwa/default_ttl',
        get_string('settings_default_ttl_label', 'local_sentientia_pwa'),
        get_string('settings_default_ttl_desc', 'local_sentientia_pwa'),
        '86400',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_sentientia_pwa/max_payload_bytes',
        get_string('settings_max_payload_label', 'local_sentientia_pwa'),
        get_string('settings_max_payload_desc', 'local_sentientia_pwa'),
        '3500',
        PARAM_INT
    ));

    // ── Phase B.3.c — log retention ──
    $settings->add(new admin_setting_configtext(
        'local_sentientia_pwa/log_retention_days',
        get_string('settings_log_retention_label', 'local_sentientia_pwa'),
        get_string('settings_log_retention_desc',  'local_sentientia_pwa'),
        '90',
        PARAM_INT
    ));

    // Link to the log viewer.
    $log_url = new \moodle_url('/local/sentientia_pwa/admin/push_log.php');
    $settings->add(new admin_setting_heading(
        'local_sentientia_pwa/push_log_link',
        get_string('settings_push_log_link', 'local_sentientia_pwa'),
        '<p><a class="btn btn-secondary" href="' . $log_url->out(false) . '">'
            . get_string('settings_push_log_link', 'local_sentientia_pwa')
            . '</a></p>'
            . '<p class="text-muted small">'
            . get_string('settings_push_log_link_desc', 'local_sentientia_pwa')
            . '</p>'
    ));

    // Register under the "Plugins → Local plugins" admin tree.
    $ADMIN->add('localplugins', $settings);
}
