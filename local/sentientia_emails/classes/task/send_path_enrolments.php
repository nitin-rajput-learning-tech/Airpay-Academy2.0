<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled task: send the learning-path enrolment e-mail for the enrolments made since the last run (COMMS-N7).
 *
 * The learning-path plugin fires no event when it enrols a learner, so the BizLMS learningplan_enrol e-mail cannot be
 * driven by an observer. This task polls the enrolment table instead (parity_senders::send_pending_path_enrolments()).
 * The e-mail is behind the flag sentientia.emails.send_learning_path_enrolment.enabled, default OFF; while it is OFF the
 * task only moves its marker forward, so switching the flag ON later never e-mails an old enrolment.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails\task;

defined('MOODLE_INTERNAL') || die();

class send_path_enrolments extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_path_enrolments', 'local_sentientia_emails');
    }

    /**
     * @return void
     */
    public function execute(): void {
        $sent = \local_sentientia_emails\parity_senders::send_pending_path_enrolments();
        if ($sent > 0) {
            mtrace("Learning-path enrolment e-mails handed to the sender: {$sent}.");
        }
    }
}
