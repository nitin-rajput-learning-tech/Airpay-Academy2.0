<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Phase 6 F.4 — seed Moodle core badges for Airpay's standard L&D outcomes.
 *
 * Creates 8 starter badges, each tied to a course-completion criterion. Once
 * the linked course is completed, Moodle's badges_cron_task auto-issues the
 * badge (no custom event observer required).
 *
 * Usage:
 *   php cli/seed_badges.php          # idempotent — won't create duplicates
 *   php cli/seed_badges.php --dryrun # print what would happen
 *
 * Run as siteadmin context. Re-running picks up any missing badges or
 * adjusts criteria as needed; existing badges are left alone otherwise.
 *
 * Why CLI seeder vs admin UI: ships the canonical set in code so prod +
 * staging + dev all converge. Admins can add more via Moodle's
 * /badges/index.php and they will NOT be touched by this script.
 *
 * @package local_sentientia_org
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
// awardlib.php was removed in Moodle 5.2 (badges refactor); this script calls
// no function from it — guard so the seed runs on both 5.1 and 5.2 (WF-011).
if (file_exists($CFG->dirroot . '/badges/lib/awardlib.php')) {
    require_once($CFG->dirroot . '/badges/lib/awardlib.php');
}
require_once($CFG->dirroot . '/badges/lib.php');
require_once($CFG->libdir . '/badgeslib.php');

global $DB, $CFG;

$dryrun = in_array('--dryrun', $argv ?? [], true);

// Authenticate as siteadmin so badge creation has permission.
$admin = $DB->get_record_sql(
    "SELECT * FROM {user} WHERE username = 'academy@airpay.co.in' LIMIT 1");
if ($admin) {
    \core\session\manager::set_user($admin);
}

// Standard 8 badges. Each maps to a course shortname (resolved at seed time)
// OR to a description-only badge that admins later add criteria to manually.
$starter_badges = [
    [
        'name'        => 'POSH Compliance',
        'idnumber'    => 'ap_badge_posh',
        'description' => 'Earned by completing the annual Prevention of Sexual Harassment training.',
        'course_shortnames' => ['POSH-2026', 'POSH'],  // try in order
    ],
    [
        'name'        => 'AML/KYC Certified',
        'idnumber'    => 'ap_badge_aml',
        'description' => 'Earned by completing the AML/KYC compliance training.',
        'course_shortnames' => ['AML-2026', 'AML', 'AML-KYC'],
    ],
    [
        'name'        => 'Data Privacy Certified',
        'idnumber'    => 'ap_badge_data_privacy',
        'description' => 'Earned by completing the Data Privacy & GDPR module.',
        'course_shortnames' => ['DP-2026', 'DATA-PRIVACY', 'GDPR'],
    ],
    [
        'name'        => 'New Hire Onboarded',
        'idnumber'    => 'ap_badge_onboarded',
        'description' => 'Awarded on completion of the new-hire onboarding track.',
        'course_shortnames' => ['ONBOARD', 'NEW-HIRE'],
    ],
    [
        'name'        => 'Customer Service Pro',
        'idnumber'    => 'ap_badge_cs_pro',
        'description' => 'Awarded for excellence in customer service training.',
        'course_shortnames' => ['CS-101'],
    ],
    [
        'name'        => 'Quick Learner',
        'idnumber'    => 'ap_badge_quick_learner',
        'description' => 'For learners who complete three courses in seven days.',
        'course_shortnames' => [],  // manual criteria
    ],
    [
        'name'        => 'Compliance Champion',
        'idnumber'    => 'ap_badge_compliance_champ',
        'description' => 'Awarded for completing all 5 mandatory compliance trainings.',
        'course_shortnames' => [],  // manual criteria
    ],
    [
        'name'        => 'Manager Track L1',
        'idnumber'    => 'ap_badge_mgr_l1',
        'description' => 'Foundations of People Management — Level 1.',
        'course_shortnames' => ['MGR-L1'],
    ],
];

$created = 0;
$skipped = 0;
$updated_criteria = 0;
$skipped_no_course = 0;

$context_id = \context_system::instance()->id;

