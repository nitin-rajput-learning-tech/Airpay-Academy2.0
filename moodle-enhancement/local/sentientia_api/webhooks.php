<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Outbound webhooks admin page — subscriptions + delivery log (ADR-030 Wave A).
 *
 * @package local_sentientia_api
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_sentientia_api\webhooks\dispatcher;
use local_sentientia_api\webhooks\queue;
use local_sentientia_api\webhooks\subscription;

admin_externalpage_setup('local_sentientia_api_webhooks');
$context = context_system::instance();
require_capability('local/sentientia_api:webhooks_manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);
$pageurl = new moodle_url('/local/sentientia_api/webhooks.php');

// ── Actions (sesskey-guarded) ────────────────────────────────────────────
if ($action !== '' && $id > 0) {
    require_sesskey();
    switch ($action) {
        case 'enable':
            subscription::set_enabled($id, true);
            redirect($pageurl, get_string('webhook_toggled', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
        case 'disable':
            subscription::set_enabled($id, false);
            redirect($pageurl, get_string('webhook_toggled', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
        case 'delete':
            subscription::delete($id);
            redirect($pageurl, get_string('webhook_deleted', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
        case 'rotate':
            $secret = subscription::rotate_secret($id);
            $sub = subscription::get($id);
            redirect($pageurl, get_string('webhook_secret_shown', 'local_sentientia_api', format_string($sub->name)) . ' ' . s($secret),
                null, \core\output\notification::NOTIFY_WARNING);
        case 'retry':
            queue::retry($id);
            redirect($pageurl, get_string('webhook_retried', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ── Add form ─────────────────────────────────────────────────────────────
$form = new \local_sentientia_api\form\subscription_form($pageurl);
if ($data = $form->get_data()) {
    $newid = subscription::create((object) [
        'name'         => $data->name,
        'url'          => $data->url,
        'events'       => \local_sentientia_api\form\subscription_form::selected_events($data),
        'costcenterid' => (int) $data->costcenterid,
        'customerid'   => dispatcher::customer_of((int) $data->costcenterid),
        'enabled'      => (int) $data->enabled,
    ]);
    $sub = subscription::get($newid);
    redirect($pageurl,
        get_string('webhook_created', 'local_sentientia_api') . ' ' .
        get_string('webhook_secret_shown', 'local_sentientia_api', format_string($sub->name)) . ' ' . s($sub->secret),
        null, \core\output\notification::NOTIFY_WARNING);
}

// ── Render ───────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('webhooks_title', 'local_sentientia_api'));
echo html_writer::tag('p', get_string('webhooks_intro', 'local_sentientia_api'));

if (!dispatcher::enabled_for(0)) {
    echo $OUTPUT->notification(get_string('webhook_flag_off_notice', 'local_sentientia_api'), 'info');
}

$counts = queue::counts();
echo html_writer::tag('p', get_string('webhook_counts', 'local_sentientia_api', (object) $counts), ['class' => 'text-muted']);

// Subscriptions table.
echo $OUTPUT->heading(get_string('webhooks_subscriptions', 'local_sentientia_api'), 3);
$subs = subscription::list_all();
if (!$subs) {
    echo html_writer::tag('p', get_string('webhooks_none', 'local_sentientia_api'));
} else {
    $t = new html_table();
    $t->head = [
        get_string('webhook_name', 'local_sentientia_api'),
        get_string('webhook_url', 'local_sentientia_api'),
        get_string('webhook_events', 'local_sentientia_api'),
        get_string('webhook_tenant', 'local_sentientia_api'),
        get_string('webhook_enabled', 'local_sentientia_api'),
        get_string('webhook_lastsuccess', 'local_sentientia_api'),
        get_string('webhook_lastfailure', 'local_sentientia_api'),
        get_string('actions'),
    ];
    foreach ($subs as $s) {
        $toggle = $s->enabled ? 'disable' : 'enable';
        $actions = [
            html_writer::link(new moodle_url($pageurl, ['action' => $toggle, 'id' => $s->id, 'sesskey' => sesskey()]),
                get_string('webhook_action_' . $toggle, 'local_sentientia_api')),
            html_writer::link(new moodle_url($pageurl, ['action' => 'rotate', 'id' => $s->id, 'sesskey' => sesskey()]),
                get_string('webhook_action_rotate', 'local_sentientia_api')),
            html_writer::link(new moodle_url($pageurl, ['action' => 'delete', 'id' => $s->id, 'sesskey' => sesskey()]),
                get_string('webhook_action_delete', 'local_sentientia_api'),
                ['onclick' => "return confirm('" . addslashes_js(get_string('webhook_confirm_delete', 'local_sentientia_api')) . "');"]),
        ];
        $t->data[] = [
            format_string($s->name),
            s($s->url),
            s($s->events),
            $s->costcenterid ? (int) $s->costcenterid : get_string('webhook_all_tenants', 'local_sentientia_api'),
            $s->enabled ? get_string('yes') : get_string('no'),
            $s->lastsuccess ? userdate($s->lastsuccess) : get_string('webhook_never', 'local_sentientia_api'),
            $s->lastfailure ? userdate($s->lastfailure) : get_string('webhook_never', 'local_sentientia_api'),
            implode(' | ', $actions),
        ];
    }
    echo html_writer::table($t);
}

// Add form.
echo $OUTPUT->heading(get_string('webhook_add', 'local_sentientia_api'), 3);
$form->display();

// Recent deliveries.
echo $OUTPUT->heading(get_string('webhooks_deliveries', 'local_sentientia_api'), 3);
$rows = queue::recent(50);
if (!$rows) {
    echo html_writer::tag('p', get_string('webhooks_nodeliveries', 'local_sentientia_api'));
} else {
    $t = new html_table();
    $t->head = [
        'ID',
        get_string('webhook_event', 'local_sentientia_api'),
        get_string('webhook_status', 'local_sentientia_api'),
        get_string('webhook_attempts', 'local_sentientia_api'),
        get_string('webhook_httpstatus', 'local_sentientia_api'),
        get_string('webhook_nextattempt', 'local_sentientia_api'),
        get_string('webhook_lasterror', 'local_sentientia_api'),
        get_string('actions'),
    ];
    foreach ($rows as $r) {
        $retry = '';
        if ($r->status === queue::STATUS_DEAD || $r->status === queue::STATUS_FAILED) {
            $retry = html_writer::link(new moodle_url($pageurl, ['action' => 'retry', 'id' => $r->id, 'sesskey' => sesskey()]),
                get_string('webhook_action_retry', 'local_sentientia_api'));
        }
        $t->data[] = [
            (int) $r->id,
            s($r->eventkey),
            s($r->status),
            (int) $r->attempts,
            (int) $r->httpstatus,
            $r->status === queue::STATUS_SENT || $r->status === queue::STATUS_DEAD ? '-' : userdate($r->nextattempt),
            s((string) $r->lasterror),
            $retry,
        ];
    }
    echo html_writer::table($t);
}

echo $OUTPUT->footer();
