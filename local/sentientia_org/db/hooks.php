<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hook subscriptions for local_sentientia_org.
 *
 * WF-016: after_config registers the BizLMS `local_costcenter` class
 * aliases (see classes/hook_callbacks.php) so the kept vendor blocks
 * keep working on trees where the local_costcenter plugin no longer
 * exists. Found by the FOOLPROOF render-smoke browser gate: a learner
 * opening /course/view.php fataled in block_learnerscript_leftmenunode()
 * (class "local_costcenter\lib\accesslib" not found).
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\after_config::class,
        'callback' => \local_sentientia_org\hook_callbacks::class . '::after_config',
        'priority' => 100,
    ],
];
