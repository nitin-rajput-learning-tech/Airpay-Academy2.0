<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_evaluation';
// W1-5 + W1-9 + P1 #17 + P1 #18 + P1 #19 (2026-05-16) — observer +
// trigger queue + scheduled task + availability window + pulse mode +
// numeric + multi-select multichoice question types + email-on-response
// admin notification.
// P1 #27 (2026-05-20) — Hindi (hi) lang pack catch-up: 132 strings
// translated, covering all P1 #17/#18/#19 additions.
// P1 #30 (2026-05-20) — conditional question display. New
// depends_on_qid + depends_on_value columns on the questions table;
// admin picks a parent + value; respond page hides children whose
// parent answer doesn't match; submit_response treats hidden
// questions as not-required.
// P1 #31 (2026-05-20) — front-end JS show/hide. respond.mustache emits
// data-depends-on-qid + data-depends-on-value; response_actions.js
// recomputes visibility on every input event (mirrors PHP
// compute_visibility_map). Hidden cards are cleared so stale answers
// can't survive a parent flip. Also retrofits setButtonContent() to
// drop the last two innerHTML usages.
$plugin->version   = 2026052011;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.12.1';  // +P1 #31 conditional question UI
