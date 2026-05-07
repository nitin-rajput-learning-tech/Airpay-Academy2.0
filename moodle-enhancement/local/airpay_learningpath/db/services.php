<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_learningpath_list_paths' => [
        'classname'    => 'local_airpay_learningpath\external\list_paths',
        'description'  => 'List learning paths for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:view',
    ],
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

    // ── Course assignment (G-04) ────────────────────────────────────────
    'local_airpay_learningpath_assign_courses' => [
        'classname'    => 'local_airpay_learningpath\external\assign_courses',
        'description'  => 'Bulk-assign courses to a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:update',
    ],
    'local_airpay_learningpath_unassign_course' => [
        'classname'    => 'local_airpay_learningpath\external\unassign_course',
        'description'  => 'Remove a course from a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:update',
    ],
    'local_airpay_learningpath_reorder_courses' => [
        'classname'    => 'local_airpay_learningpath\external\reorder_courses',
        'description'  => 'Reorder courses within a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:update',
    ],
    'local_airpay_learningpath_list_path_courses' => [
        'classname'    => 'local_airpay_learningpath\external\list_path_courses',
        'description'  => 'List courses assigned to a learning path',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:view',
    ],

    // ── User enrolment (G-04) ───────────────────────────────────────────
    'local_airpay_learningpath_enrol_users' => [
        'classname'    => 'local_airpay_learningpath\external\enrol_users',
        'description'  => 'Bulk-enrol users in a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:enrol',
    ],
    'local_airpay_learningpath_unenrol_user' => [
        'classname'    => 'local_airpay_learningpath\external\unenrol_user',
        'description'  => 'Unenrol a user from a learning path',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:enrol',
    ],
    'local_airpay_learningpath_list_path_users' => [
        'classname'    => 'local_airpay_learningpath\external\list_path_users',
        'description'  => 'List users enrolled in a learning path',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_learningpath:view',
    ],
];
