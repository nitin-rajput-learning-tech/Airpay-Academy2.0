<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_skills_list_skills' => [
        'classname'    => 'local_airpay_skills\external\list_skills',
        'description'  => 'List skills for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_skills:manage',
    ],
    'local_airpay_skills_delete_skill' => [
        'classname'    => 'local_airpay_skills\external\delete_skill',
        'description'  => 'Delete a skill (and all role/course/user mappings)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_skills:manage',
    ],
    'local_airpay_skills_delete_category' => [
        'classname'    => 'local_airpay_skills\external\delete_category',
        'description'  => 'Delete a skill category (only if no skills reference it)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_skills:manage',
    ],
];
