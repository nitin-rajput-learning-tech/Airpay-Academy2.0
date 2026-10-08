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
 * 2026-10-07 (owner decision CRS-11): sentientia.ratings.widget gates the interactive course-page stars. The submit web
 * service stays capability-gated as before; the flag only decides whether the page offers the control at all.
 *
 * @package local_sentientia_ratings
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Ratings ────────────────────────────────
    'sentientia.ratings.widget' => [
        'default'     => false,
        'description' => 'Interactive course star rating (owner decision CRS-11,
                          2026-10-07). When ON, the stars on a course page are
                          clickable for a signed-in learner who may rate
                          (local/sentientia_ratings:rate): a click saves the
                          rating through the submit_rating web service and
                          refreshes the average, as on BizLMS. OFF (the
                          default): the stars are read-only, one image with a
                          text alternative, instead of the buttons that did
                          nothing because no script ever initialised them.
                          Recommended future flip, not decided: ON for Airpay
                          at cutover, after the visual evidence is reviewed.',
    ],

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
                          in BizLMS. Since 2026-10-07 (owner decision CRS-12)
                          the same counts also sit beside the stars on a course
                          page, when an item has any. OFF: no counts are shown.',
    ],

];
