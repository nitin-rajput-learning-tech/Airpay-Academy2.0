<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-tenant Sentientia Live kill switches.
 *
 * B18 / F-089 stabilization fix (2026-05-28). The audit surfaced that
 * `live.enabled`, `live.realtime.enabled`, `live.allow_anonymous` etc.
 * were all set globally (customer_id=0, tenant_id=0). With the schema
 * supporting per-tenant overrides since ADR-002, the kill switch was
 * theoretically available — just had no admin UI to flip it.
 *
 * This page provides that UI:
 *   - Lists every (customer, tenant) pair that has Live flags set
 *   - Shows the current is_enabled for each flag per tenant
 *   - Lets a siteadmin toggle individual tenant overrides
 *
 * Kept intentionally minimal: a HTML table + per-row form posts. No
 * AMD/AJAX — siteadmin operations are rare and a full reload after a
 * flip is acceptable.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_sentientia_live_tenant_switches',
    '', null, '', ['pagelayout' => 'admin']);

global $DB, $OUTPUT, $PAGE;
$PAGE->set_url('/local/sentientia_live/admin/tenant_switches.php');
$PAGE->set_title(get_string('tenant_switches_title', 'local_sentientia_live'));
$PAGE->set_heading(get_string('tenant_switches_title', 'local_sentientia_live'));

// ── Handle a flip submission ────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'flip' && confirm_sesskey()) {
    // 2026-09-25: this read PARAM_ALPHANUMEXT . '.', which is not a param
    // type, so every flip died here. PARAM_TEXT is safe: the key is only
    // ever compared against tenant_switches::FLAGS, the real validation.
    $flag_key    = required_param('flag_key', PARAM_TEXT);
    $customer_id = required_param('customer_id', PARAM_INT);
    $tenant_id   = required_param('tenant_id', PARAM_INT);
    $new_value   = required_param('new_value', PARAM_INT);

    // The whitelist, the write, its audit row and the cache invalidation
    // all live in tenant_switches::flip() -> feature_flags::set().
    try {
        \local_sentientia_live\tenant_switches::flip($flag_key, $customer_id, $tenant_id,
            (bool) $new_value);
    } catch (\moodle_exception $e) {
        redirect($PAGE->url, s($e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }

    redirect($PAGE->url,
        get_string('tenant_switches_flipped', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tenant_switches_title',
    'local_sentientia_live'));

echo html_writer::tag('p',
    get_string('tenant_switches_intro', 'local_sentientia_live'),
    ['class' => 'lead']);

// ── Snapshot of current flag state ──────────────────────────────────
$live_flags = $DB->get_records_sql(
    "SELECT id, flag_key, customer_id, tenant_id, is_enabled, timemodified
       FROM {local_sentientia_feature_flags}
      WHERE " . $DB->sql_like('flag_key', ':pat') . "
   ORDER BY flag_key, customer_id, tenant_id",
    ['pat' => 'live.%']);

if (empty($live_flags)) {
    echo $OUTPUT->notification(
        get_string('tenant_switches_empty', 'local_sentientia_live'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    return;
}

// ── Render table ────────────────────────────────────────────────────
$table = new html_table();
$table->head = [
    get_string('th_flag_key',    'local_sentientia_live'),
    get_string('th_customer_id', 'local_sentientia_live'),
    get_string('th_tenant_id',   'local_sentientia_live'),
    get_string('th_enabled',     'local_sentientia_live'),
    get_string('th_modified',    'local_sentientia_live'),
    get_string('th_action',      'local_sentientia_live'),
];
$table->attributes['class'] = 'table table-striped table-hover ap-tenant-switches';

foreach ($live_flags as $row) {
    $scope = ($row->customer_id == 0 && $row->tenant_id == 0)
        ? '<em>(global)</em>'
        : sprintf('c=%d, t=%d', $row->customer_id, $row->tenant_id);
    $enabled_label = $row->is_enabled
        ? '<span class="badge bg-success">ON</span>'
        : '<span class="badge bg-secondary">OFF</span>';

    // Toggle form
    $new_val = $row->is_enabled ? 0 : 1;
    $action_label = $row->is_enabled
        ? get_string('action_disable', 'local_sentientia_live')
        : get_string('action_enable',  'local_sentientia_live');
    $action_class = $row->is_enabled ? 'btn-outline-danger' : 'btn-outline-success';

    $form = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'class'  => 'd-inline',
    ]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden',
        'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden',
        'name' => 'action', 'value' => 'flip']);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden',
        'name' => 'flag_key', 'value' => $row->flag_key]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden',
        'name' => 'customer_id', 'value' => $row->customer_id]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden',
        'name' => 'tenant_id', 'value' => $row->tenant_id]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden',
        'name' => 'new_value', 'value' => $new_val]);
    $form .= html_writer::tag('button', $action_label, [
        'type' => 'submit',
        'class' => 'btn btn-sm ' . $action_class,
    ]);
    $form .= html_writer::end_tag('form');

    $table->data[] = [
        html_writer::tag('code', s($row->flag_key)),
        $row->customer_id,
        $row->tenant_id,
        $enabled_label,
        userdate($row->timemodified, get_string('strftimedatetimeshort', 'langconfig')),
        $form,
    ];
}

echo html_writer::table($table);

// Footer hint about adding a new per-tenant override
echo html_writer::tag('p', '', ['class' => 'mt-4']);
echo $OUTPUT->notification(
    get_string('tenant_switches_add_hint', 'local_sentientia_live'),
    \core\output\notification::NOTIFY_INFO);

echo $OUTPUT->footer();
