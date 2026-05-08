<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_manager_list_requests' => [
        'classname'    => 'local_airpay_manager\external\list_requests',
        'description'  => 'List enrolment requests for the caller manager',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_manager:view',
    ],
    'local_airpay_manager_decide_request' => [
        'classname'    => 'local_airpay_manager\external\decide_request',
        'description'  => 'Approve or reject an enrolment request',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_manager:approve',
    ],
    'local_airpay_manager_list_allocations' => [
        'classname'    => 'local_airpay_manager\external\list_allocations',
        'description'  => 'List course allocations the caller manager has made',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_manager:view',
    ],
    'local_airpay_manager_create_allocation' => [
        'classname'    => 'local_airpay_manager\external\create_allocation',
        'description'  => 'Allocate a course to a direct report',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_manager:allocate',
    ],
    'local_airpay_manager_delete_allocation' => [
        'classname'    => 'local_airpay_manager\external\delete_allocation',
        'description'  => 'Remove a course allocation',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_manager:allocate',
    ],
    'local_airpay_manager_bulk_allocate' => [
        'classname'    => 'local_airpay_manager\external\bulk_allocate',
        'description'  => 'Allocate one course to N direct reports in a single batch',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_manager:allocate',
    ],
];
