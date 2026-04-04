<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A two dashboard layout for the epsilon theme.
 *
 * @package   theme_airpayux
 * @copyright 2018 eAbyas Info Solutons Pvt Ltd, India
 * @author    eAbyas  <info@eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

user_preference_allow_ajax_update('drawer-open-nav', PARAM_ALPHA);
require_once($CFG->libdir . '/behat/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

$extraclasses = [];
$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
$PAGE->set_secondary_navigation(false);
$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions()  && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$layerone_detail_full = $OUTPUT->blocks('layerone_full', 'col-md-12');
$layerone_detail_one = $OUTPUT->blocks('layerone_one', 'col-md-7 float-left');
$layerone_detail_two = $OUTPUT->blocks('layerone_two', 'col-md-5 float-left');

$layertwo_detail_one = $OUTPUT->blocks('layertwo_one', 'col-md-12');
$layertwo_detail_two = $OUTPUT->blocks('layertwo_two', 'col-md-12');
$layertwo_detail_three = $OUTPUT->blocks('layertwo_three', 'col-md-6 float-left');
$layertwo_detail_four = $OUTPUT->blocks('layertwo_four', 'col-md-6 float-left');

$layertwo_three_one = $OUTPUT->blocks('layerthree_one', 'col-md-12');
$layertwo_three_two = $OUTPUT->blocks('layerthree_two', 'col-md-12');

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);
$OUTPUT->seteditswtich_display(true);

// ═══════════════════════════════════════════════════════════
// Airpay Academy UX — Dashboard data injection (Sprint 3)
// ═══════════════════════════════════════════════════════════

