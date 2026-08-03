<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LRS Statement Viewer — admin UI.
 *
 * Shows recent xAPI statements stored in the Sentientia LRS.
 * Requires local/sentientia_xapi:viewstatements capability.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$context = context_system::instance();
require_capability('local/sentientia_xapi:viewstatements', $context);

// Feature flag gate.
if (class_exists('\local_sentientia_platform\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.xapi.enabled')) {
    notice(get_string('error_lrs_disabled', 'local_sentientia_xapi'),
        new moodle_url('/admin/index.php'));
}

$PAGE->set_url('/local/sentientia_xapi/index.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('lrs_viewer_title', 'local_sentientia_xapi'));
$PAGE->set_heading(get_string('lrs_viewer_heading', 'local_sentientia_xapi'));

// Resolve tenant for non-admin viewers.
$costcenterid = 0;
if (!is_siteadmin()) {
    if (class_exists('\local_sentientia_platform\tenant')) {
        $costcenterid = \local_sentientia_platform\tenant::root_for_current_user();
    }
    // Fail closed: a non-admin viewer whose tenant cannot be resolved to a valid positive
    // root must NOT fall through to get_statements(0, ...), which omits the costcenterid
    // filter and returns ALL tenants' statements (cross-tenant actor-PII leak). This also
    // covers the case where the platform plugin is absent (so the flag gate above was
    // skipped entirely) — the viewer stays reachable but cannot leak other tenants' data.
    if ($costcenterid <= 0) {
        notice(get_string('error_lrs_tenant', 'local_sentientia_xapi'),
            new moodle_url('/admin/index.php'));
    }
}

// Pagination params.
$limit  = optional_param('limit', 50, PARAM_INT);
$offset = optional_param('offset', 0, PARAM_INT);
$limit  = min((int) $limit, 500);
$offset = max((int) $offset, 0);

$lrs        = new \local_sentientia_xapi\lrs\store();
$statements = $lrs->get_statements($costcenterid, [], $limit, $offset);

echo $OUTPUT->header();

// LRS endpoint URL box.
$endpoint_url = new moodle_url('/local/sentientia_xapi/lrs/statements.php');
echo html_writer::tag('p',
    html_writer::tag('strong', get_string('lrs_endpoint_label', 'local_sentientia_xapi'))
    . ' ' . html_writer::tag('code', s($endpoint_url->out(false)))
);
echo html_writer::tag('p', get_string('lrs_endpoint_desc', 'local_sentientia_xapi'));

if (empty($statements)) {
    echo $OUTPUT->notification(get_string('lrs_no_statements', 'local_sentientia_xapi'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('lrs_col_timestamp', 'local_sentientia_xapi'),
        get_string('lrs_col_actor', 'local_sentientia_xapi'),
        get_string('lrs_col_verb', 'local_sentientia_xapi'),
        get_string('lrs_col_object', 'local_sentientia_xapi'),
        get_string('lrs_col_score', 'local_sentientia_xapi'),
        get_string('lrs_col_success', 'local_sentientia_xapi'),
        get_string('lrs_col_tenant', 'local_sentientia_xapi'),
    ];

    foreach ($statements as $row) {
        $actor_data  = json_decode($row->actor, true);
        $actor_label = '';
        if (!empty($actor_data['name'])) {
            $actor_label = s(format_string($actor_data['name']));
        } elseif (!empty($actor_data['account']['name'])) {
            $actor_label = s('user#' . $actor_data['account']['name']);
        } elseif (!empty($actor_data['mbox'])) {
            $actor_label = s($actor_data['mbox']);
        }

        $verb_label  = s($row->verbdisplay ?? basename($row->verb));
        $object_label = s(substr($row->objectid ?? '', 0, 80));
        $score_label = $row->score_scaled !== null ? number_format((float) $row->score_scaled * 100, 0) . '%' : '–';
        $success_label = $row->success !== null ? ($row->success ? '✓' : '✗') : '–';

        $table->data[] = [
            userdate($row->stored),
            $actor_label,
            $verb_label,
            $object_label,
            $score_label,
            $success_label,
            s((string) $row->costcenterid),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
