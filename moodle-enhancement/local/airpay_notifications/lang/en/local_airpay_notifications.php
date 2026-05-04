<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Smart Notifications';
$string['privacy:metadata'] = 'The notifications plugin stores notification preferences and delivery logs.';

// Capabilities.
$string['airpay_notifications:view'] = 'View notification rules';
$string['airpay_notifications:manage'] = 'Manage notification rules';

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
