<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Airpay Employee Lifecycle';

// Joiner auto-enrolment (2026-08-07, ADR-029).
$string['autoenrol_heading'] = 'Joiner auto-enrolment';
$string['autoenrol_heading_desc'] = 'New users are auto-enrolled in mandatory courses when the sentientia.lifecycle.autoenrol.enabled feature flag is on (default off). A course is mandatory when it carries the tag configured below; courses are matched to the joiner\'s tenant via their org path.';
$string['mandatory_tag'] = 'Mandatory-course tag';
$string['mandatory_tag_desc'] = 'Course tag that marks a course as mandatory for new joiners. Default: "mandatory".';

// Privacy.
$string['privacy:metadata'] = 'The Airpay sentientia_lifecycle plugin does not store personal data in plugin-owned tables.';
