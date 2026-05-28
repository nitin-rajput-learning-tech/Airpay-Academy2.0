<?php
/**
 * Event observers for employee lifecycle automation.
 * Triggers on: user_created, user_updated (dept change), user_deleted (suspend).
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_airpay_lifecycle\observer::user_created',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => '\local_airpay_lifecycle\observer::user_updated',
    ],
];
