<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_emails.
 *
 * ADR-032 (2026-09-30): the BizLMS email history is imported into
 * local_sentientia_email_log by the notifications importer. The import has no
 * user-visible surface and never flips a flag. What an administrator SEES of the
 * imported rows is a reader change, and every reader change ships behind its own
 * flag, default OFF, so the notification log shows exactly what it showed before
 * the import until somebody decides otherwise. Turning them ON for a customer is
 * Nitin's call, after he has reviewed the visual evidence (decisions file key
 * framework.reader_flags_airpay_at_cutover).
 *
 * Resolution is performed by \local_sentientia_platform\feature_flags.
 *
 * @package local_sentientia_emails
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.emails.imported_history.enabled' => [
        'default'     => false,
        'description' => 'Shows the email history imported from BizLMS (ADR-032) in the
                          notification log on the Notification Management page. When OFF
                          the Logs tab, its export and the dashboard tiles count only
                          what Sentientia itself wrote, exactly as before the import.
                          When ON they also list the imported rows, marked as BizLMS
                          history, with the BizLMS notification type where there is no
                          template, a badge for every status (imported queue rows that
                          were never delivered show as not sent), and Sent from and
                          Sent on columns. The dashboard then hides the separate BizLMS
                          (Legacy) queue card so the same emails are not counted twice.
                          A scoped tenant admin still sees only their own tenant; rows
                          whose tenant could not be resolved are for cross-tenant
                          administrators only. Nothing imported is ever sent. Default OFF.',
    ],

    'sentientia.emails.imported_body_detail.enabled' => [
        'default'     => false,
        'description' => 'Adds a View link to imported BizLMS email rows in the notification
                          log that opens the message body. Needs
                          sentientia.emails.imported_history.enabled to be ON as well: with
                          either OFF there is no link and the page answers as if the row did
                          not exist. The body is shown cleaned (format_text, no filters), only
                          to holders of local/sentientia_emails:manage within their tenant,
                          and is empty for messages that carried account credentials, whose
                          subject and body were withheld at import. Default OFF.',
    ],

    // COMMS-N7 (2026-10-07): three e-mails BizLMS sends today and Sentientia had no sender for (decisions file key
    // gaps.notification_sender_parity = build_flagged_off). Each is its own flag, default OFF, and the import never
    // flips one. Turning them ON for Airpay at cutover is Nitin's call after he has seen them work on UAT. They
    // send through the same engine as every other rule: nothing leaves a server with $CFG->noemailever set (the
    // delivery log then shows the row as suppressed), the recipient's channel preferences apply, and none of them
    // carries a password.

    'sentientia.emails.send_course_enrolment.enabled' => [
        'default'     => false,
        'description' => 'Sends the learner an e-mail when they are enrolled in a course (the BizLMS
                          course_enrol e-mail), with the course, who enrolled them and a link. Skipped
                          for a hidden course, a suspended enrolment and a suspended or deleted user.
                          The flag is read for the learner\'s own tenant, so it can be ON for one
                          tenant only. A Notification rule of type course_enrolled can change the
                          channel or switch the e-mail off for a tenant. Default OFF.',
    ],

    'sentientia.emails.send_learning_path_enrolment.enabled' => [
        'default'     => false,
        'description' => 'Sends the learner an e-mail when they are enrolled in a learning path (the
                          BizLMS learningplan_enrol e-mail), with the path, its courses and its
                          closing date. The learning-path plugin raises no event, so a scheduled task
                          looks for new enrolments every five minutes; it only ever e-mails an
                          enrolment made after it started, never an old or an imported one. The flag
                          is read for the learner\'s own tenant. A Notification rule of type
                          learning_path_enrolled can change the channel or switch the e-mail off for a
                          tenant. Default OFF.',
    ],

    'sentientia.emails.send_manager_completion_copy.enabled' => [
        'default'     => false,
        'description' => 'When a learner completes a course, sends their supervisor a copy (the BizLMS
                          course_complete e-mail to a manager), naming the learner and the course. Only
                          to a live supervisor in the same tenant as the learner. The flag is read for
                          the supervisor\'s tenant. A Notification rule of type manager_course_completed
                          can change the channel or switch the e-mail off for a tenant. The learner\'s
                          own completion e-mail is unchanged. Default OFF.',
    ],

];
