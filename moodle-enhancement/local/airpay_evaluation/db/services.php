<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_evaluation_list_evaluations' => [
        'classname'    => 'local_airpay_evaluation\external\list_evaluations',
        'description'  => 'List evaluations for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
    'local_airpay_evaluation_change_status' => [
        'classname'    => 'local_airpay_evaluation\external\change_status',
        'description'  => 'Change evaluation form status (draft/active/archived)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
    'local_airpay_evaluation_delete_evaluation' => [
        'classname'    => 'local_airpay_evaluation\external\delete_evaluation',
        'description'  => 'Delete an evaluation form (cascades through questions and responses)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
    'local_airpay_evaluation_delete_question' => [
        'classname'    => 'local_airpay_evaluation\external\delete_question',
        'description'  => 'Delete a question from an evaluation form',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
    'local_airpay_evaluation_reorder_questions' => [
        'classname'    => 'local_airpay_evaluation\external\reorder_questions',
        'description'  => 'Reorder questions within an evaluation form',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
    'local_airpay_evaluation_submit_response' => [
        'classname'    => 'local_airpay_evaluation\external\submit_response',
        'description'  => 'Submit a response to an evaluation form (learner)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:respond',
    ],

    // P1 #39 (2026-05-20) — bulk-assign by audience.
    'local_airpay_evaluation_preview_audience' => [
        'classname'    => 'local_airpay_evaluation\external\preview_audience',
        'description'  => 'Preview the user count for a target-audience filter',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
    'local_airpay_evaluation_bulk_assign_by_audience' => [
        'classname'    => 'local_airpay_evaluation\external\bulk_assign_by_audience',
        'description'  => 'Assign an evaluation to every user matching a target-audience filter',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_evaluation:manage',
    ],
];
