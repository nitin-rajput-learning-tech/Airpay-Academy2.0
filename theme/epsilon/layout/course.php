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
 * A two column layout for the epsilon theme.
 *
 * @package   theme_epsilon
 * @copyright 2018 eAbyas Info Solutons Pvt Ltd, India
 * @author    eAbyas  <info@eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// user_preference_allow_ajax_update('drawer-open-nav', PARAM_ALPHA);
require_once($CFG->libdir . '/behat/lib.php');
$core_component = new core_component();
/*$ratings_plugin_exist = $core_component::get_plugin_directory('local', 'ratings');
if($ratings_plugin_exist){
    global $PAGE;
    $PAGE->requires->jquery();
    $PAGE->requires->js('/local/ratings/js/jquery.rateyo.js');
    $PAGE->requires->js('/local/ratings/js/ratings.js');
}*/
if (isloggedin()) {
    $navdraweropen = false;//(get_user_preferences('drawer-open-nav', 'true') == 'true');
} else {
    $navdraweropen = false;
}
$extraclasses = [];
if ($navdraweropen) {
    $extraclasses[] = 'drawer-open-left';
}

//$schemename = $OUTPUT->get_my_scheme();
// if(!empty($schemename)){
//     $extraclasses[] = $schemename;
// }

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = strpos($blockshtml, 'data-block=') !== false;
$regionmainsettingsmenu = $OUTPUT->region_main_settings_menu();

$coursepre_blockshtml = $OUTPUT->blocks('course-pre');
$has_coursepre_blocks = strpos($coursepre_blockshtml, 'data-block=') !== false;

// $fontpath = $OUTPUT->get_font_path();
$is_loggedin = isloggedin();
$is_loggedin = empty($is_loggedin) ? false : true;

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'coursepre_blockshtml' => $coursepre_blockshtml,
    'has_coursepre_blocks' => $has_coursepre_blocks,
    'bodyattributes' => $bodyattributes,
    'navdraweropen' => $navdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'isloggedin' => $is_loggedin,
];

$templatecontext['flatnavigation'] = $PAGE->flatnav;
echo $OUTPUT->render_from_template('theme_epsilon/course', $templatecontext);

