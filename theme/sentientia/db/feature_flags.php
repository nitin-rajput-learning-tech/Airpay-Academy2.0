<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for theme_sentientia.
 *
 * @package theme_sentientia
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — Dashboard ─────────────────────────────
    'sentientia.dashboard.blocksdrawer.enabled' => [
        'default'     => false,
        'description' => 'Dashboard blocks in a right-hand drawer, as Boost shows
                          them (Nitin, 2026-10-24). The blocks are the ones in the
                          dashboard\'s side-pre region and in the nine BizLMS
                          layer* regions (calendar, recently accessed items and
                          whatever users or admins added). When ON, a drawer toggle
                          appears at the right edge of /my/; the drawer shows those
                          blocks, plus the Add a block button in edit mode, and
                          remembers its open state (drawer-open-block preference).
                          When OFF, the blocks are printed into the dashboard\'s
                          hidden container, so they run but are not shown.',
    ],
];
