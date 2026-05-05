<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_programs_list_programs' => [
        'classname'    => 'local_airpay_programs\external\list_programs',
        'description'  => 'List programs for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_programs:view',
    ],
    'local_airpay_programs_change_status' => [
        'classname'    => 'local_airpay_programs\external\change_status',
        'description'  => 'Change program status (draft/active/archived)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_programs:update',
    ],
    'local_airpay_programs_delete_program' => [
        'classname'    => 'local_airpay_programs\external\delete_program',
        'description'  => 'Delete a certification program',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_programs:delete',
    ],
];
