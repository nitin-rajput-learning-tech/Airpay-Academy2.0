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
    $flag_key    = required_param('flag_key', PARAM_ALPHANUMEXT . '.');
    $customer_id = required_param('customer_id', PARAM_INT);
    $tenant_id   = required_param('tenant_id', PARAM_INT);
    $new_value   = required_param('new_value', PARAM_INT);

    // Whitelist of flags this UI can touch — defence-in-depth.
    $allowed_flags = ['live.enabled', 'live.realtime.enabled',
                      'live.allow_anonymous', 'live.questiontype.multichoice',
                      'live.questiontype.openended', 'live.questiontype.wordcloud',
                      'live.questiontype.quiz', 'live.questiontype.rating',
                      'live.questiontype.scale'];
    if (!in_array($flag_key, $allowed_flags, true)) {
        throw new \moodle_exception('invalidflag', 'local_sentientia_live');
    }

    // Upsert the override row.
    $existing = $DB->get_record('local_airpay_feature_flags', [
        'flag_key'    => $flag_key,
        'customer_id' => $customer_id,
        'tenant_id'   => $tenant_id,
    ]);

    $now = time();
    if ($existing) {
        $existing->is_enabled   = $new_value;
        $existing->modified_by  = $USER->id;
        $existing->timemodified = $now;
        $DB->update_record('local_airpay_feature_flags', $existing);
    } else {
        $DB->insert_record('local_airpay_feature_flags', (object) [
            'flag_key'     => $flag_key,
            'customer_id'  => $customer_id,
            'tenant_id'    => $tenant_id,
            'is_enabled'   => $new_value,
            'modified_by'  => $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    // Invalidate feature_flags cache so the change is visible immediately.
    if (class_exists('\\local_airpay_core\\feature_flags')) {
        \local_airpay_core\feature_flags::invalidate_cache();
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
       FROM {local_airpay_feature_flags}
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