$airpay_dashboard = [];
if (isloggedin() && !isguestuser()) {
    global $DB, $USER;

    // Get user's first name for greeting
    $airpay_dashboard['firstname'] = $USER->firstname ?? 'Learner';

    // Get enrolled courses with progress
    try {
        $enrolledcourses = enrol_get_all_users_courses($USER->id, true);
        $completed = 0;
        $inprogress = 0;
        $notstarted = 0;
        $continuecourses = [];

        foreach ($enrolledcourses as $course) {
            $completion = new completion_info($course);
            $progress = \core_completion\progress::get_course_progress_percentage($course, $USER->id);

            if ($progress !== null && $progress >= 100) {
                $completed++;
            } else if ($progress !== null && $progress > 0) {
                $inprogress++;
                // Add to "Continue Learning" list
                $continuecourses[] = [
                    'id' => $course->id,
                    'fullname' => format_string($course->fullname),
                    'shortname' => format_string($course->shortname),
                    'progress' => round($progress),
                    'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                ];
            } else {
                $notstarted++;
                // Also show recently enrolled not-started courses
                if (count($continuecourses) < 6) {
                    $continuecourses[] = [
                        'id' => $course->id,
                        'fullname' => format_string($course->fullname),
                        'shortname' => format_string($course->shortname),
                        'progress' => 0,
                        'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    ];
                }
            }
        }

        // Limit to 6 courses for the dashboard
        $airpay_dashboard['continuecourses'] = array_slice($continuecourses, 0, 6);
        $airpay_dashboard['hascontinuecourses'] = count($continuecourses) > 0;
        $airpay_dashboard['stats'] = [
            'enrolled' => count($enrolledcourses),
            'inprogress' => $inprogress,
            'completed' => $completed,
            'notstarted' => $notstarted,
        ];
        $airpay_dashboard['hasstats'] = true;
    } catch (Exception $e) {
        // Silently fail — dashboard still renders without our additions
        $airpay_dashboard['hasstats'] = false;
        $airpay_dashboard['hascontinuecourses'] = false;
    }

    // Get certificate count (if available)
    try {
        $certcount = $DB->count_records('tool_certificate_issues', ['userid' => $USER->id]);
        $airpay_dashboard['stats']['certificates'] = $certcount;
    } catch (Exception $e) {
        $airpay_dashboard['stats']['certificates'] = 0;
    }

    // --- Section: Upcoming Deadlines ---
    try {
        $deadlines = [];
        $now = time();
        $enrolledids = array_keys($enrolledcourses ?? []);
        if (!empty($enrolledids)) {
            [$insql, $params] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'cid');
            $params['uid'] = $USER->id;
            $params['now'] = $now;
            $deadlinerecords = $DB->get_records_sql(
                "SELECT c.id, c.fullname, c.shortname, c.enddate
                   FROM {course} c
                  WHERE c.id $insql
                    AND c.enddate > :now
                    AND c.id NOT IN (
                        SELECT cc.course FROM {course_completions} cc
                         WHERE cc.userid = :uid AND cc.timecompleted IS NOT NULL
                    )
               ORDER BY c.enddate ASC",
                $params, 0, 5
            );
            foreach ($deadlinerecords as $dl) {
                $deadlines[] = [
                    'coursename' => format_string($dl->fullname),
                    'duedate' => userdate($dl->enddate, get_string('strftimedatefull')),
                    'duetimestamp' => $dl->enddate,
                    'urgent' => ($dl->enddate - $now) < (7 * 86400),
                    'viewurl' => (new moodle_url('/course/view.php', ['id' => $dl->id]))->out(false),
                ];
            }
        }
        $airpay_dashboard['deadlines'] = $deadlines;
        $airpay_dashboard['hasdeadlines'] = count($deadlines) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hasdeadlines'] = false;
    }

    // --- Section: Recent Achievements (badges + certificates) ---
    try {
        $achievements = [];
        // Badges
        if (function_exists('badges_get_user_badges')) {
            $badges = badges_get_user_badges($USER->id, 0, 0, 5);
            foreach ($badges as $badge) {
                $achievements[] = [
                    'title' => format_string($badge->name),
                    'description' => format_string($badge->description),
                    'date' => userdate($badge->dateissued, get_string('strftimedatefull')),
                    'timestamp' => $badge->dateissued,
                    'type' => 'badge',
                    'icon' => 'star',
                ];
            }
        }
        // Certificates
        $certs = $DB->get_records_sql(
            "SELECT ci.id, ci.timecreated, ci.code, ct.name as templatename, c.fullname as coursename
               FROM {tool_certificate_issues} ci
          LEFT JOIN {tool_certificate_templates} ct ON ct.id = ci.templateid
          LEFT JOIN {course} c ON c.id = ci.courseid
              WHERE ci.userid = :uid AND ci.archived = 0
           ORDER BY ci.timecreated DESC",
            ['uid' => $USER->id], 0, 5
        );
        foreach ($certs as $cert) {
            $achievements[] = [
                'title' => format_string($cert->coursename ?? $cert->templatename ?? 'Certificate'),
                'description' => 'Certificate earned — Code: ' . s($cert->code),
                'date' => userdate($cert->timecreated, get_string('strftimedatefull')),
                'timestamp' => $cert->timecreated,
                'type' => 'certificate',
                'icon' => 'certificate',
            ];
        }
        // Sort by timestamp descending
        usort($achievements, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
        $airpay_dashboard['achievements'] = array_slice($achievements, 0, 5);
        $airpay_dashboard['hasachievements'] = count($achievements) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hasachievements'] = false;
    }

    // --- Section: Activity Timeline ---
    try {
        $timeline = [];
        $logs = $DB->get_records_sql(
            "SELECT id, eventname, timecreated, other, courseid
               FROM {logstore_standard_log}
              WHERE userid = :uid
                AND eventname IN (
                    '\\\\core\\\\event\\\\course_completed',
                    '\\\\core\\\\event\\\\user_enrolment_created',
                    '\\\\core\\\\event\\\\badge_awarded',
                    '\\\\mod_quiz\\\\event\\\\attempt_submitted'
                )
           ORDER BY timecreated DESC",
            ['uid' => $USER->id], 0, 15
        );
        foreach ($logs as $log) {
            $coursename = '';
            if ($log->courseid > 1) {
                $coursename = $DB->get_field('course', 'fullname', ['id' => $log->courseid]);
            }
            $label = '';
            switch ($log->eventname) {
                case '\\core\\event\\course_completed':
                    $label = 'Completed ' . format_string($coursename);
                    break;
                case '\\core\\event\\user_enrolment_created':
                    $label = 'Enrolled in ' . format_string($coursename);
                    break;
                case '\\core\\event\\badge_awarded':
                    $label = 'Earned a badge';
                    break;
                case '\\mod_quiz\\event\\attempt_submitted':
                    $label = 'Submitted quiz in ' . format_string($coursename);
                    break;
                default:
                    $label = 'Activity recorded';
            }
            $timeline[] = [
                'label' => $label,
                'date' => userdate($log->timecreated, '%b %d'),
                'fulldate' => userdate($log->timecreated, get_string('strftimedatefull')),
                'istoday' => (date('Ymd', $log->timecreated) === date('Ymd')),
            ];
        }
        $airpay_dashboard['timeline'] = $timeline;
        $airpay_dashboard['hastimeline'] = count($timeline) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hastimeline'] = false;
    }

    // --- Section: Recommended for You ---
    try {
        $recommendations = [];
        $enrolledids = array_keys($enrolledcourses ?? []);
        if (!empty($enrolledids)) {
            // Get categories of enrolled courses
            $categories = $DB->get_fieldset_sql(
                "SELECT DISTINCT category FROM {course} WHERE id IN (" .
                implode(',', array_map('intval', $enrolledids)) . ")"
            );
            if (!empty($categories)) {
                [$catsql, $catparams] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
                [$exsql, $exparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
                $params = array_merge($catparams, $exparams);
                $recs = $DB->get_records_sql(
                    "SELECT c.id, c.fullname, c.summary, c.category
                       FROM {course} c
                      WHERE c.category $catsql
                        AND c.id $exsql
                        AND c.visible = 1 AND c.id > 1
                   ORDER BY c.timecreated DESC",
                    $params, 0, 3
                );
                foreach ($recs as $rec) {
                    $catname = $DB->get_field('course_categories', 'name', ['id' => $rec->category]);
                    $recommendations[] = [
                        'id' => $rec->id,
                        'fullname' => format_string($rec->fullname),
                        'summary' => shorten_text(strip_tags(format_string($rec->summary)), 80),
                        'category' => format_string($catname),
                        'viewurl' => (new moodle_url('/course/view.php', ['id' => $rec->id]))->out(false),
                    ];
                }
            }
        }
        $airpay_dashboard['recommendations'] = $recommendations;
        $airpay_dashboard['hasrecommendations'] = count($recommendations) > 0;
    } catch (Exception $e) {
        $airpay_dashboard['hasrecommendations'] = false;
    }
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'layerone_detail_full' => $layerone_detail_full,
    'layerone_detail_one' => $layerone_detail_one,
    'layerone_detail_two' => $layerone_detail_two,
    'layertwo_detail_one' => $layertwo_detail_one,
    'layertwo_detail_two' => $layertwo_detail_two,
    'layertwo_detail_three' => $layertwo_detail_three,
    'layertwo_detail_four' => $layertwo_detail_four,
    'layerone_bottom_one' => $layertwo_three_one,
    'layerone_bottom_two' => $layertwo_three_two,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'headercontent' => $headercontent,
    'overflow' => $overflow,
    'isloggedin' => isloggedin(),
    'addblockbutton' => $addblockbutton,
    // Airpay UX dashboard data
    'airpay' => $airpay_dashboard ?? [],
];

echo $OUTPUT->render_from_template('theme_airpayux/dashboard', $templatecontext);
