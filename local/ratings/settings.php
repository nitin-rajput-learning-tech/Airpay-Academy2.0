<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 */
defined('MOODLE_INTERNAL') || die;
$local_rating = new admin_category('local_ratings', new lang_string('pluginname', 'local_ratings'),false);
//$ADMIN->add('localsettings', $local_rating);
$settings = new admin_settingpage('local_ratings', get_string('pluginname', 'local_ratings'));
$ADMIN->add('localplugins', $settings);
if ($ADMIN->fulltree) {
    $settings->add((new admin_setting_configselect('local_ratings/review_enable',
            get_string('enable_reviews', 'local_ratings'), get_string('configlocal_review_help', 'local_ratings'),
            0, [
                0 => get_string('no'),
                1 => get_string('yes')
            ])));
}
