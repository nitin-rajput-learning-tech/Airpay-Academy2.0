<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_learningpath_toggle_status' => [
        'classname'    => 'local_airpay_learningpath\external\toggle_status',
        'description'  => 'Activate or archive a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:update',
    ],
    'local_airpay_learningpath_delete_path' => [
        'classname'    => 'local_airpay_learningpath\external\delete_path',
        'description'  => 'Delete a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:delete',
    ],
];
