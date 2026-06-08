<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_challenge\challenge_engine;

class get_challenge extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Challenge ID'),
        ]);
    }

    public static function execute(int $id): array {
        global $DB, $USER;
        self::validate_parameters(self::execute_parameters(), compact('id'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_challenge:view', $context);

        $challenge = $DB->get_record('local_sentientia_challenge_challenges',
            ['id' => $id], '*', MUST_EXIST);
        $myattempt = $DB->get_record('local_sentientia_challenge_attempts',
            ['challengeid' => $id, 'userid' => $USER->id]);

        // Augment with totals (matches the shape used by list_challenges).
        $challenge->participants = (int) $DB->count_records(
            'local_sentientia_challenge_attempts', ['challengeid' => $id]);
        $challenge->completed = (int) $DB->count_records(
            'local_sentientia_challenge_attempts',
            ['challengeid' => $id, 'status' => challenge_engine::ATTEMPT_COMPLETED]);

        return challenge_engine::format_challenge_row($challenge, $myattempt ?: null);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'           => new external_value(PARAM_INT,  'ID'),
            'name'         => new external_value(PARAM_RAW,  'Name'),
            'shortname'    => new external_value(PARAM_TEXT, 'Shortname'),
            'description'  => new external_value(PARAM_RAW,  'Description HTML'),
            'type'         => new external_value(PARAM_TEXT, 'Type'),
            'targetcount'  => new external_value(PARAM_INT,  'Target'),
            'pointsreward' => new external_value(PARAM_INT,  'Points'),
            'status'       => new external_value(PARAM_INT,  'Status'),
            'statuslabel'  => new external_value(PARAM_TEXT, 'Status label'),
            'statuscss'    => new external_value(PARAM_TEXT, 'Status CSS'),
            'startdate'    => new external_value(PARAM_INT,  'Start date'),
            'enddate'      => new external_value(PARAM_INT,  'End date'),
            'participants' => new external_value(PARAM_INT,  'Participants'),
            'completed'    => new external_value(PARAM_INT,  'Completed'),
            'mystatus'     => new external_value(PARAM_TEXT, 'My attempt status'),
            'myprogress'   => new external_value(PARAM_INT,  'My progress'),
            'mytarget'     => new external_value(PARAM_INT,  'My target'),
            'mypoints'     => new external_value(PARAM_INT,  'My points'),
            'joined'       => new external_value(PARAM_BOOL, 'Joined'),
        ]);
    }
}
