<?php
/**
 * Manager Nudge — send learning reminders to direct reports.
 * Accessible from compliance report, team dashboard, or direct URL.
 *
 * Usage: /local/sentientia_notifications/nudge.php?userid=123&type=compliance
 *
 * @package    local_sentientia_notifications
 * @copyright  2026 Airpay Payment Services
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $CFG, $OUTPUT, $PAGE;

$targetid = required_param('userid', PARAM_INT);
$type = optional_param('type', 'general', PARAM_ALPHA); // general, compliance, course, streak
$courseid = optional_param('courseid', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/sentientia_notifications/nudge.php', ['userid' => $targetid, 'type' => $type]);

// Permission: must be manager of this user OR admin.
$ismanager = false;
if (is_siteadmin()) {
    $ismanager = true;
} elseif (has_capability('local/courses:manage', $context)) {
    $ismanager = true;
} else {
    $ismanager = $DB->record_exists_select('user',
        'id = :uid AND open_supervisorid = :mgr AND deleted = 0',
        ['uid' => $targetid, 'mgr' => $USER->id]);
}

if (!$ismanager) {
    throw new moodle_exception('nopermission');
}

$target = $DB->get_record('user', ['id' => $targetid], 'id, firstname, lastname, email', MUST_EXIST);

// Handle form submission.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'send' && confirm_sesskey()) {
    $message_text = optional_param('message', '', PARAM_TEXT);
    if (empty($message_text)) {
        $message_text = get_default_nudge_message($type, $target, $USER);
    }

    // Send via Moodle messaging API.
    $message = new \core\message\message();
    $message->component = 'local_sentientia_notifications';
    $message->name = 'smart_alert';
    $message->userfrom = $USER;
    $message->userto = $target;
    $message->subject = 'Learning Reminder from ' . format_string($USER->firstname);
    $message->fullmessage = $message_text;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = '<p>' . s($message_text) . '</p>';
    $message->smallmessage = $message_text;
    $message->notification = 1;

    $sent = message_send($message);

    // Log the nudge.
    $DB->insert_record('local_sentientia_notif_log', (object)[
        'ruleid'      => 0, // Manual nudge, no rule.
        'userid'      => $targetid,
        'courseid'    => $courseid,
        'channel'     => 'popup',
        'status'      => $sent ? 'sent' : 'failed',
        'subject'     => $message->subject,
        'timecreated' => time(),
    ]);

    redirect(new moodle_url('/local/sentientia_notifications/nudge.php',
        ['userid' => $targetid, 'type' => $type, 'sent' => 1]),
        'Nudge sent to ' . format_string($target->firstname) . '!', null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$sent = optional_param('sent', 0, PARAM_INT);

// Build default messages by type.
$default_messages = [
    'general'    => "Hi " . format_string($target->firstname) . ", just a friendly reminder to keep up with your learning this week. Your progress matters!",
    'compliance' => "Hi " . format_string($target->firstname) . ", you have mandatory compliance courses that need attention. Please complete them before the deadline.",
    'course'     => "Hi " . format_string($target->firstname) . ", I noticed you haven't completed your enrolled course yet. Let me know if you need any support!",
    'streak'     => "Hi " . format_string($target->firstname) . ", your learning streak is at risk! Log in today and keep your momentum going.",
];

$PAGE->set_title('Send Learning Nudge');
$PAGE->set_heading('Send Learning Nudge');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<div class="ap-nudge" style="max-width:600px; margin:0 auto;">
    <div class="ap-nudge__header" style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px; padding:24px; margin-bottom:16px;">
        <h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:var(--ap-text,#1a1a2e);">
            <i class="fa fa-bell" style="color:var(--ap-primary,#0066A7);"></i> Send Learning Nudge
        </h3>
        <p style="margin:0; font-size:14px; color:var(--ap-text-secondary,#607286);">
            Send a friendly learning reminder to <strong><?php echo format_string($target->firstname . ' ' . $target->lastname); ?></strong>
            (<?php echo s($target->email); ?>)
        </p>
    </div>

    <?php if ($sent): ?>
    <div class="alert alert-success" style="border-radius:10px;">
        <i class="fa fa-check-circle"></i> Nudge sent successfully!
    </div>
    <?php endif; ?>

    <form action="<?php echo (new moodle_url('/local/sentientia_notifications/nudge.php'))->out(false); ?>" method="post">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="userid" value="<?php echo $targetid; ?>">
        <input type="hidden" name="type" value="<?php echo s($type); ?>">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
        <input type="hidden" name="action" value="send">

        <div style="background:var(--ap-surface,#fff); border:1px solid var(--ap-border,#e3eaf3); border-radius:12px; padding:24px;">
            <label style="display:block; font-size:13px; font-weight:600; color:var(--ap-text-secondary,#607286); margin-bottom:8px;">
                Quick Message Templates
            </label>
            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px;">
                <?php foreach ($default_messages as $mtype => $msg): ?>
                <button type="button" onclick="document.getElementById('nudge-msg').value=this.dataset.msg;"
                        data-msg="<?php echo s($msg); ?>"
                        style="padding:6px 12px; border:1px solid var(--ap-border,#e3eaf3); border-radius:8px; font-size:12px;
                               background:<?php echo ($mtype === $type) ? 'var(--ap-primary-light,#e8f2f9)' : 'var(--ap-surface,#fff)'; ?>;
                               color:var(--ap-text,#1a1a2e); cursor:pointer; font-family:inherit;">
                    <?php echo ucfirst($mtype); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <label for="nudge-msg" style="display:block; font-size:13px; font-weight:600; color:var(--ap-text-secondary,#607286); margin-bottom:6px;">
                Message
            </label>
            <textarea id="nudge-msg" name="message" rows="4"
                      style="width:100%; padding:12px; border:1px solid var(--ap-border,#e3eaf3); border-radius:10px;
                             font-size:14px; font-family:inherit; resize:vertical;"
            ><?php echo s($default_messages[$type] ?? $default_messages['general']); ?></textarea>

            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                <a href="javascript:history.back();" style="padding:10px 20px; border-radius:10px; font-size:14px;
                   font-weight:600; color:var(--ap-text-secondary); text-decoration:none; background:var(--ap-bg,#F2F4FB);">
                    Cancel
                </a>
                <button type="submit" style="padding:10px 24px; border-radius:10px; font-size:14px; font-weight:600;
                        background:linear-gradient(135deg,#0066a7,#0d5da1); color:#fff; border:none; cursor:pointer;">
                    <i class="fa fa-paper-plane"></i> Send Nudge
                </button>
            </div>
        </div>
    </form>
</div>

<?php

echo $OUTPUT->footer();

/**
 * Get default nudge message by type.
 */
function get_default_nudge_message(string $type, \stdClass $target, \stdClass $sender): string {
    $name = format_string($target->firstname);
    switch ($type) {
        case 'compliance':
            return "Hi {$name}, you have mandatory compliance courses that need attention. Please complete them before the deadline. — " . format_string($sender->firstname);
        case 'course':
            return "Hi {$name}, I noticed you haven't completed your enrolled course yet. Let me know if you need any support! — " . format_string($sender->firstname);
        case 'streak':
            return "Hi {$name}, your learning streak is at risk! Log in today and keep your momentum going. — " . format_string($sender->firstname);
        default:
            return "Hi {$name}, just a friendly reminder to keep up with your learning this week. Your progress matters! — " . format_string($sender->firstname);
    }
}
