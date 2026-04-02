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
$local_users = new admin_category('local_users', new lang_string('pluginname', 'local_users'),false);
//$ADMIN->add('localsettings', $local_users);
$settings = new admin_settingpage('local_users', get_string('pluginname', 'local_users'));
$ADMIN->add('localplugins', $settings);
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext('local_users/privacypolicy',get_string('privacypolicy', 'local_users'),get_string('privacypolicy', 'local_users'),
            "None", PARAM_RAW ));

    $settings->add(new admin_setting_configtext('local_users/termscondition',get_string('termscondition', 'local_users'),get_string('termscondition', 'local_users'),
            "None", PARAM_RAW ));

    $settings->add(new admin_setting_configtext('local_users/organization_shortname',get_string('organization_shortname', 'local_users'),get_string('organization_shortname', 'local_users'),
            "None", PARAM_RAW ));

    $settings->add(new admin_setting_configcheckbox('local_users/activeregistration',
        new lang_string('activeregistration','local_users'),
        new lang_string('activeregistration', 'local_users'), 1));
}
