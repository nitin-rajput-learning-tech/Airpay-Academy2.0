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
 * @package   theme_airpayux
 * @copyright 2016 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Front page: redirect ALL users to the Airpay homepage (tenant-scoped) or dashboard.
if ($PAGE->pagelayout === 'frontpage') {
    if (isloggedin() && !isguestuser()) {
        redirect(new moodle_url('/my/'));
    } else {
        // Guests see the Airpay-branded, tenant-scoped homepage — not Moodle frontpage.
        redirect(new moodle_url('/local/airpay_pages/homepage.php'));
    }
}

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
        // Phase B.3.b (2026-05-23) — Moodle 5.2 introduced
        // \core\output\select_menu and boost layouts now route the
        // overflow renderable through it. Detect at runtime so the
        // same code path works on both 5.1 and 5.2.
        if (class_exists('\\core\\output\\select_menu')) {
            $selectmenu = new \core\output\select_menu(
                'tertiarynavigation',
                $overflowdata->urls,
                $overflowdata->selected,
            );
            $selectmenu->set_label($overflowdata->label, $overflowdata->labelattributes);
            $overflow = $selectmenu->export_for_template($OUTPUT);
        } else {
            // 5.1 legacy path.
            $overflow = $overflowdata->export_for_template($OUTPUT);
        }
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions()  && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);
$OUTPUT->seteditswtich_display(false);
$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    //'primarymoremenu' => $primarymenu['moremenu'],
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
];

// Sidebar handled by core_renderer::airpay_shell_start() — no template injection needed.

echo $OUTPUT->render_from_template('theme_airpayux/columns2', $templatecontext);
