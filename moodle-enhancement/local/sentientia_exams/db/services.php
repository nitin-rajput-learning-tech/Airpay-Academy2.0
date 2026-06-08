<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_exams_list_exams' => [
        'classname'    => 'local_sentientia_exams\external\list_exams',
        'description'  => 'List exams for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_exams:view',
    ],
    'local_sentientia_exams_toggle_status' => [
        'classname'    => 'local_sentientia_exams\external\toggle_status',
        'description'  => 'Activate or deactivate an exam',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_exams:manage',
    ],
    'local_sentientia_exams_delete_exam' => [
        'classname'    => 'local_sentientia_exams\external\delete_exam',
        'description'  => 'Delete an exam wrapper (does not affect underlying quiz)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_exams:manage',
    ],
];
