<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English strings for enrol_sentientiasub (ADR-023).
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia subscription';
$string['pluginname_desc'] = 'Recurring-subscription enrolment. A subscriber keeps access while their Airpay mandate is active; access is suspended on a failed charge and revoked on cancellation. Disabled by default — enable the "sentientia.subscriptions.enabled" feature flag (per tenant) to use it.';

// Capabilities.
$string['sentientiasub:config'] = 'Configure Sentientia subscription enrolment instances';
$string['sentientiasub:manage'] = 'Manage subscribed users';
$string['sentientiasub:unenrol'] = 'Unenrol subscribed users from the course';
$string['sentientiasub:unenrolself'] = 'Cancel own subscription enrolment';

// Settings.
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Role assigned to a subscriber while their subscription is active (scope = single course).';

// Subscription statuses.
$string['status_pending'] = 'Pending';
$string['status_active'] = 'Active';
$string['status_suspended'] = 'Suspended';
$string['status_cancelled'] = 'Cancelled';

// Privacy.
$string['privacy:metadata:enrol_sentientiasub_subscription'] = 'Recurring-subscription records linking a user to an enrol instance.';
$string['privacy:metadata:enrol_sentientiasub_subscription:userid'] = 'The user who holds the subscription.';
$string['privacy:metadata:enrol_sentientiasub_subscription:status'] = 'The subscription status (pending, active, suspended, cancelled).';
$string['privacy:metadata:enrol_sentientiasub_subscription:amount'] = 'The recurring charge amount.';
$string['privacy:metadata:enrol_sentientiasub_subscription:ap_mandate_id'] = 'The Airpay mandate identifier for the recurring authorisation.';
$string['privacy:metadata:enrol_sentientiasub_subscription:timecreated'] = 'When the subscription record was created.';
