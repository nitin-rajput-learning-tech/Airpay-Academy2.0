<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Push delivery log viewer — Phase B.3.c.
 *
 * Admin-only operational view of every push that the sender attempted to
 * deliver. Filters by result + user + since. Paginated 50/page.
 *
 * @package local_sentientia_pwa
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_pwa:manage', $context);

$page    = optional_param('page',    0, PARAM_INT);
$result  = optional_param('result',  '', PARAM_ALPHA);
$userid  = optional_param('userid',  0, PARAM_INT);
$since_h = optional_param('since_h', 24, PARAM_INT);  // hours back

$perpage = 50;
$filters = [];
if ($result !== '' && in_array($result, ['sent', 'failed', 'gone', 'truncated'], true)) {
    $filters['result'] = $result;
}
if ($userid > 0) {
    $filters['userid'] = $userid;
}
if ($since_h > 0) {
    $filters['since'] = time() - ($since_h * 3600);
}

$PAGE->set_url('/local/sentientia_pwa/admin/push_log.php', [
    'page' => $page, 'result' => $result, 'userid' => $userid, 'since_h' => $since_h,
]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('push_log_page_title', 'local_sentientia_pwa'));
$PAGE->set_heading(get_string('push_log_page_heading', 'local_sentientia_pwa'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('push_log_page_heading', 'local_sentientia_pwa'));

// ── Stats banner ──
$stats = \local_sentientia_pwa\push_logger::stats_last_24h();
echo \html_writer::start_div('alert alert-info');
echo '<strong>' . get_string('push_log_stats_24h', 'local_sentientia_pwa') . ':</strong> ';
echo get_string('push_log_stats_line', 'local_sentientia_pwa', (object) $stats);
echo \html_writer::end_div();

// ── Filter form ──
$result_opts = [
    ''          => get_string('push_log_filter_any',    'local_sentientia_pwa'),
    'sent'      => get_string('push_log_filter_sent',   'local_sentientia_pwa'),
    'failed'    => get_string('push_log_filter_failed', 'local_sentientia_pwa'),
    'gone'      => get_string('push_log_filter_gone',   'local_sentientia_pwa'),
    'truncated' => get_string('push_log_filter_truncated', 'local_sentientia_pwa'),
];
$since_opts = [
    1   => get_string('push_log_since_1h',  'local_sentientia_pwa'),
    24  => get_string('push_log_since_24h', 'local_sentientia_pwa'),
    168 => get_string('push_log_since_7d',  'local_sentientia_pwa'),
    720 => get_string('push_log_since_30d', 'local_sentientia_pwa'),
    0   => get_string('push_log_since_all', 'local_sentientia_pwa'),
];

echo \html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $PAGE->url->out_omit_querystring(),
    'class'  => 'form-inline mb-3 d-flex flex-wrap gap-2',
]);
echo \html_writer::label(get_string('push_log_filter_result',
    'local_sentientia_pwa'), 'result_filter', false, ['class' => 'me-2 mt-2']);
echo \html_writer::select($result_opts, 'result', $result, false,
    ['id' => 'result_filter', 'class' => 'form-select me-3']);

echo \html_writer::label(get_string('push_log_filter_since',
    'local_sentientia_pwa'), 'since_filter', false, ['class' => 'me-2 mt-2']);
echo \html_writer::select($since_opts, 'since_h', $since_h, false,
    ['id' => 'since_filter', 'class' => 'form-select me-3']);

echo \html_writer::label(get_string('push_log_filter_userid',
    'local_sentientia_pwa'), 'userid_filter', false, ['class' => 'me-2 mt-2']);
echo \html_writer::empty_tag('input', [
    'type'  => 'number',
    'name'  => 'userid',
    'value' => $userid > 0 ? $userid : '',
    'id'    => 'userid_filter',
    'class' => 'form-control me-3',
    'style' => 'width: 6em;',
]);

echo \html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('push_log_filter_apply', 'local_sentientia_pwa'),
    'class' => 'btn btn-primary',
]);
echo \html_writer::end_tag('form');

// ── Results ──
$total = \local_sentientia_pwa\push_logger::count($filters);
$rows = \local_sentientia_pwa\push_logger::recent($perpage, $page * $perpage, $filters);

if ($total === 0) {
    echo \html_writer::tag('p',
        get_string('push_log_no_results', 'local_sentientia_pwa'),
        ['class' => 'text-muted text-center my-4']);
} else {
    echo \html_writer::tag('p',
        get_string('push_log_total_count', 'local_sentientia_pwa', $total),
        ['class' => 'text-muted']);

    $table = new \html_table();
    $table->head = [
        get_string('push_log_col_when',    'local_sentientia_pwa'),
        get_string('push_log_col_user',    'local_sentientia_pwa'),
        get_string('push_log_col_host',    'local_sentientia_pwa'),
        get_string('push_log_col_title',   'local_sentientia_pwa'),
        get_string('push_log_col_result',  'local_sentientia_pwa'),
        get_string('push_log_col_http',    'local_sentientia_pwa'),
        get_string('push_log_col_error',   'local_sentientia_pwa'),
    ];
    $table->attributes = ['class' => 'generaltable'];
    $table->data = [];

    foreach ($rows as $r) {
        $when = userdate((int) $r->sent_at, '%d %b %Y %H:%M:%S');

        $user_link = format_string(
            trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''))
        ) . ' (' . (int) $r->userid . ')';

        $result_html = '<span class="badge badge-'
            . ($r->result === 'sent'   ? 'success'
            : ($r->result === 'gone'   ? 'warning'
            : ($r->result === 'failed' ? 'danger' : 'secondary')))
            . '">' . s($r->result) . '</span>';

        $error = $r->error_detail !== null
            ? '<small class="text-muted">' . s(mb_substr($r->error_detail, 0, 100)) . '</small>'
            : '';

        $table->data[] = [
            $when,
            $user_link,
            s($r->endpoint_host ?? ''),
            s($r->title ?? ''),
            $result_html,
            $r->http_code !== null ? (int) $r->http_code : '—',
            $error,
        ];
    }

    echo \html_writer::table($table);

    // Paginator.
    if ($total > $perpage) {
        echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
    }
}

echo $OUTPUT->footer();
