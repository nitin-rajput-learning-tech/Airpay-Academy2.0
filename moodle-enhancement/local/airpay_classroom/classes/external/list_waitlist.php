<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class list_waitlist extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT, ''),
        ]);
    }
    public static function execute(int $classroomid): array {
        $params = self::validate_parameters(self::execute_parameters(), compact('classroomid'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/airpay_classroom:view', $ctx);

        $rows = \local_airpay_classroom\waitlist_manager::list_waiting(
            (int) $params['classroomid']);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->id,
                'userid'       => (int) $r->userid,
                'fullname'     => trim($r->firstname . ' ' . $r->lastname),
                'email'        => (string) $r->email,
                'employee_id'  => (string) ($r->open_employeeid ?? ''),
                'position'     => (int) $r->position,
                'status'       => $r->status,
                'reason'       => (string) ($r->reason ?? ''),
                'joined_on'    => userdate($r->timecreated, '%d %b %Y %H:%M'),
                'promoted_on'  => $r->promoted_at ? userdate($r->promoted_at, '%d %b %Y %H:%M') : '',
                'removed_on'   => $r->removed_at ? userdate($r->removed_at, '%d %b %Y %H:%M') : '',
            ];
        }
        return ['rows' => $out, 'total' => count($out)];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, ''),
            'rows'  => new external_multiple_structure(new external_single_structure([
                'id'           => new external_value(PARAM_INT, ''),
                'userid'       => new external_value(PARAM_INT, ''),
                'fullname'     => new external_value(PARAM_TEXT, ''),
                'email'        => new external_value(PARAM_TEXT, ''),
                'employee_id'  => new external_value(PARAM_TEXT, ''),
                'position'     => new external_value(PARAM_INT, ''),
                'status'       => new external_value(PARAM_ALPHANUMEXT, ''),
                'reason'       => new external_value(PARAM_TEXT, ''),
                'joined_on'    => new external_value(PARAM_TEXT, ''),
                'promoted_on'  => new external_value(PARAM_TEXT, ''),
                'removed_on'   => new external_value(PARAM_TEXT, ''),
            ])),
        ]);
    }
}
