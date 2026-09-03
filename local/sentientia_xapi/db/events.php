<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Moodle event observers — emit xAPI statements on learning events.
 *
 * Every observer silently no-ops when the sentientia.xapi.enabled
 * feature flag is OFF (default).
 *
 * Observed events:
 *   - core\event\course_completed        → verb: completed
 *   - mod_quiz\event\attempt_submitted   → verb: passed | failed
 *   - core\event\course_module_viewed    → verb: experienced (sub-flag)
 *   - core\event\user_loggedin           → verb: experienced (sub-flag)
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_sentientia_xapi\observer::course_completed',
    ],

    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_sentientia_xapi\observer::quiz_submitted',
    ],

    [
        'eventname' => '\core\event\course_module_viewed',
        'callback'  => '\local_sentientia_xapi\observer::course_module_viewed',
    ],

    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_sentientia_xapi\observer::user_loggedin',
    ],

];
