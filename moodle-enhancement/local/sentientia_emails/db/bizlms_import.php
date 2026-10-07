<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * ADR-032: the BizLMS importers this plugin owns. Discovered by
 * \local_sentientia_platform\bizlms\registry, the way db/feature_flags.php files are.
 *
 * notifications: local_emaillogs and local_email_logs -> local_sentientia_email_log.
 *
 * @package local_sentientia_emails
 */

defined('MOODLE_INTERNAL') || die();

$imports = [
    'notifications' => \local_sentientia_emails\bizlms\importer::class,
];
