<?php
// add_scorm_activity.php - add ONE mod_scorm activity to a course from a local SCORM zip.
// Proves the SCORM player works on the 5.2 runtime with real filedir content (UAT has no
// inherited filedir, so this is the only pre-Stage-B SCORM test). Idempotent by cmidnumber.
//
//   sudo -u www-data env SCORM_ZIP=/tmp/SAMPLE-SOP-scorm.zip COURSE_SHORT=UAT-AP-PRODUCT php add_scorm_activity.php
//
// Sentientia LMS / Airpay Payment Services 2026 - GPL v3 or later.
define('CLI_SCRIPT', true);
require(getenv('MOODLE_CONFIG') ?: '/var/www/html/moodle5.2/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/scorm/lib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

$zip = getenv('SCORM_ZIP') ?: '';
$short = getenv('COURSE_SHORT') ?: 'UAT-AP-PRODUCT';
if ($zip === '' || !file_exists($zip)) {
    fwrite(STDERR, "SCORM_ZIP must point at an existing zip (got: '$zip')\n");
    exit(1);
}
\core\session\manager::set_user(get_admin());
global $USER, $DB;

$course = $DB->get_record('course', ['shortname' => $short], '*', MUST_EXIST);
$module = $DB->get_record('modules', ['name' => 'scorm'], '*', MUST_EXIST);
$cmidnumber = strtoupper($short) . '-SCORM';

$existing = $DB->get_record('course_modules', ['course' => $course->id, 'idnumber' => $cmidnumber]);
if ($existing) {
    echo "SCORM activity already present in {$short} (cmid {$existing->id}); nothing to do.\n";
    exit(0);
}

// Put the zip into the admin's draft file area (the packagefile the module consumes).
$draftid = file_get_unused_draft_itemid();
$usercontext = context_user::instance($USER->id);
get_file_storage()->create_file_from_pathname([
    'component' => 'user', 'filearea' => 'draft', 'contextid' => $usercontext->id,
    'itemid' => $draftid, 'filename' => basename($zip), 'filepath' => '/',
], $zip);

$mi = (object) [
    'modulename' => 'scorm', 'module' => $module->id, 'course' => $course->id, 'section' => 1,
    'visible' => 1, 'visibleoncoursepage' => 1, 'cmidnumber' => $cmidnumber,
    'name' => 'SOP walkthrough (SCORM)',
    'intro' => '<p>A SCORM 1.2 standard operating procedure walkthrough. UAT sample content.</p>',
    'introformat' => FORMAT_HTML,
    'scormtype' => SCORM_TYPE_LOCAL, 'packagefile' => $draftid,
    'popup' => 0, 'width' => 100, 'height' => 500, 'skipview' => 0, 'hidebrowse' => 0,
    'displaycoursestructure' => 0, 'hidetoc' => 0, 'nav' => 1, 'navpositionleft' => -100, 'navpositiontop' => -100,
    'displayattemptstatus' => 1, 'displayactivityname' => 1, 'grademethod' => GRADEHIGHEST, 'maxgrade' => 100,
    'grademethodmenu' => GRADEHIGHEST, 'maxattempt' => 0, 'whatgrade' => HIGHESTATTEMPT,
    'forcenewattempt' => 0, 'lastattemptlock' => 0, 'masteryoverride' => 1, 'forcecompleted' => 0,
    'auto' => 0, 'updatefreq' => 0, 'completionstatusrequired' => null, 'completionscorerequired' => null,
    'completionstatusallscos' => 0, 'timeopen' => 0, 'timeclose' => 0,
    'groupmode' => 0, 'groupingid' => 0, 'availabilityconditionsjson' => '',
    'completion' => COMPLETION_TRACKING_NONE, 'completionview' => 0, 'completionexpected' => 0,
];

$mi = add_moduleinfo($mi, $course);
$cm = $DB->get_record('course_modules', ['id' => $mi->coursemodule], '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
$scoes = $DB->count_records('scorm_scoes', ['scorm' => $scorm->id]);
rebuild_course_cache($course->id, true);
echo "SCORM activity created in {$short}: cmid {$cm->id}, scormid {$scorm->id}, {$scoes} SCO(s) parsed.\n";
echo "Play URL: {$CFG->wwwroot}/mod/scorm/view.php?id={$cm->id}\n";
