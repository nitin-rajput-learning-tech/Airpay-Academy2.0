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
