<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_proctoring (ADR-038 D9, post-demo plan section 4B).
 *
 * One flag per assessment-integrity item, all default OFF. B1 (session 4, 2026-11-20) put code behind
 * sentientia.proctoring.enabled and B2 (session 7, 2026-11-20) behind sentientia.assessment.secure_mode.enabled.
 * The other three are registered so the Switchboard lists the whole plan and a later session cannot ship its item
 * without a flag; until their sessions land they change nothing, with one exception: while
 * sentientia.proctoring.recording.enabled is OFF the upload_chunk web service refuses every call (B1 review).
 *
 * Flags are read for the attempting learner's customer and tenant, so one tenant can run proctored quizzes alone.
 *
 * @package local_sentientia_proctoring
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.proctoring.enabled' => [
        'default'     => false,
        'description' => 'Proctored quizzes, foundation (B1, ADR-038 D2, 2026-11-20). When OFF (default)
                          every quiz behaves exactly as stock Moodle: the "Require proctoring" quiz
                          setting is hidden and ignored, no pre-start check is added, nothing is
                          loaded on attempt pages and no proctoring session is opened. When ON, for
                          a quiz with "Require proctoring" = Yes, the learner agrees to monitoring
                          and confirms their identity in the quiz\'s own pre-start check (the Start
                          button stays usable); every page of the open attempt records tab or window
                          switches, fullscreen exits and pastes with the server\'s time; when the
                          attempt is submitted or abandoned the server scores the session and sends
                          non-clean ones to the review queue. No camera, microphone or screen
                          recording. Previews by teachers are never proctored.',
    ],

    'sentientia.assessment.secure_mode.enabled' => [
        'default'     => false,
        'description' => 'Secure assessment mode (B2, session 7, ADR-038 D1, D3 and D6, 2026-11-20).
                          When OFF (default) the "Security level" quiz setting is hidden, a quiz with
                          no level behaves exactly as before (B1 proctoring included), and a quiz
                          saved as Secure or Proctored does not start (fail closed, with a message).
                          When ON, a quiz with a level adds its rules to the pre-start check; on the
                          attempt pages the questions show only in fullscreen (a button resumes),
                          copy, cut, paste, right-click, printing and common shortcuts are blocked,
                          the learner\'s name and attempt number are watermarked, and leaving
                          fullscreen or switching tab, window or application is recorded with the
                          server\'s time and counted (at most once per 2 seconds; a lost focus in the
                          first 10 seconds after a page opens is not counted). The page reports every
                          15 seconds; a gap is recorded. Employees: a warning per violation and the
                          attempt is flagged for review. Hiring assessments: submitted automatically
                          at the per-quiz limit (default 3, 0 = never). Teachers see an integrity
                          report on the attempt review page. Proctored also needs
                          sentientia.proctoring.enabled. It cannot see a second device, another
                          person or screenshots taken with the computer\'s own tools.',
    ],

    'sentientia.assessment.seb_preset.enabled' => [
        'default'     => false,
        'description' => 'Safe Exam Browser preset (B3, session 9, ADR-038 D4). Reserved: registered
                          2026-11-20, no code reads it yet. Will add the security level in the Exams
                          form and the seeded Safe Exam Browser templates.',
    ],

    'sentientia.proctoring.recording.enabled' => [
        'default'     => false,
        'description' => 'Webcam record-and-review (B4, session 15, ADR-038 D5 and D8). Reserved:
                          registered 2026-11-20; no recording code exists yet. While OFF (default)
                          the upload_chunk web service refuses every recording chunk (B1 review,
                          2026-11-20). Must stay OFF until Legal and the DPO sign off the webcam
                          data handling, an India-region store passes its check, and chunk keys
                          are tied to a prefix the server issues per session.',
    ],

    'sentientia.assessment.candidates.enabled' => [
        'default'     => false,
        'description' => 'Hiring candidate accounts (B5, session 17, ADR-038 D7). Reserved:
                          registered 2026-11-20, no code reads it yet. Will add invite-only,
                          expiring candidate accounts and the candidate notice.',
    ],

];
