<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Unified WhatsApp admin landing page.
 *
 * C14 / F-082 (selected) stabilization fix (2026-05-28). The audit
 * surfaced that `local_sentientia_whatsapp` had two admin pages
 * (admin/analytics.php + admin/templates.php) but no unified
 * landing — an admin clicking "WhatsApp" in any nav had to know
 * which page to pick. This landing page surfaces both + a quick
 * stats snapshot.
 *
 * Layout: header + summary card row + two-button quick nav.
 *   - Card 1: messages sent in last 7 days (from send_log)
 *   - Card 2: active DLT templates count
 *   - Card 3: send failures in last 24 hours
 *   - Card 4: feature-flag state (channel.whatsapp.enabled)
 *
 * @package    local_sentientia_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_sentientia_whatsapp_index',
    '', null, '', ['pagelayout' => 'admin']);

global $DB, $OUTPUT, $PAGE;
$PAGE->set_url('/local/sentientia_whatsapp/admin/index.php');
$PAGE->set_title(get_string('admin_index_title', 'local_sentientia_whatsapp'));
$PAGE->set_heading(get_string('admin_index_title', 'local_sentientia_whatsapp'));

// ── Stats snapshot ─────────────────────────────────────────────────
$week_ago = time() - (7 * 24 * 60 * 60);
$day_ago  = time() - (24 * 60 * 60);

$sent_week     = $DB->record_exists('local_sentientia_send_log', [])
    ? $DB->count_records_select('local_sentientia_send_log',
        'channel = :channel AND timecreated >= :since',
        ['channel' => 'whatsapp', 'since' => $week_ago])
    : 0;

$active_templates = 0;
if ($DB->get_manager()->table_exists('local_sentientia_whatsapp_templates')) {
    $active_templates = $DB->count_records('local_sentientia_whatsapp_templates',
        ['status' => 'approved']);
}

$failures_24h = $DB->record_exists('local_sentientia_send_log', [])
    ? $DB->count_records_select('local_sentientia_send_log',
        'channel = :channel AND status = :status AND timecreated >= :since',
        ['channel' => 'whatsapp', 'status' => 'failed', 'since' => $day_ago])
    : 0;

$wa_enabled = false;
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    $wa_enabled = \local_sentientia_platform\feature_flags::is_enabled(
        'channel.whatsapp.enabled');
}

echo $OUTPUT->header();

// Intro
echo html_writer::tag('p',
    get_string('admin_index_intro', 'local_sentientia_whatsapp'),
    ['class' => 'lead']);

// ── Quick-stats card row ───────────────────────────────────────────
echo html_writer::start_div('row mt-4 ap-wa-stats');

$stats = [
    [
        'icon'   => 'fa-paper-plane',
        'value'  => $sent_week,
        'label'  => get_string('stats_sent_week', 'local_sentientia_whatsapp'),
        'class'  => 'text-primary',
    ],
    [
        'icon'   => 'fa-file-text-o',
        'value'  => $active_templates,
        'label'  => get_string('stats_active_templates', 'local_sentientia_whatsapp'),
        'class'  => 'text-success',
    ],
    [
        'icon'   => 'fa-exclamation-triangle',
        'value'  => $failures_24h,
        'label'  => get_string('stats_failures_24h', 'local_sentientia_whatsapp'),
        'class'  => $failures_24h > 0 ? 'text-danger' : 'text-secondary',
    ],
    [
        'icon'   => $wa_enabled ? 'fa-toggle-on' : 'fa-toggle-off',
        'value'  => $wa_enabled
            ? get_string('stats_flag_on',  'local_sentientia_whatsapp')
            : get_string('stats_flag_off', 'local_sentientia_whatsapp'),
        'label'  => get_string('stats_flag_label', 'local_sentientia_whatsapp'),
        'class'  => $wa_enabled ? 'text-success' : 'text-warning',
    ],
];

foreach ($stats as $s) {
    echo html_writer::start_div('col-md-3 col-sm-6 mb-3');
    echo html_writer::start_div('card h-100 ap-wa-stat-card');
    echo html_writer::start_div('card-body text-center');
    echo html_writer::tag('i', '',
        ['class' => 'fa fa-3x ' . $s['icon'] . ' ' . $s['class'] . ' mb-2',
         'aria-hidden' => 'true']);
    echo html_writer::tag('h3', s((string) $s['value']),
        ['class' => 'mb-1 ' . $s['class']]);
    echo html_writer::tag('p', s($s['label']),
        ['class' => 'text-muted small mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

// ── Quick-nav buttons ──────────────────────────────────────────────
echo html_writer::tag('h4',
    get_string('admin_index_quicknav', 'local_sentientia_whatsapp'),
    ['class' => 'mt-5']);

echo html_writer::start_div('list-group');

// Templates link
$tmpl_url = new moodle_url('/local/sentientia_whatsapp/admin/templates.php');
echo html_writer::link($tmpl_url,
    html_writer::tag('i', '', ['class' => 'fa fa-file-text-o me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_templates', 'local_sentientia_whatsapp'))
    . html_writer::tag('p',
        get_string('admin_index_link_templates_desc', 'local_sentientia_whatsapp'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

// Analytics link
$ana_url = new moodle_url('/local/sentientia_whatsapp/admin/analytics.php');
echo html_writer::link($ana_url,
    html_writer::tag('i', '', ['class' => 'fa fa-line-chart me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_analytics', 'local_sentientia_whatsapp'))
    . html_writer::tag('p',
        get_string('admin_index_link_analytics_desc', 'local_sentientia_whatsapp'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

// Settings link (admin/settings.php?section=local_sentientia_whatsapp)
$set_url = new moodle_url('/admin/settings.php',
    ['section' => 'local_sentientia_whatsapp']);
echo html_writer::link($set_url,
    html_writer::tag('i', '', ['class' => 'fa fa-cog me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_settings', 'local_sentientia_whatsapp'))
    . html_writer::tag('p',
        get_string('admin_index_link_settings_desc', 'local_sentientia_whatsapp'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

echo html_writer::end_div();

echo $OUTPUT->footer();
