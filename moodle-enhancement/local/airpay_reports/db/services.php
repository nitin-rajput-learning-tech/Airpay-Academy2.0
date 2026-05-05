<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_reports_list_reports' => [
        'classname'    => 'local_airpay_reports\external\list_reports',
        'description'  => 'List saved reports for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_reports:view',
    ],
    'local_airpay_reports_delete_report' => [
        'classname'    => 'local_airpay_reports\external\delete_report',
        'description'  => 'Delete a saved report definition',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_reports:manage',
    ],
    'local_airpay_reports_toggle_status' => [
        'classname'    => 'local_airpay_reports\external\toggle_status',
        'description'  => 'Toggle a report between active and archived status',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_reports:manage',
    ],
];
