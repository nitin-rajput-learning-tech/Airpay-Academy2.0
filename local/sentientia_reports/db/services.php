<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_reports_list_reports' => [
        'classname'    => 'local_sentientia_reports\external\list_reports',
        'description'  => 'List saved reports for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_reports:view',
    ],
    'local_sentientia_reports_delete_report' => [
        'classname'    => 'local_sentientia_reports\external\delete_report',
        'description'  => 'Delete a saved report definition',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_reports:manage',
    ],
    'local_sentientia_reports_toggle_status' => [
        'classname'    => 'local_sentientia_reports\external\toggle_status',
        'description'  => 'Toggle a report between active and archived status',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_reports:manage',
    ],
];
