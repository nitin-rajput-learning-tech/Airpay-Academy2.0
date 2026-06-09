<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Microsoft 365 — OAuth admin landing.
 *
 * C15 stabilization fix (2026-05-28) from
 * docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md.
 *
 * Phase C.1 of the M365 integration shipped settings + classes but
 * no landing surface. An admin clicking "Microsoft 365" in any nav
 * had no way to see at a glance whether Azure was configured, whether
 * any user had ever connected, or where the roadmap stood. This page
 * is the unified status dashboard.
 *
 * Layout (mirrors C14 + C16 stats-card pattern):
 *   - Header + intro + feature-flag alert
 *   - 4-card OAuth-config status (Tenant ID set · Client ID set ·
 *     Feature flag · Connected tokens count)
 *   - Roadmap card listing C.1–C.6 with status badges
 *   - Quick-nav buttons (Settings · Test OAuth — gated · Privacy)
 *
 * Note: this page exposes ZERO live OAuth calls. C.1 is a scaffold —
 * test_oauth.php remains a confirm_required stub. C.2 is when
 * graph_client gets unwired and this page might grow a real "Test
 * connection" CTA.
 *
 * @package    local_sentientia_m365
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_sentientia_m365_index',
    '', null, '', ['pagelayout' => 'admin']);

global $DB, $OUTPUT, $PAGE;
$PAGE->set_url('/local/sentientia_m365/admin/index.php');
$PAGE->set_title(get_string('admin_index_title', 'local_sentientia_m365'));
$PAGE->set_heading(get_string('admin_index_title', 'local_sentientia_m365'));

// ── OAuth config probes ────────────────────────────────────────────
$tenant_id   = (string) get_config('local_sentientia_m365', 'azure_tenant_id');
$client_id   = (string) get_config('local_sentientia_m365', 'azure_client_id');
$redirect_uri = (string) get_config('local_sentientia_m365', 'redirect_uri');

$tenant_configured = trim($tenant_id) !== '';
$client_configured = trim($client_id) !== '';
$redirect_configured = trim($redirect_uri) !== '';
$fully_configured = $tenant_configured && $client_configured && $redirect_configured;

// Feature flag state.
$flag_on = false;
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    $flag_on = \local_sentientia_platform\feature_flags::is_enabled('sentientia_m365_enabled');
}

// Connected-tokens count (encrypted; we only count rows, never decrypt).
$tokens_count = 0;
if ($DB->get_manager()->table_exists('local_sentientia_m365_tokens')) {
    $tokens_count = $DB->count_records('local_sentientia_m365_tokens', []);
}

// is_ready check from msal_client — bundles the above checks + verifies
// the config is internally consistent (e.g. redirect_uri parseable).
$msal_ready = false;
if (class_exists('\\local_sentientia_m365\\msal_client')) {
    try {
        $msal_ready = \local_sentientia_m365\msal_client::is_ready();
    } catch (\Throwable $e) {
        // Defensive: never fail the landing because msal_client errored.
        $msal_ready = false;
    }
}

echo $OUTPUT->header();

// Intro
echo html_writer::tag('p',
    get_string('admin_index_intro', 'local_sentientia_m365'),
    ['class' => 'lead']);

// Feature flag alert
if (!$flag_on) {
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-info-circle me-2',
            'aria-hidden' => 'true'])
        . get_string('admin_index_flag_off_notice', 'local_sentientia_m365'),
        'alert alert-info');
}

// Live-call gate alert — Phase C.1 ships in confirm-required mode
echo html_writer::div(
    html_writer::tag('i', '', ['class' => 'fa fa-shield me-2',
        'aria-hidden' => 'true'])
    . get_string('admin_index_phase_c1_notice', 'local_sentientia_m365'),
    'alert alert-warning');

// ── Quick-stats card row ───────────────────────────────────────────
echo html_writer::start_div('row mt-4 ap-m365-stats');

$stats = [
    [
        'icon'  => $tenant_configured ? 'fa-check-circle' : 'fa-times-circle',
        'value' => $tenant_configured
            ? get_string('stats_configured', 'local_sentientia_m365')
            : get_string('stats_unconfigured', 'local_sentientia_m365'),
        'label' => get_string('stats_tenant_id', 'local_sentientia_m365'),
        'class' => $tenant_configured ? 'text-success' : 'text-warning',
    ],
    [
        'icon'  => $client_configured ? 'fa-check-circle' : 'fa-times-circle',
        'value' => $client_configured
            ? get_string('stats_configured', 'local_sentientia_m365')
            : get_string('stats_unconfigured', 'local_sentientia_m365'),
        'label' => get_string('stats_client_id', 'local_sentientia_m365'),
        'class' => $client_configured ? 'text-success' : 'text-warning',
    ],
    [
        'icon'  => $flag_on ? 'fa-toggle-on' : 'fa-toggle-off',
        'value' => $flag_on
            ? get_string('stats_flag_on',  'local_sentientia_m365')
            : get_string('stats_flag_off', 'local_sentientia_m365'),
        'label' => get_string('stats_flag_label', 'local_sentientia_m365'),
        'class' => $flag_on ? 'text-success' : 'text-warning',
    ],
    [
        'icon'  => 'fa-link',
        'value' => $tokens_count,
        'label' => get_string('stats_connected_users', 'local_sentientia_m365'),
        'class' => $tokens_count > 0 ? 'text-primary' : 'text-secondary',
    ],
];

