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

namespace local_myteam\output;

defined('MOODLE_INTERNAL') || die;
use context_system;
use stdClass;
use core_component;
use html_table;
use html_writer;
use local_myteam\output\team_status_lib;

class myteam{
    
    public function team_status_view() {
		global $CFG, $USER, $PAGE, $DB,$OUTPUT;
		$systemcontext =(new \local_costcenter\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'display_manageteam','perPage' => 5, 'cardClass' => 'tableformat', 'viewType' => 'table');
        
        $options['methodName']='local_myteam_manageteam_view';
        $options['templateName']='local_myteam/teamview'; 
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));

        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'display_manageteam',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];

        return  $OUTPUT->render_from_template('local_costcenter/cardPaginate', $context);
	}

	public function team_members_contentview($stable,$filtervalues) {
		global $CFG, $USER, $PAGE, $DB;
		$systemcontext =(new \local_costcenter\lib\accesslib())::get_module_context();
		$core_component = new core_component();
		$teamstatus = new team_status_lib();
		$local_pluginlist = $core_component::get_plugin_list('local');
		$teammembers = $teamstatus->get_team_members(false,$stable);
		$header = array();
		$header['members'] = get_string('members', 'local_myteam');
		$existingplugins = array();
		$data = array();
		//getting all module enrrolled user data
		foreach($local_pluginlist AS $pluginname => $pluginurl){
			$userclass = '\local_'.$pluginname.'\local\user';
			if(class_exists($userclass)){
				if(method_exists($userclass, 'user_team_content') && method_exists($userclass, 'user_team_headers')){
					$existingplugins[$pluginname] = new $userclass();
					$plugin_headers = $existingplugins[$pluginname]->user_team_headers();
					foreach($plugin_headers AS $name => $pluginheader){
						// $string = get_string('pluginname', 'local_'.$pluginname);
						$header[$name] = $pluginheader;
					}
				}
			}
		}
		$header['badges'] = get_string('badges');
		$data['headers'] = $header;
		if(!empty($teammembers)){
			$dataarray = array();
			foreach($teammembers as $teammember){
				$row = array();
				$pluginsarray = array();
				$row['userid'] = $teammember->id;
				$row['userfullname'] = fullname($teammember);
				foreach($existingplugins as $key => $value){
					$content = $value->user_team_content($teammember);
					if(is_array($content)){
						foreach($content AS $innerkey => $value){
							$row[$innerkey] = $value;
						}
					}else{
						$row[$key] = (array) $content;
					}
				}
				
				$badgecount = $DB->count_records_sql("SELECT count(id) FROM {badge_issued} WHERE userid = :userid",array('userid' => $teammember->id));
				$costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='c.open_path',$costcenterid,'lowerandsamepath');
				$totalbadgesql = "SELECT count(id) FROM {badge} WHERE 1=1 ";
				$totalbadges = $DB->count_records_sql($totalbadgesql);

				$badge_color = $teamstatus->get_colorcode_tm_dashboard($badgecount,$totalbadges);
					
				$row['badgescount'] = array('issuedbadges' => $badgecount,'totalbadges' => $totalbadges,'elementcolor' => $badge_color,'userid' => $teammember->id);
				$dataarray[] = $row;
			}
			$data['data'] = $dataarray;
			$teammembersexist = true;
		} else {
			$teammembersexist = false;
		}
		$data['teammembersexist'] = $teammembersexist;
		return $data;
	}
}
