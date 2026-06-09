<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Translation — Admin queue/landing page.
 *
 * C16 stabilization fix (2026-05-28) from
 * docs/audits/PLATFORM-STABILIZATION-AUDIT-2026-05-28.md.
 *
 * The translation plugin shipped a working translate.php (single-shot
 * source-in / diff-out flow) plus a brand-override page, but no
 * landing/queue surface — so an admin's only way to find a translation
 * they did yesterday was to remember its title. This page is the
 * unified queue: it lists every translation row the actor can see
 * (own-only, or all-customer if they hold manage_all), with status
 * filtering and the standard 4-card stats snapshot used by C14
 * (sentientia_whatsapp/admin/index.php).
 *
 * Layout:
 *   - Header + intro
 *   - 4-card stats row (Total · Pending · Saved · Failed)
 *   - Filter chip row (status + target lang)
 *   - Table of recent N translations with action links to translate.php?rowid=
 *   - Quick-nav: New translation, Brand overrides, Settings
 *
 * @package    local_sentientia_translate
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_sentientia_translate\translate_engine;

require_login();
$context = context_system::instance();

// Site admins always pass; the manage_all capability is the soft gate
// for tenant-admins who hold the cap but aren't full site admins.
$can_view = has_capability('local/sentientia_translate:translate', $context)
    || has_capability('local/sentientia_translate:manage_all', $context)
    || has_capability('moodle/site:config', $context);
if (!$can_view) {
    throw new \required_capability_exception($context,
        'local/sentientia_translate:translate', 'nopermissions', '');
}

admin_externalpage_setup('local_sentientia_translate_queue',
    '', null, '', ['pagelayout' => 'admin']);

global $DB, $OUTPUT, $PAGE, $USER;
$PAGE->set_url('/local/sentientia_translate/admin/index.php');
$PAGE->set_title(get_string('admin_index_title', 'local_sentientia_translate'));
$PAGE->set_heading(get_string('admin_index_title', 'local_sentientia_translate'));

$manageall = has_capability('local/sentientia_translate:manage_all', $context)
    || has_capability('moodle/site:config', $context);

// ── Filter inputs ──────────────────────────────────────────────────
$status_filter = optional_param('status', '', PARAM_ALPHANUMEXT);
$lang_filter   = optional_param('lang', '', PARAM_ALPHA);

// ── Stats snapshot ─────────────────────────────────────────────────
// Scope: all customers if manage_all, otherwise actor's tenant +
// own rows. Mirror translate_engine::list_for_actor() scoping rules
// so the stats numbers match what the table can show.
$TABLE = 'local_sentientia_tr_log';

$base_where = '';
$base_params = [];
if (!$manageall) {
    $tenant = translate_engine::tenant_root_for($USER);
    $base_where = 'WHERE (ownerid = :uid OR costcenterid = :cid)';
    $base_params = ['uid' => (int) $USER->id, 'cid' => $tenant];
}

$total = (int) $DB->get_field_sql(
    "SELECT COUNT(*) FROM {{$TABLE}} {$base_where}",
    $base_params);

$count_for_status = function (string $status) use ($DB, $TABLE, $base_where, $base_params): int {
    $where = $base_where !== ''
        ? $base_where . ' AND status = :status'
        : 'WHERE status = :status';
    return (int) $DB->get_field_sql(
        "SELECT COUNT(*) FROM {{$TABLE}} {$where}",
        $base_params + ['status' => $status]);
};

$pending_count = $count_for_status('pending') + $count_for_status('translated');
$saved_count   = $count_for_status('saved');
$failed_count  = $count_for_status('failed');

// Feature flag state.
$flag_on = false;
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    $flag_on = \local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.enabled');
}

echo $OUTPUT->header();

// Intro
echo html_writer::tag('p',
    get_string('admin_index_intro', 'local_sentientia_translate'),
    ['class' => 'lead']);

// Feature flag alert (mirrors the pattern used by sentientia_aiquiz)
if (!$flag_on) {
    echo html_writer::div(
        html_writer::tag('i', '', ['class' => 'fa fa-info-circle me-2',
            'aria-hidden' => 'true'])
        . get_string('admin_index_flag_off_notice', 'local_sentientia_translate'),
        'alert alert-info');
}

