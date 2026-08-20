<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname']        = 'Airpay Ratings';
$string['rate']              = 'Rate';
$string['yourrating']        = 'Your rating';
$string['averagerating']     = 'Average rating';
$string['noratings']         = 'No ratings yet';

// W1-3 (2026-05-15) — write endpoint.
$string['airpay_ratings:rate'] = 'Submit star ratings on courses, classrooms, programs and learning paths';
$string['invalidrating']       = 'Rating must be between 1 and 5 stars';
$string['invalidratearea']     = 'Unknown rating area';
$string['invaliditemid']       = 'Invalid item to rate';
$string['cannotrateasguest']   = 'You must be logged in to submit a rating';
$string['ratingsaved']         = 'Your rating has been saved';
$string['rateaccessibility']   = 'Rate {$a} out of 5';

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:ratings']              = 'Star ratings the user has given';
$string['privacy:metadata:ratings:userid']       = 'The user who rated';
$string['privacy:metadata:ratings:itemid']       = 'The item that was rated';
$string['privacy:metadata:ratings:ratearea']     = 'What kind of item was rated (course, classroom, program, path)';
$string['privacy:metadata:ratings:rating']       = 'The 1-5 star value given';
$string['privacy:metadata:ratings:timecreated']  = 'When the rating was first given';
$string['privacy:metadata:ratings:timemodified'] = 'When the rating was last changed';
