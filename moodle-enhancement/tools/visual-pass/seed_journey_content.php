<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * LOCAL-ONLY: seed the content the persona-journey harness (persona_journeys.mjs)
 * needs and write the context file it reads (.journey-context.local.json).
 * Refuses unless wwwroot is localhost AND $CFG->noemailever is set, so it can
 * never touch UAT/production or mail anyone. Never deletes anything.
 *
 * Seeds, idempotently (each course is reused by shortname):
 *   vp_journey_free    "VP Journey Free Course"    /1    free, one Page activity;
 *                      vp_author1 is enrolled as its trainer (gradebook step).
 *   vp_journey_zeea    "VP Journey ZEEA Course"    /177  free (ZEEA one-click enrol).
 *   vp_journey_priced  "VP Journey Public Priced"  /77   Rs 500 - an enrol_fee
 *                      instance (what local_sentientia_cart reads; grants the
 *                      "employee" role) AND the local_sentientia_catalog
 *                      course_price_<id> setting (what the public storefront
 *                      reads), so both cart paths see a priced course.
 *   The Page activity in the free course carries a stored body (content +
 *   contentformat; an empty one from an earlier run is filled in), and one mock
 *   authoring draft "VP Journey Draft" is created for vp_courseauthor1 through the
 *   plugin's draft_manager (static text, no AI call) for the review-queue step.
 *
 * The context file holds, for the harness:
 *   - the seeded course ids / names / Page cmid;
 *   - the feature-flag state per tenant for the flags the journeys gate on;
 *   - oneOnlyCourseNames: names of visible /1 courses that appear in no other
 *     tenant's course list and are not shared out - the "must not leak" list for
 *     the ZEEA and public isolation checks. Read-only queries; course titles only.
 *
 *   php seed_journey_content.php     (run with cwd = moodle5/public, after provision_local_personas.php)
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/resourcelib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?(/|$)~', $CFG->wwwroot)) {
    fwrite(STDERR, "Refusing: wwwroot {$CFG->wwwroot} is not a local development host.\n");
    exit(1);
}
if (empty($CFG->noemailever)) {
    fwrite(STDERR, "Refusing: \$CFG->noemailever is not set - enrolment can send messages.\n");
    exit(1);
}

global $DB;
$dbman = $DB->get_manager();

// Top-level category per tenant root (same lookup as provision_local_personas.php).
$tenantcategory = function (string $root) use ($DB): int {
    $byroot = ['/1' => 'AirPay', '/77' => 'external', '/177' => 'ZEEA01'];
    $id = isset($byroot[$root])
        ? $DB->get_field('course_categories', 'id', ['idnumber' => $byroot[$root], 'parent' => 0]) : false;
    if (!$id) {
        $id = $DB->get_field_sql('SELECT MIN(id) FROM {course_categories} WHERE parent = 0 AND id > 1');
    }
    return (int) $id;
};

$mkcourse = function (string $shortname, string $fullname, string $path) use ($DB, $tenantcategory): stdClass {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = create_course((object) [
            'fullname' => $fullname, 'shortname' => $shortname, 'category' => $tenantcategory($path),
            'visible' => 1, 'format' => 'topics', 'numsections' => 1,
            'summary' => 'Created by the persona-journey harness (local test data).', 'summaryformat' => FORMAT_HTML,
        ]);
    }
    $DB->set_field('course', 'open_path', $path, ['id' => $course->id]);
    $DB->set_field('course', 'visible', 1, ['id' => $course->id]);
    return $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
};

$free1 = $mkcourse('vp_journey_free', 'VP Journey Free Course', '/1');
$zeea = $mkcourse('vp_journey_zeea', 'VP Journey ZEEA Course', '/177');
$priced = $mkcourse('vp_journey_priced', 'VP Journey Public Priced', '/77');

// A free course carries no fee instance and no catalog price (a fresh course has
// neither); the priced one gets both.
// The role a fee enrolment grants. "employee" is the learner role on this platform
// (there is no "student" shortname here, which used to leave roleid at 0); "student" is
// only the fallback for a vanilla Moodle role set.
$learnerrole = (int) ($DB->get_field('role', 'id', ['shortname' => 'employee'])
    ?: $DB->get_field('role', 'id', ['shortname' => 'student']) ?: 0);
