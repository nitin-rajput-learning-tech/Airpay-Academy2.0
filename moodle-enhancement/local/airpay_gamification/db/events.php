<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_airpay_gamification\observer::course_completed',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_airpay_gamification\observer::quiz_submitted',
    ],
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_airpay_gamification\observer::user_loggedin',
    ],
    [
        'eventname' => '\core\event\course_module_viewed',
        'callback'  => '\local_airpay_gamification\observer::course_module_viewed',
    ],
];
