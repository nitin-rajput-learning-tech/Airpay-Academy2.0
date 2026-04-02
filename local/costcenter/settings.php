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
$local_costcenter = new admin_category('local_costcenter', new lang_string('pluginname', 'local_costcenter'),false);
//$ADMIN->add('localsettings', $local_costcenter);
$settings = new admin_settingpage('local_costcenter', get_string('pluginname', 'local_costcenter'));
$ADMIN->add('localplugins', $settings);
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('local_costcenter/nooflevel',
            get_string('nooflevel', 'local_costcenter'),
            get_string('confignooflevel', 'local_costcenter'),
            5, PARAM_INT ));
}