<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * External trainers: invite, list, extend, reactivate, revoke (ADR-036 D9, plan C6, session 5).
 *
 * Behind sentientia.users.external_trainer.enabled (default OFF) and
 * local/sentientia_users:inviteexternaltrainer (the tenant administrator role; site admins). The list
 * and every action stay inside the caller's tenant (ADR-031). Actions are POST with sesskey; revoke asks
 * for confirmation first.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_sentientia_users\external_trainer\invite_mailer;
use local_sentientia_users\external_trainer\manager;

require_login();

$context = context_system::instance();
$pageurl = new moodle_url('/local/sentientia_users/external_trainers.php');
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('exttrainer_page_title', 'local_sentientia_users'));
$PAGE->set_heading(get_string('exttrainer_page_title', 'local_sentientia_users'));
$PAGE->set_secondary_navigation(false);

// Flag ON and the capability, or nothing (the exception names the reason).
manager::require_can_manage();

// ── Actions (POST + sesskey) ────────────────────────────────────────────────
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    $targetid = required_param('userid', PARAM_INT);
    switch ($action) {
        case 'extend':
            manager::extend($targetid);
            break;
        case 'reactivate':
            manager::reactivate($targetid);
            break;
        case 'revoke':
            manager::revoke($targetid);
            break;
        default:
            throw new moodle_exception('invalidparameter', 'debug');
    }
    redirect($pageurl, get_string('exttrainer_done_' . $action, 'local_sentientia_users'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Invite ──────────────────────────────────────────────────────────────────
$form = new \local_sentientia_users\form\external_trainer_invite_form($pageurl);
if ($data = $form->get_data()) {
    $newid = manager::invite($data, false);
    $sent = invite_mailer::send($newid);
    $name = fullname(core_user::get_user($newid));
    redirect($pageurl,
        get_string($sent ? 'exttrainer_invited' : 'exttrainer_invited_nomail', 'local_sentientia_users', $name),
        null, $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING);
}

// ── List ────────────────────────────────────────────────────────────────────
$rows = manager::list_for_caller();
$now = time();
$dateformat = get_string('strftimedate', 'langconfig');

$inviterids = array_values(array_unique(array_filter(array_map(fn($r) => (int) $r->invitedby, $rows))));
$inviters = $inviterids ? $DB->get_records_list('user', 'id', $inviterids) : [];

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-sm';
$table->head = [
    get_string('name'),
    get_string('exttrainer_field_organisation', 'local_sentientia_users'),
    get_string('status'),
    get_string('exttrainer_col_expires', 'local_sentientia_users'),
    get_string('exttrainer_col_invitedby', 'local_sentientia_users'),
    get_string('exttrainer_col_invited', 'local_sentientia_users'),
    get_string('actions'),
];
$table->data = [];
foreach ($rows as $row) {
    $userid = (int) $row->userid;
    $status = (string) $row->status;
    $statuslabel = get_string('exttrainer_status_' . $status, 'local_sentientia_users');
    $badge = $status === manager::STATUS_ACTIVE ? 'bg-success' : ($status === manager::STATUS_EXPIRED ? 'bg-warning text-dark' : 'bg-secondary');
    $statushtml = html_writer::span(s($statuslabel), 'badge ' . $badge);
    if ($status !== manager::STATUS_ACTIVE && (int) $row->expires_at > $now) {
        // Assigned to a later session after the account ended: an admin decides (ADR-036 open question 2).
        $statushtml .= html_writer::div(s(get_string('exttrainer_reassigned_hint', 'local_sentientia_users')),
            'small text-muted');
    }

    $buttons = [];
    $actionurl = fn(string $a) => new moodle_url($pageurl, ['action' => $a, 'userid' => $userid, 'sesskey' => sesskey()]);
    if ($status === manager::STATUS_ACTIVE) {
        $buttons[] = $OUTPUT->single_button($actionurl('extend'),
            get_string('exttrainer_action_extend', 'local_sentientia_users'), 'post');
        $revoke = new single_button($actionurl('revoke'),
            get_string('exttrainer_action_revoke', 'local_sentientia_users'), 'post');
        $revoke->add_confirm_action(get_string('exttrainer_revoke_confirm', 'local_sentientia_users'));
        $buttons[] = $OUTPUT->render($revoke);
    } else {
        $buttons[] = $OUTPUT->single_button($actionurl('reactivate'),
            get_string('exttrainer_action_reactivate', 'local_sentientia_users'), 'post');
    }

    $inviter = isset($inviters[(int) $row->invitedby]) && empty($inviters[(int) $row->invitedby]->deleted)
        ? fullname($inviters[(int) $row->invitedby]) : '—';

    $table->data[] = [
        s(fullname($row)),
        s((string) $row->organisation !== '' ? (string) $row->organisation : '—'),
        $statushtml,
        s(userdate((int) $row->expires_at, $dateformat)),
        s($inviter),
        s(userdate((int) $row->timecreated, $dateformat)),
        html_writer::div(implode('', array_map(fn($b) => html_writer::div($b, 'me-2 mb-1'), $buttons)),
            'd-flex flex-wrap'),
    ];
}

echo $OUTPUT->header();
echo html_writer::tag('p', s(get_string('exttrainer_page_intro', 'local_sentientia_users', manager::GRACE / DAYSECS)),
    ['class' => 'text-muted']);

echo $OUTPUT->heading(get_string('exttrainer_list_heading', 'local_sentientia_users'), 3);
if ($table->data) {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
} else {
    echo $OUTPUT->notification(get_string('exttrainer_list_empty', 'local_sentientia_users'),
        \core\output\notification::NOTIFY_INFO, false);
}

echo $OUTPUT->heading(get_string('exttrainer_invite_heading', 'local_sentientia_users'), 3);
echo html_writer::tag('p', s(get_string('exttrainer_invite_intro', 'local_sentientia_users')), ['class' => 'text-muted']);
$form->display();

echo $OUTPUT->footer();
