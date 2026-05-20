<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_skills\skills_manager;

/**
 * P1 #25 (2026-05-20) — WS endpoint for learner self-attestation of a
 * skill level.
 *
 * Closes audit item #26 from
 * parity-audit-2026-05-15/airpay_skills.md.
 *
 * Contract:
 *   skillid      — Required. The skill being rated.
 *   level        — Required. 1..skill.max_level inclusive.
 *   userid       — Optional. Defaults to $USER->id. When supplied
 *                  AND different from the caller, requires the
 *                  'local/airpay_skills:manage' capability — admins
 *                  can backfill levels for a learner during onboarding
 *                  with their consent. Learners can only set their
 *                  own level.
 *
 * Returns:
 *   rowid        — The local_airpay_user_skills row id (after upsert).
 *   previous_level — What the level was before this call (0 if never set).
 *   new_level    — Confirms what was stored.
 *   source       — Always 'self' for this endpoint.
 *
 * @package local_airpay_skills
 */
class self_rate_skill extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'skillid' => new external_value(PARAM_INT, 'Skill ID'),
            'level'   => new external_value(PARAM_INT, 'Level (1..max_level)'),
            'userid'  => new external_value(PARAM_INT,
                'Optional override (admins only). 0 = caller.',
                VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $skillid, int $level, int $userid = 0): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('skillid', 'level', 'userid'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_sesskey();

        // Self-rate (default) requires only the new self_rate cap.
        // Backfill on behalf of someone else requires the manage cap.
        $target_userid = (int) $params['userid'];
        if ($target_userid === 0 || $target_userid === (int) $USER->id) {
            $target_userid = (int) $USER->id;
            require_capability('local/airpay_skills:self_rate', $context);
            $acting_userid = (int) $USER->id;
        } else {
            require_capability('local/airpay_skills:manage', $context);
            // The acting user is the admin; the subject is whoever they
            // named. skills_manager records both so HR can see who
            // backfilled what.
            $acting_userid = (int) $USER->id;
        }

        // Capture previous level so the response is informative.
        $previous_level = (int) ($DB->get_field('local_airpay_user_skills',
            'current_level',
            ['userid' => $target_userid, 'skillid' => (int) $params['skillid']]) ?: 0);

        $rowid = skills_manager::self_rate_skill(
            $target_userid, (int) $params['skillid'],
            (int) $params['level'], $acting_userid);

        return [
            'rowid'          => $rowid,
            'previous_level' => $previous_level,
            'new_level'      => (int) $params['level'],
            'source'         => 'self',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'rowid'          => new external_value(PARAM_INT,
                'local_airpay_user_skills.id (after upsert)'),
            'previous_level' => new external_value(PARAM_INT,
                'Level held before this call (0 if first attestation)'),
            'new_level'      => new external_value(PARAM_INT,
                'Level after this call'),
            'source'         => new external_value(PARAM_ALPHA,
                "Always 'self' for this endpoint"),
        ]);
    }
}