foreach ($stats as $s) {
    echo html_writer::start_div('col-md-3 col-sm-6 mb-3');
    echo html_writer::start_div('card h-100 ap-m365-stat-card');
    echo html_writer::start_div('card-body text-center');
    echo html_writer::tag('i', '', [
        'class' => 'fa fa-3x ' . $s['icon'] . ' ' . $s['class'] . ' mb-2',
        'aria-hidden' => 'true',
    ]);
    echo html_writer::tag('h3', s((string) $s['value']),
        ['class' => 'mb-1 ' . $s['class']]);
    echo html_writer::tag('p', s($s['label']),
        ['class' => 'text-muted small mb-0']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

// ── msal_client readiness summary ──────────────────────────────────
echo html_writer::tag('h4',
    get_string('admin_index_readiness', 'local_sentientia_m365'),
    ['class' => 'mt-5']);

$ready_class = $msal_ready ? 'alert alert-success' : 'alert alert-warning';
$ready_icon  = $msal_ready ? 'fa-check-circle' : 'fa-exclamation-triangle';
$ready_text  = $msal_ready
    ? get_string('admin_index_ready_yes', 'local_sentientia_m365')
    : get_string('admin_index_ready_no', 'local_sentientia_m365');

echo html_writer::div(
    html_writer::tag('i', '',
        ['class' => 'fa ' . $ready_icon . ' me-2', 'aria-hidden' => 'true'])
    . $ready_text,
    $ready_class);

// ── Roadmap card ───────────────────────────────────────────────────
echo html_writer::tag('h4',
    get_string('admin_index_roadmap', 'local_sentientia_m365'),
    ['class' => 'mt-5']);

$roadmap = [
    ['phase' => 'C.1', 'status' => 'done',
     'label_key' => 'roadmap_c1', 'status_key' => 'roadmap_done'],
    ['phase' => 'C.2', 'status' => 'planned',
     'label_key' => 'roadmap_c2', 'status_key' => 'roadmap_planned'],
    ['phase' => 'C.3', 'status' => 'planned',
     'label_key' => 'roadmap_c3', 'status_key' => 'roadmap_planned'],
    ['phase' => 'C.4', 'status' => 'planned',
     'label_key' => 'roadmap_c4', 'status_key' => 'roadmap_planned'],
    ['phase' => 'C.5', 'status' => 'planned',
     'label_key' => 'roadmap_c5', 'status_key' => 'roadmap_planned'],
    ['phase' => 'C.6', 'status' => 'planned',
     'label_key' => 'roadmap_c6', 'status_key' => 'roadmap_planned'],
];

$table = new \html_table();
$table->attributes['class'] = 'generaltable ap-m365-roadmap-table';
$table->head = [
    get_string('roadmap_col_phase', 'local_sentientia_m365'),
    get_string('roadmap_col_what',  'local_sentientia_m365'),
    get_string('roadmap_col_status', 'local_sentientia_m365'),
];

foreach ($roadmap as $r) {
    $badge_class = $r['status'] === 'done' ? 'bg-success' : 'bg-secondary';
    $table->data[] = [
        html_writer::tag('strong', s($r['phase'])),
        s(get_string($r['label_key'], 'local_sentientia_m365')),
        html_writer::tag('span',
            s(get_string($r['status_key'], 'local_sentientia_m365')),
            ['class' => 'badge ' . $badge_class]),
    ];
}

echo html_writer::table($table);

// ── Quick-nav buttons ──────────────────────────────────────────────
echo html_writer::tag('h4',
    get_string('admin_index_quicknav', 'local_sentientia_m365'),
    ['class' => 'mt-5']);

echo html_writer::start_div('list-group');

// Settings link
$set_url = new moodle_url('/admin/settings.php',
    ['section' => 'local_sentientia_m365']);
echo html_writer::link($set_url,
    html_writer::tag('i', '', ['class' => 'fa fa-cog me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_settings', 'local_sentientia_m365'))
    . html_writer::tag('p',
        get_string('admin_index_link_settings_desc', 'local_sentientia_m365'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

// Privacy / data subject rights link
$privacy_url = new moodle_url('/admin/tool/dataprivacy/');
echo html_writer::link($privacy_url,
    html_writer::tag('i', '', ['class' => 'fa fa-shield me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_privacy', 'local_sentientia_m365'))
    . html_writer::tag('p',
        get_string('admin_index_link_privacy_desc', 'local_sentientia_m365'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

echo html_writer::end_div();

// Footer note: this page does NOT trigger any OAuth round trips —
// just config reads + a token-row count. Phase C.2 will introduce
// a real "Test connection" CTA, gated per ADR & CLAUDE.md §3.
echo html_writer::tag('p',
    get_string('admin_index_footnote', 'local_sentientia_m365'),
    ['class' => 'text-muted small fst-italic mt-5']);

echo $OUTPUT->footer();
