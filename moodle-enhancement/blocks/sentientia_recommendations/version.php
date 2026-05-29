<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Block plugin version — Sentientia LMS AI Recommendations block.
 *
 * Renders the top N personalised course recommendations for the current
 * learner on any page that supports blocks (dashboard, course pages,
 * site index). Gated by the sentientia.recommendations.enabled flag.
 *
 * @package    block_sentientia_recommendations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_sentientia_recommendations';
// 2026052901 — Defensive tenant gate added in get_content(): drops any
// rec row whose course sits outside the viewer's tenant subtree (resolved
// via accesslib::get_tenant_category_id). Compensates for stale rec_log
// rows persisted before the recommendation_engine cross-tenant fix
// landed. Broader-sweep follow-up to db5242c9a.
$plugin->version   = 2026052901;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.1-alpha';
$plugin->dependencies = [
    'local_sentientia_recommendations' => 2026052500,
];