// ── Quick-stats card row ───────────────────────────────────────────
echo html_writer::start_div('row mt-4 ap-tr-stats');

$stats = [
    [
        'icon'  => 'fa-language',
        'value' => $total,
        'label' => get_string('stats_total', 'local_sentientia_translate'),
        'class' => 'text-primary',
    ],
    [
        'icon'  => 'fa-hourglass-half',
        'value' => $pending_count,
        'label' => get_string('stats_pending', 'local_sentientia_translate'),
        'class' => $pending_count > 0 ? 'text-warning' : 'text-secondary',
    ],
    [
        'icon'  => 'fa-check-circle',
        'value' => $saved_count,
        'label' => get_string('stats_saved', 'local_sentientia_translate'),
        'class' => 'text-success',
    ],
    [
        'icon'  => 'fa-exclamation-triangle',
        'value' => $failed_count,
        'label' => get_string('stats_failed', 'local_sentientia_translate'),
        'class' => $failed_count > 0 ? 'text-danger' : 'text-secondary',
    ],
];

foreach ($stats as $s) {
    echo html_writer::start_div('col-md-3 col-sm-6 mb-3');
    echo html_writer::start_div('card h-100 ap-tr-stat-card');
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

// ── Filter chip row ────────────────────────────────────────────────
echo html_writer::tag('h4',
    get_string('admin_index_queue', 'local_sentientia_translate'),
    ['class' => 'mt-5']);

echo html_writer::start_div('mb-3 ap-tr-filters');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/sentientia_translate/admin/index.php'))->out(false),
    'class'  => 'd-inline-flex flex-wrap gap-2 align-items-center',
]);

// Status dropdown
echo html_writer::tag('label', get_string('filter_status', 'local_sentientia_translate'),
    ['for' => 'status-filter', 'class' => 'form-label me-2 mb-0']);
echo html_writer::select([
    ''            => get_string('filter_all', 'local_sentientia_translate'),
    'pending'     => get_string('status_pending', 'local_sentientia_translate'),
    'translated'  => get_string('status_translated', 'local_sentientia_translate'),
    'saved'       => get_string('status_saved', 'local_sentientia_translate'),
    'failed'      => get_string('status_failed', 'local_sentientia_translate'),
    'discarded'   => get_string('status_discarded', 'local_sentientia_translate'),
], 'status', $status_filter, false, [
    'id' => 'status-filter', 'class' => 'form-select form-select-sm me-3',
]);

// Lang dropdown
echo html_writer::tag('label', get_string('filter_lang', 'local_sentientia_translate'),
    ['for' => 'lang-filter', 'class' => 'form-label me-2 mb-0']);
echo html_writer::select([
    ''   => get_string('filter_all', 'local_sentientia_translate'),
    'hi' => 'Hindi (hi)',
    'mr' => 'Marathi (mr)',
    'kn' => 'Kannada (kn)',
    'sw' => 'Swahili (sw)',
], 'lang', $lang_filter, false, [
    'id' => 'lang-filter', 'class' => 'form-select form-select-sm me-3',
]);

