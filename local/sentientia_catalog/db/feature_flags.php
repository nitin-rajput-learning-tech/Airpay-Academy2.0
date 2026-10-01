<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_catalog.
 *
 * C4 (2026-05-29) — public guest storefront LXP restyle. Per CLAUDE.md
 * §13 the flag ships default OFF, and OFF reproduces today's
 * production behaviour exactly (the plain inline-styled grid).
 *
 * @package local_sentientia_catalog
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

    'sentientia.catalog.storefront_checkout.enabled' => [
        'default'     => false,
        'description' => 'Storefront basket to order cart hand-off (persona
                          pass D2, 2026-09-30). When OFF (default) a basket
                          holding a paid course ends in a disabled "Payment
                          Coming Soon" button on cart.php - exactly today\'s
                          behaviour. When ON, a logged-in buyer who holds
                          local/sentientia_cart:purchase, in a tenant the
                          cart is enabled for (enabled_tenants), sees
                          "Proceed to checkout" instead: the basket\'s paid
                          lines are added to the order cart
                          (cart_manager::add_item(), which applies the
                          ADR-031 catalogue purchase gate and the enrol_fee
                          price) and the buyer lands on the order cart\'s
                          checkout page. A line the buyer may not buy stays
                          in the basket. Leave OFF until the payment gateway
                          has been verified in sandbox. See
                          classes/checkout_bridge.php.',
    ],

    'sentientia.catalog.course_type_labels.enabled' => [
        'default'     => false,
        'description' => 'Course card type label from the course types
                          (ADR-032 course_lookups import, 2026-10-01). When
                          OFF (default) every course card is labelled
                          E-Learning, Classroom or Exam from the course\'s
                          open_coursetype - exactly today\'s behaviour. When
                          ON, a card shows the names of the course types the
                          course is identified as (the comma list in
                          open_identifiedas, looked up in
                          local_sentientia_course_type, which the BizLMS
                          import fills with the BizLMS course types). A course
                          that names no known type keeps the open_coursetype
                          label. Leave OFF until the import has run and the
                          course types have been reviewed. See
                          catalog_manager::course_type_labels().',
    ],

];
