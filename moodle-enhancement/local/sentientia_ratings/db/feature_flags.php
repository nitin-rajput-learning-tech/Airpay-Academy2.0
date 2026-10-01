<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_ratings.
 *
 * First flags added 2026-09-30 with the ADR-032 BizLMS import. The star rating itself pre-dates the registry
 * convention (its web service is capability-gated, not flag-gated: grandfathered). The two surfaces below show
 * what the import brought over from BizLMS and are NEW, so each ships OFF. The import never flips a flag;
 * whether they are ON for the Airpay customer at cutover is the owner's call, after the visual evidence is
 * reviewed (decision framework.reader_flags_airpay_at_cutover).
 *
 * @package local_sentientia_ratings
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Ratings ────────────────────────────────
    'sentientia.ratings.reviews' => [
        'default'     => false,
        'description' => 'Review list (ADR-032). When ON, the item reviews page
                          (/local/sentientia_ratings/reviews.php) lists the
                          written reviews learners left on a course, classroom,
                          programme or learning path, imported from BizLMS. The
                          list shows only reviewers in the viewer\'s own tenant,
                          hides blank reviews and deleted users, and escapes
                          every review on output. OFF: the page lists no
                          reviews.',
    ],

    'sentientia.ratings.reactions' => [
        'default'     => false,
        'description' => 'Like and dislike counts (ADR-032). When ON, the item
                          reviews page shows how many learners liked and
                          disliked an item, from the reactions imported from
                          BizLMS (status 1 = like, 2 = dislike; any other value
                          is never counted). Counts are site-wide per item, as
                          in BizLMS. OFF: no counts are shown.',
    ],

];
