<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SCIM provisioning clients admin page (ADR-030 Wave B).
 *
 * @package local_sentientia_api
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_sentientia_api\scim\client;
use local_sentientia_api\scim\handler;
use local_sentientia_api\scim\mapper;
use local_sentientia_api\webhooks\dispatcher;

admin_externalpage_setup('local_sentientia_api_scim');
$context = context_system::instance();
require_capability('local/sentientia_api:scim_manage', $context);

$action  = optional_param('action', '', PARAM_ALPHA);
$id      = optional_param('id', 0, PARAM_INT);
$pageurl = new moodle_url('/local/sentientia_api/scim.php');

if ($action !== '' && $id > 0) {
    require_sesskey();
    switch ($action) {
        case 'enable':
            client::set_enabled($id, true);
            redirect($pageurl, get_string('scim_client_toggled', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
        case 'disable':
            client::set_enabled($id, false);
            redirect($pageurl, get_string('scim_client_toggled', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
        case 'delete':
            client::delete($id);
            redirect($pageurl, get_string('scim_client_deleted', 'local_sentientia_api'), null, \core\output\notification::NOTIFY_SUCCESS);
        case 'rotate':
            $token = client::rotate_token($id);
            $c = client::get($id);
            redirect($pageurl, get_string('scim_client_token_shown', 'local_sentientia_api', format_string($c->name)) . ' ' . s($token),
                null, \core\output\notification::NOTIFY_WARNING);
    }
}

$form = new \local_sentientia_api\form\scim_client_form($pageurl);
if ($data = $form->get_data()) {
    $made = client::create((object) [
        'name'         => $data->name,
        'costcenterid' => (int) $data->costcenterid,
        'customerid'   => dispatcher::customer_of((int) $data->costcenterid),
        'auth'         => $data->auth,
        'ratelimit'    => (int) $data->ratelimit,
        'enabled'      => (int) $data->enabled,
    ]);
    $c = client::get($made['id']);
    redirect($pageurl,
        get_string('scim_client_created', 'local_sentientia_api') . ' ' .
        get_string('scim_client_token_shown', 'local_sentientia_api', format_string($c->name)) . ' ' . s($made['token']),
        null, \core\output\notification::NOTIFY_WARNING);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('scim_title', 'local_sentientia_api'));
echo html_writer::tag('p', get_string('scim_intro', 'local_sentientia_api'));

$endpoint = (new moodle_url('/local/sentientia_api/scim/v2.php'))->out(false);
echo html_writer::tag('p', html_writer::tag('strong', get_string('scim_endpoint_url', 'local_sentientia_api') . ': ')
    . html_writer::tag('code', s($endpoint)));

$flagson = class_exists('\local_sentientia_platform\feature_flags')
    && \local_sentientia_platform\feature_flags::is_enabled_for(handler::FLAG_MASTER, 0, 0)
    && \local_sentientia_platform\feature_flags::is_enabled_for(handler::FLAG_SCIM, 0, 0);
if (!$flagson) {
    echo $OUTPUT->notification(get_string('scim_flag_off_notice', 'local_sentientia_api'), 'info');
}

echo $OUTPUT->heading(get_string('scim_clients', 'local_sentientia_api'), 3);
$clients = client::list_all();
if (!$clients) {
    echo html_writer::tag('p', get_string('scim_none', 'local_sentientia_api'));
} else {
    $t = new html_table();
    $t->head = [
        get_string('scim_client_name', 'local_sentientia_api'),
        get_string('scim_client_tenant', 'local_sentientia_api'),
        get_string('scim_client_auth', 'local_sentientia_api'),
        get_string('scim_client_enabled', 'local_sentientia_api'),
        get_string('scim_client_lastseen', 'local_sentientia_api'),
        get_string('scim_mappings', 'local_sentientia_api'),
        get_string('actions'),
    ];
    foreach ($clients as $c) {
        $toggle = $c->enabled ? 'disable' : 'enable';
        $actions = [
            html_writer::link(new moodle_url($pageurl, ['action' => $toggle, 'id' => $c->id, 'sesskey' => sesskey()]),
                get_string('scim_action_' . $toggle, 'local_sentientia_api')),
            html_writer::link(new moodle_url($pageurl, ['action' => 'rotate', 'id' => $c->id, 'sesskey' => sesskey()]),
                get_string('scim_action_rotate', 'local_sentientia_api')),
            html_writer::link(new moodle_url($pageurl, ['action' => 'delete', 'id' => $c->id, 'sesskey' => sesskey()]),
                get_string('scim_action_delete', 'local_sentientia_api'),
                ['onclick' => "return confirm('" . addslashes_js(get_string('scim_confirm_delete', 'local_sentientia_api')) . "');"]),
        ];
        $t->data[] = [
            format_string($c->name),
            $c->costcenterid ? (int) $c->costcenterid : get_string('webhook_all_tenants', 'local_sentientia_api'),
            s($c->auth),
            $c->enabled ? get_string('yes') : get_string('no'),
            $c->lastseen ? userdate($c->lastseen) : get_string('webhook_never', 'local_sentientia_api'),
            mapper::count_for_client((int) $c->id),
            implode(' | ', $actions),
        ];
    }
    echo html_writer::table($t);
}

echo $OUTPUT->heading(get_string('scim_client_add', 'local_sentientia_api'), 3);
$form->display();
echo $OUTPUT->footer();
