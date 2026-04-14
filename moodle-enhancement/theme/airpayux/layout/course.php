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
 * A drawer based layout for the epsilon theme.
 *
 * @package   theme_airpayux
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

user_preference_allow_ajax_update('drawer-open-nav', PARAM_ALPHA);
user_preference_allow_ajax_update('drawer-open-index', PARAM_BOOL);
user_preference_allow_ajax_update('drawer-open-block', PARAM_BOOL);

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING')) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$PAGE->set_secondary_navigation(false);
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
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'isloggedin' => isloggedin(),
];

// ═══ AIRPAY COURSE PLAYER ENHANCEMENTS ═══
// When viewing a course activity (SCORM, quiz, etc.), inject:
// 1. Sticky progress bar at top
// 2. Course context info (breadcrumb, progress, next activity)
$courseid = $PAGE->course->id;
if ($courseid > 1 && isloggedin() && !is_siteadmin()) {
    try {
        $courseobj = get_course($courseid);
        $modinfo = get_fast_modinfo($courseobj);
        $totalactivities = 0;
        $completedactivities = 0;
        $completion = new completion_info($courseobj);

        if ($completion->is_enabled()) {
            foreach ($modinfo->cms as $cminfo) {
                if (!$cminfo->visible || !$cminfo->uservisible) continue;
                $completiondata = $completion->get_data($cminfo, true, $USER->id);
                if ($cminfo->completion != COMPLETION_TRACKING_NONE) {
                    $totalactivities++;
                    if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                        $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $completedactivities++;
                    }
                }
            }
        }

        $courseprogress = ($totalactivities > 0) ? round(($completedactivities / $totalactivities) * 100) : 0;

        // Find current activity and next activity.
        $currentcmid = optional_param('id', 0, PARAM_INT);
        $nextactivity = null;
        $foundcurrent = false;
        if ($currentcmid) {
            foreach ($modinfo->cms as $cminfo) {
                if (!$cminfo->visible || !$cminfo->uservisible) continue;
                if ($foundcurrent && $cminfo->url) {
                    $nextactivity = [
                        'name' => format_string($cminfo->name),
                        'url'  => $cminfo->url->out(false),
                    ];
                    break;
                }
                if ($cminfo->id == $currentcmid) {
                    $foundcurrent = true;
                }
            }
        }

        $templatecontext['ap_course_progress'] = $courseprogress;
        $templatecontext['ap_course_total'] = $totalactivities;
        $templatecontext['ap_course_completed'] = $completedactivities;
        $templatecontext['ap_course_name'] = format_string($courseobj->fullname);
        $templatecontext['ap_course_url'] = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
        $templatecontext['ap_has_next'] = !empty($nextactivity);
        $templatecontext['ap_next_name'] = $nextactivity['name'] ?? '';
        $templatecontext['ap_next_url'] = $nextactivity['url'] ?? '';
        $templatecontext['ap_has_progress'] = ($totalactivities > 0);
    } catch (Exception $e) {
        // Non-fatal — layout renders without enhancements.
    }
}

echo $OUTPUT->render_from_template('theme_airpayux/course', $templatecontext);
