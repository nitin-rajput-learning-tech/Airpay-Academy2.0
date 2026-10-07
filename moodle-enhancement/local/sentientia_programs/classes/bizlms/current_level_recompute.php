<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

defined('MOODLE_INTERNAL') || die();

/**
 * users.currentlevelid, worked out after the level completions have loaded.
 *
 * The level a learner is on is the first level (by position) that has no stored completion for them; a
 * completed learner is on the last level and a learner who has not started is on none. BizLMS derived it the
 * same way when it drew the page (renderer.php:330-335, program.php:1643-1647) and never stored it.
 *
 * Idempotent, as the contract requires: it recomputes every imported row and only changes the ones that differ.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class current_level_recompute extends recompute_step {

    public function key(): string {
        return 'program.currentlevel';
    }

    public function targettable(): string {
        return base_step::T_USERS;
    }

    public function recompute(array $targetids, context $ctx): array {
        global $DB;
        if (!$targetids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $targetids), SQL_PARAMS_NAMED, 'prgu');
        $users = $DB->get_records_select(base_step::T_USERS, "id {$insql}", $params, '',
            'id, programid, userid, status, currentlevelid');
        if (!$users) {
            return [];
        }

        $programids = [];
        $userids = [];
        foreach ($users as $user) {
            $programids[(int) $user->programid] = (int) $user->programid;
            $userids[(int) $user->userid] = (int) $user->userid;
        }

        // The levels of each program in position order.
        [$pin, $pparams] = $DB->get_in_or_equal(array_values($programids), SQL_PARAMS_NAMED, 'prgp');
        $levels = [];
        $rows = $DB->get_records_select(base_step::T_LEVELS, "programid {$pin}", $pparams,
            'programid, sortorder, id', 'id, programid, sortorder');
        foreach ($rows as $row) {
            $levels[(int) $row->programid][] = (int) $row->id;
        }

        // The stored completions of these learners in these programs.
        [$pin2, $pparams2] = $DB->get_in_or_equal(array_values($programids), SQL_PARAMS_NAMED, 'prgq');
        [$uin, $uparams] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'prgv');
        $done = [];
        $rows = $DB->get_records_select(base_step::T_LVLCOMP,
            "status = 1 AND programid {$pin2} AND userid {$uin}", $pparams2 + $uparams, '',
            'id, programid, levelid, userid');
        foreach ($rows as $row) {
            $done[(int) $row->programid][(int) $row->userid][(int) $row->levelid] = true;
        }

        $out = [];
        foreach ($users as $user) {
            $programid = (int) $user->programid;
            $new = rules::current_level((int) $user->status, $levels[$programid] ?? [],
                $done[$programid][(int) $user->userid] ?? []);
            $old = $user->currentlevelid === null ? null : (int) $user->currentlevelid;
            if ($new !== $old) {
                $out[] = outcome::update(base_step::T_USERS, (int) $user->id, (object) ['currentlevelid' => $new]);
            }
        }
        return $out;
    }
}
