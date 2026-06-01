<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_airpay_catalog.
 *
 * C4 (2026-05-29) — public guest storefront LXP restyle. Per CLAUDE.md
 * §13 the flag ships default OFF, and OFF reproduces today's
 * production behaviour exactly (the plain inline-styled grid).
 *
 * @package local_airpay_catalog
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.catalog.public_lxp.enabled' => [
        'default'     => true,
        'description' => 'Public guest storefront (public.php) LXP /
                          Netflix restyle (C4 / F-004). When OFF
                          (default) the guest catalog renders the legacy
                          plain card grid — exactly today\'s production
                          look. When ON, the guest storefront uses the
                          same airpay-catalog__* card + carousel visual
                          language as the logged-in member catalog
                          (index.php): a "Popular picks" scroll-snap
                          rail (hidden during search) above the
                          searchable, sortable course grid. Commerce
                          (pricing, Add to cart, cart pill) is preserved
                          in both modes.',
    ],

    'sentientia.catalog.free_oneclick_enrol.enabled' => [
        'default'     => false,
        'description' => 'One-click free self-enrolment for internal-tenant
                          employees (QA-walk P1, 2026-05-29). When OFF
                          (default) every free-course "Enroll" button routes
                          through the session cart — exactly today\'s
                          production behaviour. When ON, a logged-in user
                          from an INTERNAL tenant (any tenant that is not the
                          Public storefront tenant /77) clicking a FREE course
                          is enrolled immediately via the manual enrol plugin
                          (bypassing any self-enrol enrolment key) and taken
                          straight into the course — no cart/checkout step.
                          The Public storefront keeps Add-to-Cart, and paid
                          courses always use the cart, in both modes. Enable
                          per internal tenant (e.g. Airpay /1, ZEEA /177) to
                          unblock employees. See classes/enrolment.php.',
    ],

];
