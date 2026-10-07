<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Imported admin log - a read-only report (ADR-032, legacy_logs).
 *
 * Lists the BizLMS admin log (course created, updated, deleted) and the bulk course upload errors that the
 * legacy_logs importer copied into local_sentientia_admin_log. BizLMS had no screen for either table, so
 * this page is new, and it is behind the default-OFF flag sentientia.legacy_logs.report.enabled.
 *
 * Who sees what: the capability local/sentientia_core:viewadminlog (no role holds it by default) and the
 * tenant scope of ADR-031. A cross-tenant caller sees every row; anyone else sees the rows whose actor sat in
 * their own tenant; a row with no resolvable tenant is cross-tenant only. Nothing on this page writes.
 *
 * @package   local_sentientia_core
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_core\admin_log;

require_login();
$context = context_system::instance();
require_capability('local/sentientia_core:viewadminlog', $context);

$source = optional_param('source', '', PARAM_ALPHANUMEXT);
$event = optional_param('event', '', PARAM_TEXT);
$module = optional_param('module', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$baseurl = new moodle_url('/local/sentientia_core/admin_log.php', ['source' => $source, 'event' => $event, 'module' => $module]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('adminlog', 'local_sentientia_core'));
$PAGE->set_heading(get_string('adminlog', 'local_sentientia_core'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adminlog', 'local_sentientia_core'));

if (!admin_log::report_enabled()) {
    echo $OUTPUT->notification(get_string('adminlog_flagoff', 'local_sentientia_core'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('adminlog_intro', 'local_sentientia_core'));

if (admin_log::scope() === null) {
    // ADR-031: a caller with no resolvable tenant sees nothing.
    echo $OUTPUT->notification(get_string('adminlog_notenant', 'local_sentientia_core'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Filters. The selects offer what the table holds; the values are only ever bound as parameters.
$options = admin_log::filter_options();
$any = ['' => get_string('adminlog_filter_any', 'local_sentientia_core')];
$sourceoptions = $any;
foreach (array_keys($options['source']) as $value) {
    $key = 'adminlog_source_' . $value;
    $sourceoptions[$value] = get_string_manager()->string_exists($key, 'local_sentientia_core')
        ? get_string($key, 'local_sentientia_core') : $value;
}
$eventoptions = $any + array_combine(array_keys($options['event']), array_keys($options['event']));
$moduleoptions = $any + array_combine(array_keys($options['module']), array_keys($options['module']));

echo html_writer::start_tag('form', ['method' => 'get', 'action' => (new moodle_url('/local/sentientia_core/admin_log.php'))->out(false),
    'class' => 'd-flex flex-wrap align-items-end mb-3']);
foreach ([
    ['source', 'adminlog_col_source', $sourceoptions, $source],
    ['event', 'adminlog_col_event', $eventoptions, $event],
    ['module', 'adminlog_col_module', $moduleoptions, $module],
] as [$name, $labelkey, $choices, $current]) {
    echo html_writer::start_div('mr-3 mb-2');
    echo html_writer::tag('label', get_string($labelkey, 'local_sentientia_core'),
        ['for' => 'adminlog_' . $name, 'class' => 'd-block']);
    echo html_writer::select($choices, $name, $current, false, ['id' => 'adminlog_' . $name, 'class' => 'custom-select']);
    echo html_writer::end_div();
}
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary mb-2',
    'value' => get_string('adminlog_filter_apply', 'local_sentientia_core')]);
echo html_writer::end_tag('form');

$filters = ['source' => $source, 'event' => $event, 'module' => $module];
$total = admin_log::count($filters);
echo html_writer::tag('p', get_string('adminlog_total', 'local_sentientia_core', $total), ['class' => 'text-muted']);

if ($total === 0) {
    echo $OUTPUT->notification(get_string('adminlog_noentries', 'local_sentientia_core'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table-sm';
$table->head = [
    get_string('adminlog_col_when', 'local_sentientia_core'),
    get_string('adminlog_col_who', 'local_sentientia_core'),
    get_string('adminlog_col_source', 'local_sentientia_core'),
    get_string('adminlog_col_event', 'local_sentientia_core'),
    get_string('adminlog_col_module', 'local_sentientia_core'),
    get_string('adminlog_col_item', 'local_sentientia_core'),
    get_string('adminlog_col_description', 'local_sentientia_core'),
];
foreach (admin_log::page($filters, $page, $perpage) as $row) {
    if ((int) $row->userid <= 0 || $row->firstname === null) {
        $who = get_string('adminlog_actor_unknown', 'local_sentientia_core');
    } else if ((int) $row->actor_deleted === 1) {
        $who = get_string('adminlog_actor_deleted', 'local_sentientia_core', s(fullname($row)));
    } else {
        $who = s(fullname($row));
    }
    $sourcekey = 'adminlog_source_' . $row->source;
    $table->data[] = [
        admin_log::when((int) $row->timecreated),
        $who,
        get_string_manager()->string_exists($sourcekey, 'local_sentientia_core')
            ? get_string($sourcekey, 'local_sentientia_core') : s($row->source),
        s($row->event),
        s($row->module),
        s((string) $row->itemref),
        s($row->description),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

echo $OUTPUT->footer();