$fee = $DB->get_record('enrol', ['enrol' => 'fee', 'courseid' => $priced->id], '*', IGNORE_MULTIPLE);
if (!$fee) {
    $DB->insert_record('enrol', (object) [
        'enrol' => 'fee', 'status' => ENROL_INSTANCE_ENABLED, 'courseid' => $priced->id, 'sortorder' => 9,
        'cost' => '500.00', 'currency' => 'INR', 'roleid' => $learnerrole,
        'timecreated' => time(), 'timemodified' => time(),
    ]);
} else if ((int) $fee->roleid === 0 && $learnerrole) {
    // An earlier run stored the instance with roleid 0: repair it, nothing else about it changes.
    $DB->set_field('enrol', 'roleid', $learnerrole, ['id' => $fee->id]);
    echo "fee enrolment instance {$fee->id}: roleid 0 -> {$learnerrole} (employee)\n";
}
set_config('course_price_' . $priced->id, '500', 'local_sentientia_catalog');

// One Page activity in the free /1 course.
$pagebody = '<p>VP journey page body text</p>';
$pagemodule = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
$pagecm = $DB->get_record_sql(
    "SELECT cm.id FROM {course_modules} cm
      WHERE cm.course = :c AND cm.module = :m AND cm.deletioninprogress = 0 ORDER BY cm.id",
    ['c' => $free1->id, 'm' => $pagemodule->id], IGNORE_MULTIPLE);
if (!$pagecm) {
    course_create_sections_if_missing($free1, 1);
    $mi = new stdClass();
    $mi->modulename = 'page';
    $mi->module = $pagemodule->id;
    $mi->course = $free1->id;
    $mi->section = 1;
    $mi->visible = 1;
    $mi->visibleoncoursepage = 1;
    $mi->name = 'VP Journey Page';
    $mi->intro = '<p>Persona-journey harness page.</p>';
    $mi->introformat = FORMAT_HTML;
    // page_add_instance() only reads ->page when it is given a form object, and add_moduleinfo()
    // passes none, so the body would be saved as NULL. Set the stored columns directly; ->page
    // stays for callers that do pass a form.
    $mi->content = $pagebody;
    $mi->contentformat = FORMAT_HTML;
    $mi->page = ['text' => $pagebody, 'format' => FORMAT_HTML, 'itemid' => 0];
    $mi->display = RESOURCELIB_DISPLAY_OPEN;
    $mi->printheading = 1;
    $mi->printintro = 0;
    $mi->printlastmodified = 1;
    $mi->popupwidth = 620;
    $mi->popupheight = 450;
    $mi->cmidnumber = '';
    $mi->groupmode = 0;
    $mi->groupingid = 0;
    $mi->completion = 0;
    $mi = add_moduleinfo($mi, $free1);
    $pagecmid = (int) $mi->coursemodule;
    echo "created Page activity cm {$pagecmid} in {$free1->shortname}\n";
} else {
    $pagecmid = (int) $pagecm->id;
    echo "Page activity cm {$pagecmid} already exists\n";
    // An earlier run saved the body as NULL (see above): fill it in, only when it is empty.
    $pageid = (int) $DB->get_field('course_modules', 'instance', ['id' => $pagecmid]);
    $pagerow = $pageid ? $DB->get_record('page', ['id' => $pageid], 'id, content, contentformat') : false;
    if ($pagerow && ($pagerow->content === null || trim((string) $pagerow->content) === '')) {
        $DB->update_record('page', (object) [
            'id' => $pagerow->id, 'content' => $pagebody, 'contentformat' => FORMAT_HTML, 'timemodified' => time(),
        ]);
        echo "Page {$pagerow->id}: empty content filled in\n";
    }
}

// vp_author1 is the trainer of the free /1 course: the gradebook step needs a
// course-level teacher role (the trainer role is assignable at course level).
$author = $DB->get_record('user', ['username' => 'vp_author1', 'deleted' => 0]);
$trainerrole = $DB->get_field('role', 'id', ['shortname' => 'trainer']);
if ($author && $trainerrole) {
    $coursecontext = context_course::instance($free1->id);
    if (!is_enrolled($coursecontext, $author->id)) {
        $manual = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $free1->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $manual->enrol_user($instance, $author->id, (int) $trainerrole);
        echo "enrolled vp_author1 as trainer in {$free1->shortname}\n";
    }
}

