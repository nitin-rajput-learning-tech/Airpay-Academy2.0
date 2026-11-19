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
// Phase C 2026-11-19 (V8-B): the course count on a category tile (was "{n} All Courses").
$string['category_coursecount'] = '{$a} courses';
$string['newest'] = 'Newest';
$string['popular'] = 'Popular';
$string['atoz'] = 'A-Z';
// Phase C 2026-11-19 (V8-B): the filter bar button, its count badge and the filter group (were hard-coded English).
$string['filters_sort'] = 'Filters &amp; Sort';
$string['filters_active_aria'] = '{$a} active';
$string['filters_group_aria'] = 'Active filters and sort';
$string['nocourses'] = 'No courses found.';
$string['loadmore'] = 'Load more';
$string['viewdetails'] = 'View Details';
// Phase C 2026-11-19 (V8-B): course card NEW badge and aria-labels (templates/course_card.mustache; were hard-coded English).
$string['badge_new'] = 'NEW';
$string['badge_new_aria'] = 'New course';
$string['card_viewdetails_aria'] = 'View details for {$a}';
$string['card_enrolled_aria'] = '{$a} learners enrolled';
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
$string['storefront_checkout_pricediffers'] = '{$a} course(s) are priced differently at checkout than in your basket. Check the amounts on this page before you pay.';
$string['storefront_checkout_freeleft'] = 'Your basket still holds {$a} free course(s). They do not go through checkout: open your basket to enrol in them.';

// Owner decision CRS-14 (2026-10-07) -- the card label of a BizLMS pseudo-course (open_coursetype 1) that stays in a
// learner's in-progress rail, by open_module. Everything else keeps its E-Learning, Classroom or Exam label.
$string['coursetype_exam'] = 'Exam';
$string['coursetype_forum'] = 'Forum';
// Phase C 2026-11-19 (V8-B): the E-Learning and Classroom card type labels (catalog_manager; were literals, so the Hindi catalogue showed English).
$string['coursetype_elearning'] = 'E-Learning';
$string['coursetype_classroom'] = 'Classroom';

// Persona walk 2026-11-18 -- course.php no longer links to /enrol/index.php (the theme sends it back to course.php):
// where nothing on the page can enrol the learner, it says so, worded for an employee or for a storefront consumer
// (the _public strings, Phase C CAT-1 2026-11-19: a storefront consumer has no L&D team), and an enrolment that does
// not let them in says why.
$string['enrolment_managed'] = 'Enrolment in this course is managed by your L&D team.';
$string['enrolment_managed_public'] = 'This course is not open for online enrolment yet.';
$string['enrolment_suspended'] = 'Your enrolment in this course is suspended. Contact your L&D team.';
$string['enrolment_suspended_public'] = 'Your access to this course is on hold. Please contact us for help.';
$string['enrolment_notstarted'] = 'Your enrolment in this course starts on {$a}.';
$string['enrolment_ended'] = 'Your enrolment in this course ended on {$a}. Contact your L&D team.';
$string['enrolment_ended_public'] = 'Your access to this course ended on {$a}.';
$string['open_course'] = 'Open course';

// Phase C 2026-11-19 (V8-COURSE) -- the course page (course.php) prints these instead of hard-coded English.
$string['course_addedtocart_short'] = 'Added to cart.';
$string['course_addedtocart'] = '{$a} has been added to your cart.';
$string['course_viewcart'] = 'View cart ({$a})';
$string['course_enrolledcount'] = '{$a} enrolled';
$string['course_selfpaced'] = 'Self-paced';
$string['course_onetime'] = 'one-time';
$string['course_addtocart_price'] = 'Add to cart — {$a}';
$string['course_login'] = 'Log in to access';
$string['course_about'] = 'About this course';
$string['course_backtocatalog'] = 'Back to the course catalog';

// Phase C 2026-11-19 (V8-MYCOURSES) -- My courses (mycourses.php) prints these instead of hard-coded English.
$string['mycourses_filter_label'] = 'Filter your courses';
$string['mycourses_all'] = 'All courses';
$string['mycourses_inprogress'] = 'In progress';
$string['mycourses_completed'] = 'Completed';
$string['mycourses_notstarted'] = 'Not started';
$string['mycourses_done'] = 'Done';
$string['mycourses_percentcomplete'] = '{$a}% complete';
$string['mycourses_review'] = 'Review';
$string['mycourses_continue'] = 'Continue';
$string['mycourses_start'] = 'Start';
$string['mycourses_pagination'] = 'My courses pages';
$string['mycourses_showing'] = 'Showing {$a->from}–{$a->to} of {$a->total}';
$string['mycourses_empty_title'] = 'No courses found';
$string['mycourses_empty_message'] = 'Browse the catalog to discover courses.';
$string['mycourses_browse'] = 'Browse the catalog';
