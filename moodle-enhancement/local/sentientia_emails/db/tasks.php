<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_emails\task\process_rules',
        'blocking'  => 0,
        'minute'    => '15',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
    [
        // COMMS-N7 (2026-10-07): the learning-path plugin fires no event when it enrols a learner, so the learning-path
        // enrolment e-mail is sent from here. While its flag is OFF (the default) the run only moves its marker forward.
        'classname' => 'local_sentientia_emails\task\send_path_enrolments',
        'blocking'  => 0,
        'minute'    => '*/5',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
