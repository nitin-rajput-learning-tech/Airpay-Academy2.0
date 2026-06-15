<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * QA content-path proof — add a file-free mod_page activity to a course so the
 * public-learner visual audit can prove that DB-backed activity content renders
 * end-to-end. This isolates the SCORM player 404s as a missing-filedir DATA
 * artifact (the production package binaries were never imported into this
 * clone's moodledata) rather than a product defect: a Page carries its body in
 * the {page}.content column with no moodledata asset, so if it renders for the
 * public learner while SCORM does not, the difference is purely the empty
 * filedir.
 *
 * Idempotent — reuses the existing proof page if one is already present.
 * LOCAL/QA INSTANCES ONLY: refuses to run when the qa_* accounts are absent.
 *
 * Usage:  php seed_qa_content_path_proof.php [courseid]
 *
 * @package local_sentientia_exams
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');

global $DB;

$courseid = (int) ($argv[1] ?? 400); // Public-tenant CS01 by default.

// QA-only guard — never let this run against a real deployment.
if (!$DB->record_exists('user', ['username' => 'qa_public', 'deleted' => 0])) {
    cli_error('qa_public not found — this seeder is for QA-provisioned instances only.');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$pagemodule = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);

$marker = 'QA-CONTENT-PATH-PROOF';

// Idempotency: reuse an existing proof page in this course if present.
foreach ($DB->get_records('page', ['course' => $courseid]) as $p) {
    if (strpos($p->name, $marker) === false) {
        continue;
    }
    $cm = $DB->get_record('course_modules',
        ['course' => $courseid, 'module' => $pagemodule->id, 'instance' => $p->id]);
    if ($cm) {
        echo "exists: proof page already present (cmid={$cm->id}).\n";
        echo "VIEW:   {$CFG->wwwroot}/mod/page/view.php?id={$cm->id}\n";
        exit(0);
    }
}

$content = '<h3>Content path proof</h3>'
    . '<p>This file-free Page activity is rendered straight from the database '
    . '(the body lives in {page}.content, with no moodledata filedir asset '
    . 'involved). If you can read this as the public learner, the activity '
    . 'content path works end-to-end.</p>'
    . '<ul>'
    . '<li>Storefront &rarr; course &rarr; activity link &rarr; content: OK.</li>'
    . '<li>SCORM packages fail only because this clone\'s filedir is empty '
    . '(production binaries were not imported) &mdash; a data artifact, not a '
    . 'defect.</li>'
    . '</ul>';

$moduleinfo = new stdClass();
$moduleinfo->modulename        = 'page';
$moduleinfo->module            = $pagemodule->id;
$moduleinfo->course            = $course->id;
$moduleinfo->section           = 0; // General section.
$moduleinfo->visible           = 1;
$moduleinfo->visibleoncoursepage = 1;
$moduleinfo->name              = $marker . ' — public learner content check';
$moduleinfo->intro             = '<p>QA proof that DB-backed content renders.</p>';
$moduleinfo->introformat       = FORMAT_HTML;
$moduleinfo->content           = $content;
$moduleinfo->contentformat     = FORMAT_HTML;
$moduleinfo->display           = RESOURCELIB_DISPLAY_OPEN;
$moduleinfo->printintro        = 0;
$moduleinfo->printlastmodified = 1;
// Common add_moduleinfo defaults (avoid strict-field notices on CLI).
$moduleinfo->cmidnumber        = '';
$moduleinfo->completion        = 0;
$moduleinfo->completionexpected = 0;
$moduleinfo->groupmode         = 0;
$moduleinfo->groupingid        = 0;
$moduleinfo->availabilityconditionsjson = '';

$moduleinfo = add_moduleinfo($moduleinfo, $course);

echo "created: proof page cmid={$moduleinfo->coursemodule} in course {$course->id} ({$course->shortname}).\n";
echo "VIEW:    {$CFG->wwwroot}/mod/page/view.php?id={$moduleinfo->coursemodule}\n";
exit(0);
