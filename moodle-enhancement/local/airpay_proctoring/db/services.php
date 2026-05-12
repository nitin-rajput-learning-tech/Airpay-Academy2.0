<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_proctoring_start_session' => [
        'classname'    => 'local_airpay_proctoring\external\start_session',
        'description'  => 'Open a proctoring session for a quiz attempt',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:attempt',
    ],
    'local_airpay_proctoring_give_consent' => [
        'classname'    => 'local_airpay_proctoring\external\give_consent',
        'description'  => 'Record the candidate\'s consent for recording',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:attempt',
    ],
    'local_airpay_proctoring_submit_identity' => [
        'classname'    => 'local_airpay_proctoring\external\submit_identity',
        'description'  => 'Submit ID + selfie for identity verification',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:attempt',
    ],
    'local_airpay_proctoring_report_event' => [
        'classname'    => 'local_airpay_proctoring\external\report_event',
        'description'  => 'Report a behavioural event (face_lost, tab_switch, etc.)',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:attempt',
    ],
    'local_airpay_proctoring_upload_chunk' => [
        'classname'    => 'local_airpay_proctoring\external\upload_chunk',
        'description'  => 'Register a recording chunk that was uploaded directly to S3',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:attempt',
    ],
    'local_airpay_proctoring_finalize' => [
        'classname'    => 'local_airpay_proctoring\external\finalize_session',
        'description'  => 'Mark session finished and trigger AI analysis',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:attempt',
    ],
    'local_airpay_proctoring_list_attempts' => [
        'classname'    => 'local_airpay_proctoring\external\list_attempts',
        'description'  => 'List proctored attempts (admin)',
        'type'         => 'read', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:viewattempts',
    ],
    'local_airpay_proctoring_get_attempt' => [
        'classname'    => 'local_airpay_proctoring\external\get_attempt',
        'description'  => 'Get full detail of a proctored attempt',
        'type'         => 'read', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:viewattempts',
    ],
    'local_airpay_proctoring_list_review_queue' => [
        'classname'    => 'local_airpay_proctoring\external\list_review_queue',
        'description'  => 'List sessions pending human review',
        'type'         => 'read', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:review',
    ],
    'local_airpay_proctoring_flag' => [
        'classname'    => 'local_airpay_proctoring\external\flag_session',
        'description'  => 'Mark a session for human review',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:review',
    ],
    'local_airpay_proctoring_submit_review' => [
        'classname'    => 'local_airpay_proctoring\external\submit_review',
        'description'  => 'Reviewer submits a decision',
        'type'         => 'write', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:review',
    ],
    'local_airpay_proctoring_compliance_report' => [
        'classname'    => 'local_airpay_proctoring\external\compliance_report',
        'description'  => 'Compliance report: % clean vs flagged vs failed over time',
        'type'         => 'read', 'ajax' => true,
        'capabilities' => 'local/airpay_proctoring:review',
    ],
];