foreach ($starter_badges as $def) {
    // Look up by name (badge table has no idnumber column — site badges
    // use name as the de-facto identifier). Use IGNORE_MULTIPLE to tolerate
    // accidental duplicates from earlier runs.
    $existing = $DB->get_record_select('badge',
        'name = :name AND type = :t',
        ['name' => $def['name'], 't' => BADGE_TYPE_SITE],
        '*', IGNORE_MULTIPLE);

    if ($existing) {
        echo sprintf("  ✓ %-40s exists (id=%d)\n", $def['name'], $existing->id);
        $skipped++;
        $badge_id = $existing->id;
    } else {
        if ($dryrun) {
            echo sprintf("  + would create %-30s [%s]\n", $def['name'],
                implode('|', $def['course_shortnames']) ?: '(manual criteria)');
            $created++;
            continue;
        }
        $b = new \stdClass();
        $b->name        = $def['name'];
        $b->description = $def['description'];
        $b->timecreated = time();
        $b->timemodified = time();
        $b->usercreated = $admin->id ?? 2;
        $b->usermodified = $admin->id ?? 2;
        $b->issuername  = 'Airpay Academy';
        $b->issuerurl   = $CFG->wwwroot;
        $b->issuercontact = 'no-reply@airpay.academy';
        $b->expiredate  = null;
        $b->expireperiod = 0;
        $b->type        = BADGE_TYPE_SITE;
        $b->courseid    = null;
        $b->message     = 'Congratulations! You have earned the ' . $def['name'] . ' badge.';
        $b->messagesubject = 'Badge awarded: ' . $def['name'];
        $b->attachment  = 1;
        $b->notification = 0;
        $b->status      = BADGE_STATUS_ACTIVE;
        $b->nextcron    = time();
        $b->version     = '1.0';
        $b->language    = 'en';
        $b->imageauthorname = '';
        $b->imageauthoremail = '';
        $b->imageauthorurl = '';
        $b->imagecaption = $def['name'];

        $badge_id = $DB->insert_record('badge', $b);
        echo sprintf("  + created badge: %-40s (id=%d)\n", $def['name'], $badge_id);
        $created++;
    }

    // Wire course-completion criterion if shortnames were provided.
    if (!empty($def['course_shortnames']) && !$dryrun) {
        $matched_course = null;
        foreach ($def['course_shortnames'] as $sn) {
            $c = $DB->get_record('course', ['shortname' => $sn]);
            if ($c) { $matched_course = $c; break; }
        }
        if ($matched_course) {
            // Check if criterion already exists.
            $has_crit = $DB->record_exists_select('badge_criteria',
                'badgeid = :bid AND criteriatype = :ct',
                ['bid' => $badge_id, 'ct' => BADGE_CRITERIA_TYPE_COURSESET]);
            if (!$has_crit) {
                $crit = new \stdClass();
                $crit->badgeid       = $badge_id;
                $crit->criteriatype  = BADGE_CRITERIA_TYPE_COURSESET;
                $crit->method        = BADGE_CRITERIA_AGGREGATION_ALL;
                $crit->description   = 'Auto-issued on completing: ' . format_string($matched_course->fullname);
                $crit->descriptionformat = FORMAT_PLAIN;
                $crit_id = $DB->insert_record('badge_criteria', $crit);

                // Parameter row pointing to the course.
                $param = new \stdClass();
                $param->critid = $crit_id;
                $param->name   = 'course_' . $matched_course->id;
                $param->value  = $matched_course->id;
                $DB->insert_record('badge_criteria_param', $param);
                $updated_criteria++;
                echo sprintf("    → linked to course %s (id=%d)\n",
                    $matched_course->shortname, $matched_course->id);
            }
        } else {
            $skipped_no_course++;
            echo "    ! no matching course (" . implode('|', $def['course_shortnames']) . "); criterion left empty\n";
        }
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo sprintf("Result: created=%d existing=%d criteria_linked=%d no_course=%d%s\n",
    $created, $skipped, $updated_criteria, $skipped_no_course,
    $dryrun ? ' (DRY-RUN)' : '');
echo str_repeat('=', 50) . "\n";

exit(0);
