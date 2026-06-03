<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_ratings';
// W1-3 (2026-05-15) — add write endpoint (submit_rating WS, capability,
// interactive AMD widget). Version bump triggers Moodle to register the new
// db/services.php and db/access.php files.
// P1 #51 (2026-05-20) — Hindi pack: 12 strings (star widget + capability + errors).
// ADR-022 batch-1 (2026-06-03) — renamed from local_airpay_ratings (component/dir/table/
// capability/WS) via a DB hand-over; this version bump drives Moodle's upgrade flow to
// rebuild the component classmap + re-register the renamed web service cleanly.
$plugin->version   = 2026060302;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.2';  // +ADR-022 rename to local_sentientia_ratings
