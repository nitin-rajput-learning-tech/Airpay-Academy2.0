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

];