echo html_writer::tag('button',
    get_string('filter_apply', 'local_sentientia_translate'),
    ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']);

if ($status_filter !== '' || $lang_filter !== '') {
    echo html_writer::link(
        (new moodle_url('/local/sentientia_translate/admin/index.php'))->out(false),
        get_string('filter_reset', 'local_sentientia_translate'),
        ['class' => 'btn btn-sm btn-link']);
}

echo html_writer::end_tag('form');
echo html_writer::end_div();

// ── Queue table ────────────────────────────────────────────────────
$where_parts = [];
$params = [];
if (!$manageall) {
    $tenant = translate_engine::tenant_root_for($USER);
    $where_parts[] = '(ownerid = :uid OR costcenterid = :cid)';
    $params['uid'] = (int) $USER->id;
    $params['cid'] = $tenant;
}
if ($status_filter !== '') {
    $where_parts[] = 'status = :status';
    $params['status'] = $status_filter;
}
if ($lang_filter !== '') {
    $where_parts[] = 'targetlang = :lang';
    $params['lang'] = $lang_filter;
}
$where_clause = !empty($where_parts)
    ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$rows = $DB->get_records_sql(
    "SELECT * FROM {{$TABLE}} {$where_clause} ORDER BY timecreated DESC",
    $params, 0, 25);

if (empty($rows)) {
    echo html_writer::tag('p',
        get_string('admin_index_empty', 'local_sentientia_translate'),
        ['class' => 'text-muted fst-italic']);
} else {
    $table = new \html_table();
    $table->attributes['class'] = 'generaltable ap-tr-queue-table';
    $table->head = [
        get_string('col_title',       'local_sentientia_translate'),
        get_string('col_lang',        'local_sentientia_translate'),
        get_string('col_status',      'local_sentientia_translate'),
        get_string('col_tokens',      'local_sentientia_translate'),
        get_string('col_created',     'local_sentientia_translate'),
        get_string('col_actions',     'local_sentientia_translate'),
    ];

    foreach ($rows as $r) {
        $status_badge_class = match ($r->status) {
            'saved'      => 'bg-success',
            'pending', 'translated' => 'bg-warning text-dark',
            'failed'     => 'bg-danger',
            'discarded'  => 'bg-secondary',
            default      => 'bg-light text-dark',
        };
        $action_url = new moodle_url('/local/sentientia_translate/translate.php',
            ['rowid' => $r->id]);
        $action_label = $r->status === 'translated'
            ? get_string('action_review', 'local_sentientia_translate')
            : get_string('action_open', 'local_sentientia_translate');

        $table->data[] = [
            format_string($r->title),
            html_writer::tag('code', s(strtoupper($r->targetlang))),
            html_writer::tag('span',
                s(get_string('status_' . $r->status, 'local_sentientia_translate')),
                ['class' => 'badge ' . $status_badge_class]),
            number_format((int) ($r->tokens_in ?? 0) + (int) ($r->tokens_out ?? 0)),
            userdate($r->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            html_writer::link($action_url,
                $action_label,
                ['class' => 'btn btn-sm btn-outline-primary']),
        ];
    }

    echo html_writer::table($table);

    if (count($rows) === 25) {
        echo html_writer::tag('p',
            get_string('admin_index_truncated', 'local_sentientia_translate'),
            ['class' => 'text-muted small fst-italic']);
    }
}

// ── Quick-nav buttons ──────────────────────────────────────────────
echo html_writer::tag('h4',
    get_string('admin_index_quicknav', 'local_sentientia_translate'),
    ['class' => 'mt-5']);

echo html_writer::start_div('list-group');

$translate_url = new moodle_url('/local/sentientia_translate/translate.php');
echo html_writer::link($translate_url,
    html_writer::tag('i', '', ['class' => 'fa fa-plus-square me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_translate', 'local_sentientia_translate'))
    . html_writer::tag('p',
        get_string('admin_index_link_translate_desc', 'local_sentientia_translate'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

$brands_url = new moodle_url('/local/sentientia_translate/brands.php');
echo html_writer::link($brands_url,
    html_writer::tag('i', '', ['class' => 'fa fa-tags me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_brands', 'local_sentientia_translate'))
    . html_writer::tag('p',
        get_string('admin_index_link_brands_desc', 'local_sentientia_translate'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

$settings_url = new moodle_url('/admin/settings.php',
    ['section' => 'local_sentientia_translate']);
echo html_writer::link($settings_url,
    html_writer::tag('i', '', ['class' => 'fa fa-cog me-2 fa-fw'])
    . html_writer::tag('strong',
        get_string('admin_index_link_settings', 'local_sentientia_translate'))
    . html_writer::tag('p',
        get_string('admin_index_link_settings_desc', 'local_sentientia_translate'),
        ['class' => 'text-muted small mb-0']),
    ['class' => 'list-group-item list-group-item-action']);

echo html_writer::end_div();

echo $OUTPUT->footer();
