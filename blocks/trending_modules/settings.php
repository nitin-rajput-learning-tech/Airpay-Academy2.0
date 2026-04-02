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
 * Settings for the HTML block
 *
 * @copyright 2012 Aaron Barnes
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   block_html
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Default low scores.
    $setting = new admin_setting_configtext('block_trending_modules/block_title',
        new lang_string('custom_block_title', 'block_trending_modules'),
        new lang_string('custom_block_title_desc', 'block_trending_modules'), get_string('pluginname', 'block_trending_modules'), PARAM_TEXT);
    $settings->add($setting);

    // Default group display.
    // $types = array('trending_modules' => get_string('trending_modules', 'block_trending_modules'), 
    //         'suggested_modules' => get_string('suggested_modules', 'block_trending_modules'), 
    //         'both' => get_string('all')
    //     );
    // $setting = new admin_setting_configselect('block_trending_modules/modules_type',
    //     new lang_string('custom_modules_toshow', 'block_trending_modules'),
    //     new lang_string('custom_modules_toshow_desc', 'block_trending_modules'), 'both', $types);
    // $settings->add($setting);
    $settings->add(new \block_trending_modules\config\custom_int_config('block_trending_modules/rating', 
        get_string('ratings_from', 'block_trending_modules'), 
        get_string('ratings_from_help', 'block_trending_modules'),
        1, 
        PARAM_INT,
        5, $minvalue = 0, $maxvalue = 5));
    // $types = array('week' => 'Weekly', 'month' => 'Monthly', 'overall' => 'Overall');
    // $setting = new admin_setting_configselect('block_trending_modules/frequency',
    //     new lang_string('custom_modules_toshow', 'block_trending_modules'),
    //     new lang_string('custom_modules_toshow_desc', 'block_trending_modules'), 'both', $types);
    // $settings->add($setting);
    $settings->add(new \block_trending_modules\config\custom_int_config('block_trending_modules/minenrollments', 
        get_string('min_enrollments', 'block_trending_modules'), 
        get_string('min_enrollments_help', 'block_trending_modules'),
        10, 
        PARAM_INT, $minvalue = 0, $maxvalue = 0));
    $settings->add(new \block_trending_modules\config\custom_int_config('block_trending_modules/mincompletions', 
        get_string('min_completions', 'block_trending_modules'), 
        get_string('min_completions_help', 'block_trending_modules'),
        10, 
        PARAM_INT, $minvalue = 0, $maxvalue = 0));
}