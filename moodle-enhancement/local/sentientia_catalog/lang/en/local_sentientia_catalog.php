<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Course Catalog';
$string['catalog'] = 'Course Catalog';
$string['privacy:metadata'] = 'The catalog plugin does not store personal data.';
$string['search'] = 'Search courses, topics, skills...';
$string['continuelearning'] = 'Continue Learning';
$string['trending'] = 'Trending in Your Department';
$string['newthismonth'] = 'New This Month';
$string['browsecategory'] = 'Browse by Category';
$string['allcourses'] = 'All Courses';
$string['newest'] = 'Newest';
$string['popular'] = 'Popular';
$string['atoz'] = 'A-Z';
$string['nocourses'] = 'No courses found.';
$string['loadmore'] = 'Load more';
$string['viewdetails'] = 'View Details';
$string['enroll'] = 'Enroll';
$string['continue'] = 'Continue';

// Sprint C (2026-05-13) — cross-tenant course sharing provenance badge.
// The placeholder receives the provider tenant's name. Must stay
// single-quoted so PHP doesn't pre-interpolate $a at file load time.
$string['provenance_provided_by'] = 'Provided by {$a}';

// C4 (2026-05-29) — public guest storefront LXP restyle.
$string['public_popularpicks'] = 'Popular picks';
$string['public_browseall'] = 'Browse all courses';
$string['public_coursesavailable'] = '{$a} courses available';
$string['public_details'] = 'Details';
$string['public_addtocart'] = 'Add to cart';
$string['public_enrolfree'] = 'Enrol free';
$string['public_free'] = 'Free';
$string['public_scrollleft'] = 'Scroll left';
$string['public_scrollright'] = 'Scroll right';
$string['public_cart'] = 'Cart ({$a})';
$string['public_nocourses'] = 'No courses found';
$string['public_nocourses_hint'] = 'Try a different search term or browse all courses.';
$string['public_clearsearch'] = 'Clear search';
$string['public_sort_popular'] = 'Popular';
$string['public_sort_newest'] = 'Newest';
$string['public_sort_name'] = 'A-Z';

// QA-walk P1 (2026-05-29) — one-click free self-enrolment for internal tenants.
$string['enrol_now_free'] = 'Enrol now — free';
$string['enrolled_welcome'] = 'You\'re enrolled — welcome to the course!';
$string['enrolled_count'] = 'Enrolled in {$a} free course(s)!';
$string['enrolled_none'] = 'We couldn\'t complete your free enrolment. Please try again or contact your administrator.';

// Persona pass D2 (2026-09-30) — storefront basket to order cart hand-off
// (flag sentientia.catalog.storefront_checkout.enabled, default OFF).
$string['storefront_checkout_button'] = 'Proceed to checkout';
$string['storefront_checkout_hint'] = 'Your paid courses move to your order cart, where you confirm your billing details and choose how to pay.';
$string['storefront_checkout_moved'] = '{$a} course(s) moved to checkout.';
$string['storefront_checkout_refused'] = '{$a} course(s) could not be moved to checkout and are still in your basket. They may not be on sale online yet, or may not be available to your account.';
$string['storefront_checkout_redundant'] = '{$a} course(s) were removed from your basket because you are already enrolled.';
$string['storefront_checkout_nothing'] = 'None of the paid courses in your basket could be moved to checkout.';
