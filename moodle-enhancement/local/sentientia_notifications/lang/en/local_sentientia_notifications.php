<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Smart Notifications';
$string['privacy:metadata'] = 'The notifications plugin stores notification preferences and delivery logs.';

// Capabilities.
$string['sentientia_notifications:view'] = 'View notification rules';
$string['sentientia_notifications:manage'] = 'Manage notification rules';

// CRUD strings.
$string['addrule'] = 'Create Notification Rule';
$string['editrule'] = 'Edit Notification Rule';
$string['deleterule'] = 'Delete Rule';
$string['enablerule'] = 'Enable Rule';
$string['disablerule'] = 'Disable Rule';

// Form sections.
$string['heading_basic'] = 'Rule Identity';
$string['heading_trigger'] = 'Trigger Conditions';
$string['heading_delivery'] = 'Delivery';

// Form labels.
$string['rule_name'] = 'Rule name';
$string['rule_type'] = 'Rule type';
$string['rule_type_help'] = 'What event triggers this notification. Pick the event type that matches when you want to alert users.';
$string['trigger_days'] = 'Trigger window (days)';
$string['trigger_days_help'] = 'How many days before/after the event to send. Example: 3 days before deadline = enter 3 with rule type "Deadline approaching".';
$string['audience'] = 'Audience';
$string['channel'] = 'Delivery channel';
$string['template'] = 'Message template';
$string['template_help'] = 'The notification message. You can use placeholders like {{firstname}}, {{coursename}}, {{days}}, {{deadline}}.';
$string['enabled'] = 'Rule is active';

// Errors.
$string['missingrequiredfields'] = 'Please fill in name and rule type.';
$string['invalidruletype'] = 'Invalid rule type.';
$string['invalidchannel'] = 'Invalid delivery channel.';
$string['invalidaudience'] = 'Invalid audience.';
$string['trigger_days_invalid'] = 'Trigger window must be 0 or more days.';
$string['confirmdelete'] = 'Delete rule "{$a}"? Existing notification logs will be preserved for audit, but the rule will stop firing for new events.';
$string['confirmdisable'] = 'Disable rule "{$a}"? It will stop firing immediately but can be re-enabled later.';
$string['confirmenable'] = 'Enable rule "{$a}"? It will start processing matching events on the next cron run.';

// Success.
$string['rulecreated'] = 'Notification rule created.';
$string['ruleupdated'] = 'Notification rule updated.';
$string['ruledeleted'] = 'Rule deleted.';
$string['rulestatuschanged'] = 'Rule status updated.';

$string['taskprocessrules'] = 'Process Airpay notification rules';
$string['messageprovider:smart_alert'] = 'Airpay learning alerts';
$string['notifications'] = 'Notifications';
$string['markallread'] = 'Mark all as read';
$string['nonotifications'] = 'You\'re all caught up!';
$string['urgent'] = 'Urgent';
$string['learning'] = 'Learning';
$string['achievement'] = 'Achievement';
$string['viewall'] = 'View all notifications';
$string['preferences'] = 'Notification preferences';

// Privacy strings (Phase Z.1).
$string['privacy:metadata:log'] = 'Sent notification log — one row per delivered (or attempted) notification.';
$string['privacy:metadata:log:ruleid'] = 'The ID of the rule that triggered the notification.';
$string['privacy:metadata:log:userid'] = 'The recipient user ID.';
$string['privacy:metadata:log:courseid'] = 'Optional course context (NULL for site-wide notifications).';
$string['privacy:metadata:log:channel'] = 'Delivery channel (inapp/email/push/whatsapp).';
$string['privacy:metadata:log:subject'] = 'Subject line of the notification.';
$string['privacy:metadata:log:message'] = 'Body text of the notification.';
$string['privacy:metadata:log:status'] = 'Delivery state (sending/sent/read/failed).';
$string['privacy:metadata:log:timecreated'] = 'Send timestamp.';
$string['privacy:metadata:log:timeread'] = 'Read timestamp (NULL if unread).';
$string['privacy:metadata:prefs'] = 'User-specific notification preferences (channel toggles, quiet hours, rule-type opt-outs).';
$string['privacy:metadata:prefs:userid'] = 'The user the preferences belong to.';
$string['privacy:metadata:prefs:channel_inapp'] = 'Whether the user accepts in-app messages.';
$string['privacy:metadata:prefs:channel_email'] = 'Whether the user accepts email messages.';
$string['privacy:metadata:prefs:channel_push'] = 'Whether the user accepts push notifications.';
$string['privacy:metadata:prefs:digest_frequency'] = 'How often a digest is sent (none/daily/weekly).';
$string['privacy:metadata:prefs:disabled_rule_types'] = 'Comma-separated list of rule types the user has silenced.';
$string['privacy:metadata:prefs:quiet_hours_start'] = 'Quiet hours window start hour.';
$string['privacy:metadata:prefs:quiet_hours_end'] = 'Quiet hours window end hour.';
$string['privacy:metadata:prefs:timemodified'] = 'Last update timestamp.';
