<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
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
];
