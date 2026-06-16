<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_xapi.
 *
 * Consumed by \local_sentientia_platform\feature_flags::load_registry().
 * All flags default OFF — no impact on existing Airpay Academy production.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // Master switch — ALL xAPI/LRS functionality requires this ON.
    'sentientia.xapi.enabled' => [
        'default'     => false,
        'description' => 'Master switch for the Sentientia xAPI / LRS subsystem.
                          When OFF: the /lrs/statements endpoint returns 503,
                          Moodle event observers skip statement emission, and
                          cmi5 session tracking is disabled. Default OFF until
                          P1.4 ships and LRS credentials are configured.',
    ],

    // Sub-flag: emit login statements (potentially high-volume).
    'sentientia.xapi.emit_login' => [
        'default'     => false,
        'description' => 'Emit an xAPI "experienced" statement when a user logs in.
                          High-volume sites may leave this OFF to reduce LRS
                          write throughput. Requires sentientia.xapi.enabled = ON.',
    ],

    // Sub-flag: emit course_module_viewed statements (very high-volume).
    'sentientia.xapi.emit_module_view' => [
        'default'     => false,
        'description' => 'Emit an xAPI "experienced" statement on every course
                          module page view. Very high volume on active sites.
                          Enable only when you have sufficient LRS write capacity.
                          Requires sentientia.xapi.enabled = ON.',
    ],

    // Sub-flag: expose the LRS HTTP endpoint to external xAPI clients.
    'sentientia.xapi.lrs_endpoint_enabled' => [
        'default'     => false,
        'description' => 'Enable the external LRS endpoint
                          /local/sentientia_xapi/lrs/statements.php for inbound
                          xAPI statements from SCORM / cmi5 / external clients.
                          Requires sentientia.xapi.enabled = ON and at least one
                          client credential configured.',
    ],

    // Sub-flag: cmi5 session tracking.
    'sentientia.xapi.cmi5_enabled' => [
        'default'     => false,
        'description' => 'Enable cmi5 Assignable Unit session tracking.
                          When ON, the LRS endpoint recognises the cmi5 mandatory
                          verb set (initialized, terminated, passed, failed,
                          completed, satisfied) and writes cmi5 session rows.
                          Requires sentientia.xapi.lrs_endpoint_enabled = ON.',
    ],

];
