<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_ratings';
// W1-3 (2026-05-15) — add write endpoint (submit_rating WS, capability,
// interactive AMD widget). Version bump triggers Moodle to register the new
// db/services.php and db/access.php files.
// P1 #51 (2026-05-20) — Hindi pack: 12 strings (star widget + capability + errors).
// ADR-022 batch-1 (2026-06-03) — renamed from local_airpay_ratings (component/dir/table/
// capability/WS) via a DB hand-over; this bump rebuilds the classmap + re-registers the WS.
// ADR-032 (2026-09-30) — BizLMS import, ratings: two new tables (reviews, reactions), the importer
// (classes/bizlms), flag-gated readers (sentientia.ratings.reviews / .reactions, both OFF), and the
// legacy local_rating fallback removed from rating_manager.
$plugin->version   = 2026093001;  // ADR-032 ratings importer: reviews + reactions tables, importer, flagged readers
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.0';  // +ADR-032 BizLMS ratings import (was 1.1.3: +ADR-022 rename to local_sentientia_ratings)
