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
 * Airpay Academy UX theme configuration.
 *
 * Child theme of epsilon. Inherits ALL layouts, block regions, renderers,
 * and SCSS pipeline from epsilon. Only overrides what we explicitly change.
 *
 * ARCHITECTURE NOTE:
 * Epsilon declares $THEME->parents = [] — it is a standalone theme that
 * ships its own copies of Bootstrap 5 and Moodle SCSS. When we set
 * $THEME->parents = ['epsilon'], Moodle's theme resolution will:
 *   1. Look for templates in theme/airpayux/templates/ first
 *   2. Fall back to theme/epsilon/templates/ for anything not overridden
 *   3. Fall back to core templates last
 * This gives us surgical override capability without forking epsilon.
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$THEME->name = 'airpayux';

// Inherit everything from epsilon — layouts, block regions, renderers, SCSS.
$THEME->parents = ['epsilon'];

// No additional stylesheets — we use SCSS only.
$THEME->sheets = [];
$THEME->editor_sheets = [];

// SCSS: Use epsilon's main SCSS content, then layer our overrides via callbacks.
$THEME->scss = function($theme) {
    // Start with epsilon's full SCSS (Bootstrap 5 + Moodle + BizLMS custom).
    // Note: Epsilon's fontawesome.scss references fontawesome-webfont.* files
    // which didn't exist in Moodle 4.5 (FA6). We fixed this by copying the
    // fa-v4compatibility.* fonts to the old filenames in lib/fonts/.
    return theme_epsilon_get_main_scss_content($theme);
};

// Pre-SCSS: Inject our design system tokens BEFORE epsilon's variables.
$THEME->prescsscallback = 'theme_airpayux_get_pre_scss';

// Extra SCSS: Inject our component overrides AFTER epsilon's styles.
$THEME->extrascsscallback = 'theme_airpayux_get_extra_scss';

// Inherit epsilon's icon system, edit switch, course index, and activity header config.
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->haseditswitch = true;
$THEME->usescourseindex = true;
$THEME->activityheaderconfig = [
    'notitle' => true,
];

// Use overridden renderers (inherits epsilon's renderer chain).
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

// Block configuration (same as epsilon).
$THEME->requiredblocks = '';
$THEME->addblockposition = BLOCK_ADDBLOCK_POSITION_FLATNAV;

// No YUI modules.
$THEME->yuicssmodules = [];
$THEME->enable_dock = false;
