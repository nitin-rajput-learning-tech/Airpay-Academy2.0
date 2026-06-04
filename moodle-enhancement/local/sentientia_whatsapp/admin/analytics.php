<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Channel analytics dashboard — Phase A1 iter 5.
 *
 * Reads from local_sentientia_send_log + the analytics class to render:
 *   - KPI tiles (using the canonical stat_card from theme_airpayux)
 *   - Cost summary
 *   - Recent activity feed (using activity_item partial)
 *
 * Composes the existing reusable components from Phase B0.
 *
 * @package local_sentientia_whatsapp
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

global $OUTPUT, $PAGE;

$PAGE->set_url('/local/sentientia_whatsapp/admin/analytics.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('analytics_pagetitle', 'local_sentientia_whatsapp'));
$PAGE->set_heading(get_string('analytics_heading', 'local_sentientia_whatsapp'));

// Date range — last 30 days by default.
$since_days = optional_param('since', 30, PARAM_INT);
$since_ts = time() - ($since_days * 86400);

$mix = \local_sentientia_whatsapp\analytics::channel_mix($since_ts);
$cost = \local_sentientia_whatsapp\analytics::cost_summary($since_ts);
$recent = \local_sentientia_whatsapp\analytics::recent_log(20);

// Build KPI tiles using the Phase B0 stat_card partial shape.
$kpi_tiles = [
    [
        'label' => 'Attempted (last ' . $since_days . 'd)',
        'value' => number_format($mix['totals']['attempted']),
        'icon'  => 'paper-plane',
        'color' => 'primary',
    ],
    [
        'label' => 'Successful',
        'value' => number_format($mix['totals']['successful'])
                    . ' (' . $mix['totals']['success_pct'] . '%)',
        'icon'  => 'check-circle',
        // Audit fix H3 (2026-05-15): when there are zero attempts, the
        // success_pct is 0 by definition, not a failure — paint the tile
        // neutral/info instead of danger so a brand-new install does not
        // look broken.
        'color' => $mix['totals']['attempted'] === 0 ? 'info'
                : ($mix['totals']['success_pct'] >= 90 ? 'success'
                : ($mix['totals']['success_pct'] >= 70 ? 'warning' : 'danger')),
    ],
    [
        'label' => 'Mocked (dev / disabled)',
        'value' => $mix['totals']['mocked_pct'] . '%',
        'icon'  => 'eye-slash',
        // Audit fix H3 (2026-05-15): same zero-attempts guard so the
        // "0% Mocked" tile reads as neutral, not a warning, on a fresh
        // install.
        'color' => $mix['totals']['attempted'] === 0 ? 'info'
                : ($mix['totals']['mocked_pct'] === 100 ? 'info' : 'warning'),
    ],
    [
        'label' => 'Cost estimate',
        'value' => '₹' . number_format($cost['total_inr'], 2),
        'icon'  => 'inr',
        'color' => 'accent',
    ],
];

// Build activity feed using the Phase B0 activity_item partial shape.
$activity_feed = [];
foreach ($recent as $r) {
    $variant = match ($r->status) {
        'sent', 'delivered' => 'completion',
        'mocked'            => 'submission',
        'failed', 'bounced' => 'alert',
        'opted_out'         => 'default',
        default             => 'default',
    };
    $activity_feed[] = [
        'text'    => "$r->channel → $r->template_key" . ($r->recipient ? " ($r->recipient)" : ''),
        'subtext' => userdate($r->timecreated, '%d %b %Y %I:%M %p')
                     . ' · status: ' . $r->status,
        'icon'    => match ($r->channel) {
            'whatsapp' => 'whatsapp',
            'sms'      => 'mobile',
            'email'    => 'envelope',
            default    => 'circle',
        },
        'variant' => $variant,
        'layout'  => 'inline',
    ];
}

$data = [
    'kpi_tiles'         => $kpi_tiles,
    'has_kpi_tiles'     => !empty($kpi_tiles),
    'activity_feed'     => $activity_feed,
    'has_activity_feed' => !empty($activity_feed),
    'since_days'        => $since_days,

    // Channel-by-channel breakdown for the per-channel cards.
    'channels' => [
        [
            'name'  => 'WhatsApp',
            'icon'  => 'whatsapp',
            'sent'      => $mix['whatsapp']['sent']      ?? 0,
            'mocked'    => $mix['whatsapp']['mocked']    ?? 0,
            'failed'    => $mix['whatsapp']['failed']    ?? 0,
            'opted_out' => $mix['whatsapp']['opted_out'] ?? 0,
        ],
        [
            'name'  => 'SMS',
            'icon'  => 'mobile',
            'sent'      => $mix['sms']['sent']      ?? 0,
            'mocked'    => $mix['sms']['mocked']    ?? 0,
            'failed'    => $mix['sms']['failed']    ?? 0,
            'opted_out' => $mix['sms']['opted_out'] ?? 0,
        ],
        [
            'name'  => 'Email',
            'icon'  => 'envelope',
            'sent'      => $mix['email']['sent']      ?? 0,
            'mocked'    => $mix['email']['mocked']    ?? 0,
            'failed'    => $mix['email']['failed']    ?? 0,
            'opted_out' => $mix['email']['opted_out'] ?? 0,
        ],
    ],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_whatsapp/analytics', $data);
echo $OUTPUT->footer();
