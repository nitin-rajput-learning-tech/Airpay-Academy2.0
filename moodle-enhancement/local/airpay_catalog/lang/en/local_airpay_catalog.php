<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Course Catalog';
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
