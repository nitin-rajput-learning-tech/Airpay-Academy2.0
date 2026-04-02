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
 * @package Bizlms 
 * @subpackage local_classroom
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_classroom', get_string('pluginname', 'local_classroom'));
   

    $ADMIN->add('localplugins', $settings);
    
    $menu = array(null=>new lang_string('select_session_type', 'local_classroom'));
    foreach (core_component::get_plugin_list('mod') as $type => $notused) {
              
        if($type=='wiziq'||$type=='bigbluebuttonbn'||$type=='webexactivity'){
        
            $visible =$DB->get_field('modules','visible',array('name'=>$type));
            
            if ($visible) {
                $menu['mod_' . $type] = $type;
            }
        }
        
    }
    //print_object($menu);
    $test = '';
    if ($menu) {
        $name = new lang_string('online_session_type', 'local_classroom');
        $description = new lang_string('online_session_type_desc', 'local_classroom');
        $settings->add(new admin_setting_configselect('local_classroom/classroom_onlinesession_type',
                                                      $name,
                                                      $description,
                                                      'online_session_type_comments',
                                                      $menu));
    }else{
        $name = new lang_string('online_session_type', 'local_classroom');
        $description = new lang_string('online_session_plugin_info', 'local_classroom');
        $setting = new admin_setting_configempty('local_classroom/classroom_onlinesession_type',
                                                 $name,
                                                 $description);
        $settings->add($setting);               
    }
    
}

