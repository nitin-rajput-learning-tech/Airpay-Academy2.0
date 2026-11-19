<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_courses.
 *
 * Read by \local_sentientia_platform\feature_flags::load_registry(): a key that is not registered here (or in
 * another plugin's db/feature_flags.php) makes ::set() throw. Every flag defaults OFF (CLAUDE.md section 5), so
 * Manage Courses stays exactly as Airpay Academy users see it today until the owner turns a flag on.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Post-demo plan 2.4 (2026-11-19): two more row actions on Manage Courses ───
    'sentientia.courses.advanced_actions.enabled' => [
        'default'     => false,
        'description' => 'Manage Courses row actions "Open course" and "Advanced settings" (post-demo plan
                          2.4, 2026-11-19). When ON, every row of the Manage Courses table
                          (/local/sentientia_courses/index.php) gets an "Open course" icon (the course
                          page, /course/view.php) and, where the viewer may change the course, an
                          "Advanced settings" icon that opens Moodle\'s full course settings form
                          (/course/edit.php): completion tracking, course image, appearance, files and
                          the other settings the Manage Courses pop-up does not carry. "Advanced
                          settings" is offered only to a viewer who holds moodle/course:update in that
                          course, and, like the pencil, not to a tenant admin on a legacy course with no
                          organisation (every tenant lists those). When OFF (the default) the row is
                          exactly as before. Turning it on for a customer is the owner\'s decision, taken
                          after the visual evidence is reviewed. See classes/external/list_courses.php.',
    ],

];
