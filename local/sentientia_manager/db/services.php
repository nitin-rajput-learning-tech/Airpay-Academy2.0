<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_manager_list_requests' => [
        'classname'    => 'local_sentientia_manager\external\list_requests',
        'description'  => 'List enrolment requests for the caller manager',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:view',
    ],
    'local_sentientia_manager_decide_request' => [
        'classname'    => 'local_sentientia_manager\external\decide_request',
        'description'  => 'Approve or reject an enrolment request',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:approve',
    ],
    'local_sentientia_manager_list_allocations' => [
        'classname'    => 'local_sentientia_manager\external\list_allocations',
        'description'  => 'List course allocations the caller manager has made',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:view',
    ],
    'local_sentientia_manager_create_allocation' => [
        'classname'    => 'local_sentientia_manager\external\create_allocation',
        'description'  => 'Allocate a course to a direct report',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:allocate',
    ],
    'local_sentientia_manager_delete_allocation' => [
        'classname'    => 'local_sentientia_manager\external\delete_allocation',
        'description'  => 'Remove a course allocation',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:allocate',
    ],
    'local_sentientia_manager_bulk_allocate' => [
        'classname'    => 'local_sentientia_manager\external\bulk_allocate',
        'description'  => 'Allocate one course to N direct reports in a single batch',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:allocate',
    ],

    // Phase 4 B.10 (2026-05-11) — bulk decide + team performance.
    'local_sentientia_manager_bulk_decide' => [
        'classname'    => 'local_sentientia_manager\external\bulk_decide',
        'description'  => 'Approve or reject many requests in one batch',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:approve',
    ],
    'local_sentientia_manager_team_performance' => [
        'classname'    => 'local_sentientia_manager\external\team_performance',
        'description'  => 'Aggregate per-direct-report metrics for the dashboard',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_manager:view',
    ],
];
