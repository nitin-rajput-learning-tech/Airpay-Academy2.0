<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_exams_list_exams' => [
        'classname'    => 'local_airpay_exams\external\list_exams',
        'description'  => 'List exams for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_exams:view',
    ],
    'local_airpay_exams_toggle_status' => [
        'classname'    => 'local_airpay_exams\external\toggle_status',
        'description'  => 'Activate or deactivate an exam',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_exams:manage',
    ],
    'local_airpay_exams_delete_exam' => [
        'classname'    => 'local_airpay_exams\external\delete_exam',
        'description'  => 'Delete an exam wrapper (does not affect underlying quiz)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_exams:manage',
    ],
];