// One mock authoring draft owned by vp_courseauthor1 (/1). review.php needs ?draftid= and the
// authoring index only links drafts that exist, so the "review queue" step needs one. It is
// built through the plugin's own draft_manager (create_pending + persist_generation) from
// static text: no AI call, no spend; status "generated", i.e. waiting for a human review.
// Reused by title on re-runs; never deleted.
$draftid = 0;
$courseauthor = $DB->get_record('user', ['username' => 'vp_courseauthor1', 'deleted' => 0]);
if ($courseauthor && $dbman->table_exists('local_sentientia_auth_draft')
        && class_exists('\\local_sentientia_authoring\\draft_manager')) {
    $existingdraft = $DB->get_record('local_sentientia_auth_draft',
        ['ownerid' => $courseauthor->id, 'title' => 'VP Journey Draft'], 'id', IGNORE_MULTIPLE);
    if ($existingdraft) {
        $draftid = (int) $existingdraft->id;
        echo "Authoring draft {$draftid} already exists\n";
    } else {
        $draftid = \local_sentientia_authoring\draft_manager::create_pending(
            (int) $courseauthor->id, 'VP Journey Draft', 'Static text for the persona-journey harness (no AI call).',
            'prompt', 'en', 'mock', 70);
        \local_sentientia_authoring\draft_manager::persist_generation($draftid, [
            (object) ['cardtype' => 'concept', 'heading' => 'VP journey card',
                'body' => 'Static card body for the persona-journey harness.'],
        ], [
            (object) ['qtype' => 'multichoice', 'qtext' => 'Which option is correct in the VP journey question?',
                'qoptions_json' => json_encode(['The first option', 'The second option', 'The third option', 'The fourth option']),
                'qanswer' => '0', 'qfeedback_correct' => 'Correct.', 'qfeedback_incorrect' => 'Not quite.',
                'qexplanation' => 'Static explanation for the persona-journey harness.'],
        ], 0, 0, 'mock');
        echo "created mock authoring draft {$draftid} for vp_courseauthor1\n";
    }
} else {
    echo "authoring draft not seeded (vp_courseauthor1 or the authoring plugin tables are missing)\n";
}

// Feature-flag state per tenant, for the journeys that gate on a flag.
$flagkeys = [
    'live.enabled', 'sentientia.authoring.enabled', 'sentientia.authoring.publish.enabled',
    'sentientia.aiquiz.enabled', 'sentientia.aiquiz.live_api', 'sentientia.ai.gateway.enabled',
    'sentientia.catalog.free_oneclick_enrol.enabled', 'sentientia.catalog.public_lxp.enabled',
    'sentientia.translate.enabled', 'sentientia.pwa.enabled', 'sentientia.lifecycle.autoenrol.enabled',
];
$flags = [];
foreach ($flagkeys as $key) {
    foreach ([1, 77, 177] as $tenant) {
        try {
            $flags[$key][$tenant] = \local_sentientia_platform\feature_flags::is_enabled_for_tenant($key, $tenant);
        } catch (\Throwable $e) {
            $flags[$key][$tenant] = null;
        }
    }
}

// The must-not-leak list: visible /1 courses seen by no other tenant.
$plain = fn(string $s): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8')));
$shared = [];
if ($dbman->table_exists('local_sentientia_courses_tenant_share')) {
    $shared = $DB->get_fieldset_select('local_sentientia_courses_tenant_share', 'DISTINCT courseid', "status = 'active'");
    $shared = array_flip(array_map('intval', $shared));
}
$tenant1 = [];
$others = [];
foreach ($DB->get_recordset_select('course', 'id > 1 AND visible = 1', null, 'id', 'id, fullname, open_path') as $c) {
    $root = explode('/', trim((string) $c->open_path, '/'))[0] ?? '';
    $name = mb_strtolower($plain($c->fullname));
    if ($root === '1') {
        if (!isset($shared[(int) $c->id])) {
            $tenant1[$name] = $plain($c->fullname);
        }
    } else {
        $others[] = $name;
    }
}
$oneonly = [];
foreach ($tenant1 as $lower => $name) {
    if (mb_strlen($name) < 12) {
        continue;
    }
    $clash = false;
    foreach ($others as $other) {
        if ($other === $lower || strpos($other, $lower) !== false) {
            $clash = true;
            break;
        }
    }
    if (!$clash) {
        $oneonly[] = $name;
    }
}
sort($oneonly);

$ctx = [
    'generated' => gmdate('c'),
    'wwwroot' => $CFG->wwwroot,
    'courses' => [
        'free1' => ['id' => (int) $free1->id, 'fullname' => $free1->fullname, 'pagecmid' => $pagecmid],
        'zeeaFree' => ['id' => (int) $zeea->id, 'fullname' => $zeea->fullname],
        'publicPriced' => ['id' => (int) $priced->id, 'fullname' => $priced->fullname, 'price' => 500],
    ],
    'authoring' => ['draftid' => $draftid, 'title' => 'VP Journey Draft', 'owner' => 'vp_courseauthor1'],
    'flags' => $flags,
    'oneOnlyCourseNames' => $oneonly,
];
$file = __DIR__ . '/.journey-context.local.json';
file_put_contents($file, json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo 'Context written to ' . basename($file) . ' (gitignored): free1=' . $free1->id . ' zeeaFree=' . $zeea->id
    . ' publicPriced=' . $priced->id . ' pagecm=' . $pagecmid . ' authoringDraft=' . $draftid
    . ' oneOnlyCourseNames=' . count($oneonly) . "\n";
