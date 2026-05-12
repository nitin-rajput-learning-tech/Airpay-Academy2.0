<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_request_submit' => [
        'classname'    => 'local_airpay_request\external\submit_request',
        'description'  => 'Submit a new course enrolment request',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_request:request',
    ],
    'local_airpay_request_list_mine' => [
        'classname'    => 'local_airpay_request\external\list_mine',
        'description'  => 'List the current user\'s own requests',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_request:request',
    ],
    'local_airpay_request_list_pending' => [
        'classname'    => 'local_airpay_request\external\list_pending',
        'description'  => 'List requests pending the current user\'s approval',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_request:approve',
    ],
    'local_airpay_request_list_all' => [
        'classname'    => 'local_airpay_request\external\list_all',
        'description'  => 'List ALL requests across the tenant (admin)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_request:viewall',
    ],
    'local_airpay_request_decide' => [
        'classname'    => 'local_airpay_request\external\decide',
        'description'  => 'Approve or reject a request',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_request:approve',
    ],
    'local_airpay_request_cancel' => [
        'classname'    => 'local_airpay_request\external\cancel_request',
        'description'  => 'Cancel one\'s own pending request',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_request:request',
    ],
];
