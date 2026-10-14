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
// 2026100701 -- owner decisions of 2026-10-07 (courses cluster): CRS-11, new flag sentientia.ratings.widget (default OFF)
// gates the interactive course-page stars (the theme called render() with its interactive default and never initialised the
// widget, so learners saw buttons that did nothing); OFF renders read-only stars with a text alternative, ON renders
// interactive stars and loads the AMD widget once (rating_manager::render_for_viewer()). Doc item "ratings security": the
// 194 scanner rows in local_like are labelled in the parity report (skip detail scanner_payload). CRS-12 follow-up code: the
// like and dislike counts sit beside the stars on a course page, behind sentientia.ratings.reactions (still OFF).
// No schema or capability change.
$plugin->version   = 2026100701;  // CRS-11 widget flag (default OFF) + scanner-row label in the import report; no schema/cap change
// 2026093001: ADR-032 ratings importer: reviews + reactions tables, importer, flagged readers
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.1';  // +CRS-11 sentientia.ratings.widget (was 1.2.0: +ADR-032 BizLMS ratings import)
