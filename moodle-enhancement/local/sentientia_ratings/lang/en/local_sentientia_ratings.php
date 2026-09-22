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
