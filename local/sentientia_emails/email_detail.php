<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * One e-mail imported from BizLMS, with its body (ADR-032, mapping doc section 11, code fix 3).
 *
 * URL: /local/sentientia_emails/email_detail.php?id=<local_sentientia_email_log.id>
 *
 * Behind two default-OFF flags (sentientia.emails.imported_history.enabled and
 * sentientia.emails.imported_body_detail.enabled). With either OFF, for a row that is not an imported one, and for
 * a row of another tenant, the page answers exactly as it would for a row that does not exist. Only holders of
 * local/sentientia_emails:manage (or a site admin) get this far, and the tenant rule is the log's own: a scoped
 * caller reads their own tenant, a caller whose tenant does not resolve reads nothing.
 *
 * BizLMS echoed this body raw. It is shown here cleaned (format_text, HTML, no filters), so markup in an old
 * message cannot run in an administrator's session. A message that carried account credentials has no body and
 * no subject to show: the importer withheld both.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

if (!is_siteadmin() && !has_capability('local/sentientia_emails:manage', $context)) {
    throw new \moodle_exception('nopermissions', 'error', '', 'view an imported notification');
}

$id = required_param('id', PARAM_INT);

$row = \local_sentientia_emails\delivery_log::get_imported_detail($id);
if ($row === null) {
    throw new \moodle_exception('invalidrecord', 'error', '', 'local_sentientia_email_log');
}

$PAGE->set_url(new moodle_url('/local/sentientia_emails/email_detail.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('email_detail', 'local_sentientia_emails'));
$PAGE->set_heading(get_string('pluginname', 'local_sentientia_emails'));
$PAGE->set_pagelayout('standard');

$hasbody = $row->body_html !== null && trim((string) $row->body_html) !== '';
$body = '';
if ($hasbody) {
    // Cleaned, never raw: trusted is false and noclean is not set. No filters: this is an archived message.
    // First, nothing that would be fetched from outside: an old mail's remote image or tracking pixel must not call out
    // to a third party, and tell it when an administrator read the message (F-65).
    $source = \local_sentientia_emails\imported_history::without_external_resources((string) $row->body_html,
        '[' . get_string('email_detail_image_removed', 'local_sentientia_emails') . ']');
    $body = format_text($source, FORMAT_HTML, [
        'context' => $context,
        'trusted' => false,
        'noclean' => false,
        'filter' => false,
        'para' => false,
        'overflowdiv' => true,
    ]);
}

$sendername = trim(($row->sender_firstname ?? '') . ' ' . ($row->sender_lastname ?? ''));
$data = [
    'subject' => format_string($row->subject, true, ['escape' => false]),
    'recipient' => format_string(trim(($row->firstname ?? '') . ' ' . ($row->lastname ?? '')), true, ['escape' => false]),
    'recipient_email' => (string) ($row->email ?? ''),
    'legacy_type' => (string) ($row->legacy_type ?? ''),
    'status' => (string) $row->status,
    'created' => userdate((int) $row->timecreated, '%d %b %Y %I:%M %p'),
    'has_sent_on' => !empty($row->timesent),
    'sent_on' => !empty($row->timesent) ? userdate((int) $row->timesent, '%d %b %Y %I:%M %p') : '',
    'has_sender' => $sendername !== '',
    'sender' => format_string($sendername, true, ['escape' => false]),
    'has_error' => !empty($row->error_message),
    'error' => (string) ($row->error_message ?? ''),
    'has_body' => $hasbody,
    // Output of format_text() above: cleaned HTML, the one place this template uses triple braces.
    'body' => $body,
    'back_url' => (new moodle_url('/local/sentientia_emails/manage.php', [
        'tab' => 'logs',
        'tenant' => \local_sentientia_emails\tenant_scope::resolve(0),
    ]))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_emails/manage/email_detail', $data);
echo $OUTPUT->footer();
