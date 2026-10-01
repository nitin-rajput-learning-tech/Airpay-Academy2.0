<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname']        = 'Sentientia Ratings';
$string['rate']              = 'Rate';
$string['yourrating']        = 'Your rating';
$string['averagerating']     = 'Average rating';
$string['noratings']         = 'No ratings yet';

// W1-3 (2026-05-15) — write endpoint.
$string['sentientia_ratings:rate'] = 'Submit star ratings on courses, classrooms, programs and learning paths';
$string['invalidrating']       = 'Rating must be between 1 and 5 stars';
$string['invalidratearea']     = 'Unknown rating area';
$string['invaliditemid']       = 'Invalid item to rate';
$string['cannotrateasguest']   = 'You must be logged in to submit a rating';
$string['ratingsaved']         = 'Your rating has been saved';
$string['rateaccessibility']   = 'Rate {$a} out of 5';

// Privacy metadata (classes/privacy/provider.php, added 2026-09-22).
// Replaced a null_provider that wrongly asserted this plugin held no
// personal data. Every table below is keyed on a user id.
$string['privacy:metadata:ratings'] = 'A rating an employee gave to a course or other item.';
$string['privacy:metadata:ratings:userid'] = 'The ID of the user this record is about.';
$string['privacy:metadata:ratings:itemid'] = 'The ID of the thing that was rated.';
$string['privacy:metadata:ratings:ratearea'] = 'Which kind of thing was rated.';
$string['privacy:metadata:ratings:rating'] = 'The rating the user gave.';
$string['privacy:metadata:ratings:timecreated'] = 'When the record was created.';
$string['privacy:metadata:ratings:timemodified'] = 'When the record was last changed.';

// ADR-032 (2026-09-30) - BizLMS import: the item reviews page and the privacy metadata of the two tables the
// import added. The page is behind two flags, OFF by default.
$string['err_feature_off'] = 'This page is not available.';
$string['reviewspagetitle'] = 'Reviews';
$string['reviewsheading'] = 'Ratings and reviews';
$string['reviewsempty'] = 'There are no reviews to show.';
$string['reviewsscope'] = 'Only reviews from people in your own organisation are listed.';
$string['averagesummary'] = 'Average rating {$a->average} from {$a->count} ratings';
$string['reviewrating'] = '{$a} out of 5';
$string['reactionlikes'] = 'Likes: {$a}';
$string['reactiondislikes'] = 'Dislikes: {$a}';
$string['privacy:metadata:reviews'] = 'A written review an employee left on a course or other item.';
$string['privacy:metadata:reviews:userid'] = 'The ID of the user this record is about.';
$string['privacy:metadata:reviews:itemid'] = 'The ID of the thing that was reviewed.';
$string['privacy:metadata:reviews:ratearea'] = 'Which kind of thing was reviewed.';
$string['privacy:metadata:reviews:review'] = 'The text the user wrote.';
$string['privacy:metadata:reviews:timecreated'] = 'When the review was written.';
$string['privacy:metadata:reviews:timemodified'] = 'When the record was last changed.';
$string['privacy:metadata:reactions'] = 'A like or dislike an employee gave to a course or other item.';
$string['privacy:metadata:reactions:userid'] = 'The ID of the user this record is about.';
$string['privacy:metadata:reactions:itemid'] = 'The ID of the thing that was reacted to.';
$string['privacy:metadata:reactions:ratearea'] = 'Which kind of thing was reacted to.';
$string['privacy:metadata:reactions:likestatus'] = 'Whether the user liked (1) or disliked (2) the item.';
$string['privacy:metadata:reactions:timecreated'] = 'When the reaction was first given.';
$string['privacy:metadata:reactions:timemodified'] = 'When the record was last changed.';
