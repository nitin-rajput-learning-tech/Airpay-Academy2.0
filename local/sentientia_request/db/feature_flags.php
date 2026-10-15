<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_request.
 *
 * Consumed by \local_sentientia_platform\feature_flags::load_registry().
 * Every flag defaults OFF - no change to what Airpay Academy users see today.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.request.imported_history' => [
        'default'     => false,
        'description' => 'Show the requests imported from BizLMS (ADR-032) in My requests, Pending
                          approvals and All requests, and count them in the approver nav badge.
                          When OFF (the default) those lists leave the imported rows out; the rows
                          stay in the database. Legacy pending course and learning-path requests
                          become actionable in the approvals inbox only when this is ON, and only
                          those whose requester is still active and whose item still exists (the
                          others are history, with no approver). The corrections made to the three
                          list screens themselves (the Item header, the route in words, status badges
                          in All requests, the SLA column, real names on path requests) are not behind
                          this flag: they repair screens that were wrong without any import. The
                          import never flips it: turning it on for Airpay is the owner\'s decision,
                          taken after the visual evidence is reviewed.',
    ],

    'sentientia.request.catalog_button' => [
        'default'     => false,
        'description' => 'Show "Request enrolment" on the course catalogue (visual-pass finding 7a,
                          2026-10-15). The Request enrolment dialog (request_button.js) has always
                          existed but no page rendered its button, so a learner could not start a
                          request. When OFF (the default) the catalogue is exactly as it was. When ON,
                          the logged-in catalogue card and the catalogue course page offer the button
                          to a learner who holds local/sentientia_request:request, is not enrolled,
                          may be enrolled in the course (visible and in the learner\'s tenant scope,
                          the test submit() applies), and has no way in of the course\'s own (no
                          enabled self-enrolment instance they can use); a free course the one-click
                          flag (sentientia.catalog.free_oneclick_enrol.enabled) enrols at once, and a
                          paid course (the cart), never get it. While a request of the learner\'s for
                          the course is pending the button reads "Request pending". Turning it on
                          for Airpay is the owner\'s decision, taken after the visual evidence is
                          reviewed. See classes/catalog_cta.php.',
    ],

];
