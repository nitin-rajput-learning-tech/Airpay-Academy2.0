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
// P1 #37 (2026-05-20) — assignments table.
// P1 #38 (2026-05-20) — show-non-respondents admin page.
// P1 #39 (2026-05-20) — bulk-assign by audience back-end.
// P1 #40 (2026-05-20) — bulk-assign modal + AMD wiring.
// P1 #41 (2026-05-20) — DB-backed template library.
// P1 #42 (2026-05-20) — auto-expire overdue assignments cron. Daily
//                       sweep that flips status='assigned' rows past
//                       their due_at to 'expired'. Polish that pairs
//                       with P1 #37/#38.
$plugin->version   = 2026052031;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.15.1';  // +P1 #42 auto-expire cron
