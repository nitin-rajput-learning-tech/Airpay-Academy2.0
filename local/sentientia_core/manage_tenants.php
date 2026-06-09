<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia tenant registry — admin management UI (ADR-021 Wave 4).
 *
 * Lists customers + tenants, toggles tenant status, and onboards new
 * customers / tenants. Gated by local/sentientia_core:managetenants (site-admin
 * only in v1). The registry is only CONSULTED at runtime when
 * tenant_registry_legacy is OFF — managing rows here is safe while the flag is ON.
 *
 * @package   local_sentientia_core
 * @copyright 2026 Airpay Payment Services
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_core\form\customer_form;
use local_sentientia_core\form\tenant_form;
use local_sentientia_core\tenant_registry;

admin_externalpage_setup('local_sentientia_core_tenants');

$context = context_system::instance();
require_capability('local/sentientia_core:managetenants', $context);

$baseurl = new moodle_url('/local/sentientia_core/manage_tenants.php');
$action  = optional_param('action', '', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);

// ── Status toggle (GET + sesskey) ─────────────────────────────────────────
if (($action === 'suspendtenant' || $action === 'activatetenant') && confirm_sesskey()) {
    $tenant = $DB->get_record('local_sentientia_tenant', ['id' => $id], '*', MUST_EXIST);
    $tenant->status = ($action === 'suspendtenant') ? 'suspended' : 'active';
    $tenant->timemodified = time();
    $DB->update_record('local_sentientia_tenant', $tenant);
    redirect($baseurl, get_string('tenant_statuschanged', 'local_sentientia_core'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Customer options for the tenant form's owner dropdown.
$customers = $DB->get_records('local_sentientia_customer', null, 'name ASC');
$customeropts = [];
foreach ($customers as $c) {
    $customeropts[$c->id] = format_string($c->name) . ' (' . s($c->shortname) . ')';
}

$customerform = new customer_form($baseurl->out(false));
$tenantform   = new tenant_form($baseurl->out(false), ['customers' => $customeropts]);

// ── Save handlers ─────────────────────────────────────────────────────────
if ($data = $customerform->get_data()) {
    $now = time();
    $DB->insert_record('local_sentientia_customer', (object) [
        'name'         => $data->name,
        'shortname'    => $data->shortname,
        'status'       => $data->status,
        'timecreated'  => $now,
        'timemodified' => $now,
    ]);
    redirect($baseurl, get_string('customer_saved', 'local_sentientia_core'),
        null, \core\output\notification::NOTIFY_SUCCESS);
} else if ($data = $tenantform->get_data()) {
    $now = time();
    $idnumber = trim((string) $data->idnumber);
    $DB->insert_record('local_sentientia_tenant', (object) [
        'rootid'       => (int) $data->rootid,
        'customerid'   => (int) $data->customerid,
        'name'         => $data->name,
        'idnumber'     => ($idnumber === '') ? null : $idnumber,
        'status'       => $data->status,
        'timecreated'  => $now,
        'timemodified' => $now,
    ]);
    redirect($baseurl, get_string('tenant_saved', 'local_sentientia_core'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Render ──────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetenants', 'local_sentientia_core'));

// Flag-state banner — make the dormant/active state unmistakable.
$legacyon = tenant_registry::use_legacy_registry();
echo $OUTPUT->notification(
    get_string($legacyon ? 'registry_flag_legacy_on' : 'registry_flag_legacy_off',
        'local_sentientia_core'),
    $legacyon ? \core\output\notification::NOTIFY_INFO
              : \core\output\notification::NOTIFY_WARNING
);

// Customers table.
echo $OUTPUT->heading(get_string('customers', 'local_sentientia_core'), 3);
if ($customers) {
    $ct = new html_table();
    $ct->head = [
        get_string('field_customername', 'local_sentientia_core'),
        get_string('field_shortname', 'local_sentientia_core'),
        get_string('field_status', 'local_sentientia_core'),
        get_string('tenantcount', 'local_sentientia_core'),
    ];
    foreach ($customers as $c) {
        $count = $DB->count_records('local_sentientia_tenant', ['customerid' => $c->id]);
        $ct->data[] = [
            format_string($c->name),
            s($c->shortname),
            get_string('status_' . $c->status, 'local_sentientia_core'),
            $count,
        ];
    }
    echo html_writer::table($ct);
} else {
    echo $OUTPUT->notification(get_string('nocustomers', 'local_sentientia_core'),
        \core\output\notification::NOTIFY_INFO);
}

// Tenants table.
echo $OUTPUT->heading(get_string('tenants', 'local_sentientia_core'), 3);
$tenants = $DB->get_records('local_sentientia_tenant', null, 'rootid ASC');
if ($tenants) {
    $tt = new html_table();
    $tt->head = [
        get_string('field_rootid', 'local_sentientia_core'),
        get_string('field_tenantname', 'local_sentientia_core'),
        get_string('field_customer', 'local_sentientia_core'),
        get_string('field_idnumber', 'local_sentientia_core'),
        get_string('field_status', 'local_sentientia_core'),
        get_string('actions', 'local_sentientia_core'),
    ];
    foreach ($tenants as $t) {
        $ownername = isset($customers[$t->customerid])
            ? format_string($customers[$t->customerid]->name)
            : get_string('customer_missing', 'local_sentientia_core');

        if ($t->status === 'active') {
            $toggle = html_writer::link(
                new moodle_url($baseurl, ['action' => 'suspendtenant', 'id' => $t->id,
                    'sesskey' => sesskey()]),
                get_string('suspend', 'local_sentientia_core'));
        } else {
            $toggle = html_writer::link(
                new moodle_url($baseurl, ['action' => 'activatetenant', 'id' => $t->id,
                    'sesskey' => sesskey()]),
                get_string('activate', 'local_sentientia_core'));
        }

        $tt->data[] = [
            (int) $t->rootid,
            format_string($t->name),
            $ownername,
            $t->idnumber !== null ? s($t->idnumber) : '—',
            get_string('status_' . $t->status, 'local_sentientia_core'),
            $toggle,
        ];
    }
    echo html_writer::table($tt);
} else {
    echo $OUTPUT->notification(get_string('notenants', 'local_sentientia_core'),
        \core\output\notification::NOTIFY_INFO);
}

// Add forms.
echo $OUTPUT->heading(get_string('addcustomer', 'local_sentientia_core'), 3);
$customerform->display();

echo $OUTPUT->heading(get_string('addtenant', 'local_sentientia_core'), 3);
if ($customeropts) {
    $tenantform->display();
} else {
    echo $OUTPUT->notification(get_string('addcustomer_first', 'local_sentientia_core'),
        \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();
