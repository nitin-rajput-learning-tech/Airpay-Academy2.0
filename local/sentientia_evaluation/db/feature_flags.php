<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_evaluation.
 *
 * Per CLAUDE.md section 5 every new user-visible feature ships behind a default-OFF flag whose OFF state matches
 * today's behaviour. This file is new with ADR-032 (the BizLMS import): the reader surfaces that show imported
 * history are flags, and the import itself is not (it is gated by its CLI guard).
 *
 * @package local_sentientia_evaluation
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.evaluation.learner_history' => [
        'default'     => false,
        'description' => 'A learner\'s own evaluation history (2026-09-30, ADR-032). When OFF
                          (default), /local/sentientia_evaluation/my_evaluations.php answers
                          "not available" and nothing links to it, as before. When ON, a
                          learner who holds local/sentientia_evaluation:respond sees the
                          evaluations they answered, were asked to answer or missed,
                          including those brought over from the previous system. It shows
                          only rows that name the learner: an anonymous form is listed as
                          "responded" by day, never with its answers. The flag only gates
                          the page; nothing else changes. Flip it for a customer after the
                          page has been reviewed.',
    ],

    'sentientia.evaluation.response_drilldown' => [
        'default'     => false,
        'description' => 'The individual responses pages (2026-10-07, EV-06). Until now
                          /local/sentientia_evaluation/response_list.php and response_detail.php
                          asked for a capability that no plugin declares, so nobody could open
                          them, site administrators included. When OFF (default) both pages answer
                          "not available" and nothing links to them, exactly as before. When ON, a
                          user who holds local/sentientia_evaluation:manage (the manager
                          archetype: manager, tenant administrator, site administrator; not
                          trainers, not employees) and who may manage the evaluation (ADR-031,
                          their own tenant) sees each submission on its own: the respondent, and
                          for a supervisor form the person it is about, with the answers beside
                          how everybody else answered. A form that is anonymous, or ever held an
                          anonymous answer, names nobody and shows the day, not the minute. The
                          flag only gates the two pages and the "Individual responses" link on
                          responses.php; nothing else changes. Flip it for a customer after the
                          screenshots of both pages have been reviewed.',
    ],

];
