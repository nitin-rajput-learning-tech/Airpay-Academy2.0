<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_programs.
 *
 * ADR-032 (BizLMS program import): the two surfaces that show imported history are new, so each ships behind
 * its own flag, default OFF (CLAUDE.md section 13). With both OFF the plugin shows exactly what it showed
 * before the import existed. Turning them ON for Airpay at cutover is the owner's call, after the visual
 * evidence is reviewed (decision framework.reader_flags_airpay_at_cutover).
 *
 * Flags:
 *   sentientia.programs.learner.enabled
 *     The learner "My programs" page (myprograms.php): the programs the signed-in learner is enrolled in,
 *     their level-by-level progress from stored and live completions, and the program logo. BizLMS showed
 *     a learner this on its dashboard; the Sentientia program pages are manager/editingteacher only.
 *     Switched-off (archived or hidden) programs are not shown to learners (decision
 *     program.inactive_history_to_learners = false), only to admins.
 *   sentientia.programs.history.enabled
 *     The admin-side readers of imported history: the "Completed on" column of the program roster and the
 *     program logo on the program page.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.programs.learner.enabled' => [
        'default'     => false,
        'description' => 'Learner "My programs" page (ADR-032). Lists the active programs the learner is enrolled '
                       . 'in with level progress, completion dates and the program logo. When OFF (default) the '
                       . 'page refuses to open and nothing links to it.',
    ],

    'sentientia.programs.history.enabled' => [
        'default'     => false,
        'description' => 'Imported program history on the admin pages (ADR-032): the "Completed on" column of the '
                       . 'program roster and the program logo on the program page. When OFF (default) the roster '
                       . 'and program page look as they did before the BizLMS import.',
    ],

];
