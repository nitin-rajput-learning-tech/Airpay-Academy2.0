<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_evaluation';
// W1-5 + W1-9 + P1 #17 + P1 #18 + P1 #19 (2026-05-16) — observer +
// trigger queue + scheduled task + availability window + pulse mode +
// numeric + multi-select multichoice question types + email-on-response
// admin notification.
// P1 #27 (2026-05-20) — Hindi (hi) lang pack catch-up: 132 strings
// translated, covering all P1 #17/#18/#19 additions.
// P1 #30 (2026-05-20) — conditional question display.
// P1 #31 (2026-05-20) — front-end JS show/hide.
// P1 #37 (2026-05-20) — assignments table. New local_airpay_evaluation_assign
//                       tracks WHO was meant to respond; trigger queue auto-
//                       creates rows; submit_response marks them 'responded'.
//                       Closes audit items #20 + #21 from
//                       parity-audit-2026-05-15/airpay_evaluation.md.
$plugin->version   = 2026052020;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.13.0';  // +P1 #37 assignments table
