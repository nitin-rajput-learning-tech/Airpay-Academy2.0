<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Seed default notification rules on install.
 */
function xmldb_local_sentientia_notifications_install() {
    global $DB;

    $now = time();
    $rules = [
        ['name' => 'Deadline 7 days',      'rule_type' => 'deadline_approaching', 'channel' => 'inapp', 'trigger_days' => 7,  'audience' => 'learner',  'template' => 'Your course "{{coursename}}" is due in 7 days.'],
        ['name' => 'Deadline 3 days',      'rule_type' => 'deadline_approaching', 'channel' => 'inapp', 'trigger_days' => 3,  'audience' => 'learner',  'template' => 'Urgent: "{{coursename}}" is due in 3 days!'],
        ['name' => 'Deadline 1 day',       'rule_type' => 'deadline_approaching', 'channel' => 'inapp', 'trigger_days' => 1,  'audience' => 'learner',  'template' => 'Last day! "{{coursename}}" is due tomorrow.'],
        ['name' => 'Course not started',   'rule_type' => 'course_not_started',   'channel' => 'inapp', 'trigger_days' => 3,  'audience' => 'learner',  'template' => 'You enrolled in "{{coursename}}" but haven\'t started. Jump in today!'],
        ['name' => 'Streak at risk',       'rule_type' => 'streak_broken',        'channel' => 'inapp', 'trigger_days' => 2,  'audience' => 'learner',  'template' => 'Your learning streak is at risk! Log in to keep it alive.'],
        ['name' => 'Manager team overdue', 'rule_type' => 'manager_nudge',        'channel' => 'inapp', 'trigger_days' => 0,  'audience' => 'manager',  'template' => '{{count}} team members have overdue courses. Check your dashboard.'],
        ['name' => 'New course available',  'rule_type' => 'new_course',           'channel' => 'inapp', 'trigger_days' => 0,  'audience' => 'learner',  'template' => 'New course available: "{{coursename}}". Check it out!'],
    ];

    foreach ($rules as $rule) {
        $rule['enabled']      = 1;
        $rule['timecreated']  = $now;
        $rule['timemodified'] = $now;
        $DB->insert_record('local_sentientia_notif_rules', (object)$rule);
    }
}
