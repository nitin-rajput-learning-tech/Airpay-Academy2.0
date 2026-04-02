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
 * @package BizLMS
 * @subpackage block_userdashboard
 */

global $DB, $OUTPUT, $USER, $CFG, $PAGE;
require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
use core_component;
require_once $CFG->dirroot . '/local/includes.php';

class block_userdashboard_renderer extends plugin_renderer_base {
	public function userdashboard_view() {
		global $DB, $PAGE, $USER, $CFG, $OUTPUT;
		// $core_component = new core_component();
		// $coures_plugin_exist = $core_component::get_plugin_directory('local', 'courses');
  //       $classroom_plugin_exist = $core_component::get_plugin_directory('local', 'classroom');
  //       $program_plugin_exist = $core_component::get_plugin_directory('local', 'program');
  //       $certification_plugin_exist = $core_component::get_plugin_directory('local', 'certification');
  //       $learningplan_plugin_exist = $core_component::get_plugin_directory('local', 'learningplan');
  //       $evaluation_plugin_exist = $core_component::get_plugin_directory('local', 'evaluation');
  //       $onlinetests_plugin_exist = $core_component::get_plugin_directory('local', 'onlinetests');

		$clientProps=array('screen.width');

		// if(! isset($_POST['screenwidth'])){
		//     echo "<form method='POST' id='data' style='display:none'>";
		//     foreach($clientProps as $p) {  //create hidden form
		//       echo "<input type='text' id='".str_replace('.','',$p)."' name='".str_replace('.','',$p)."'>";
		//     }
		//     echo "<input type='submit'></form>";

		//     echo "<script>";
		//     foreach($clientProps as $p) {  //populate hidden form with screen/window info
		//       echo "document.getElementById('" . str_replace('.','',$p) . "').value = $p;";
		//     }
		//     echo "document.forms.namedItem('data').submit();"; //submit form
		//     echo "</script>";
		// }else{
		//     foreach($clientProps as $p) {
		//      $screen_width  = $_POST[str_replace('.','',$p)];
		//     }
		// }
		// $mobile_view = $desktop_view = false;
		// if($screen_width < 768){
		// 	$mobile_view = true;
		// }else{
		// 	$desktop_view = true;
		// }

		$desktop_view = true;
		
		// $functions = get_plugin_list_with_function('local', 'userdashboard_menu_content', 'lib.php');
		$menulinks = array();
		$local_pluginlist = \core_component::get_plugin_list('local');
		foreach($local_pluginlist as $key => $local_pluginname){
			$classname = '\\local_'.$key.'\\local\\userdashboard_content';
			if(class_exists($classname)){
				$class = new $classname($DB);
				if(method_exists($class, 'userdashboard_menu_content')){
					$content = $class->userdashboard_menu_content();
					$menulinks[$content['order'] -1] = $content;
				}
			}
		}
		ksort($menulinks);
		$menulinks = array_values($menulinks);
		$menulinks[0]['active_class'] = 'active_main_tab';
		$content = array('links' => $menulinks,
		'mobile_view' => $mobile_view,
		'desktop_view' => $desktop_view);
		return $this->render_from_template('block_userdashboard/menulinks', $content);
		// $content = [
  //           'coures_plugin_exist' => $coures_plugin_exist,
  //           'classroom_plugin_exist' => $classroom_plugin_exist,
  //           'program_plugin_exist' =>$program_plugin_exist,
  //           'certification_plugin_exist'=>$certification_plugin_exist,
  //           'learningplan_plugin_exist'=>$learningplan_plugin_exist,
  //           'evaluation_plugin_exist'=>$evaluation_plugin_exist,
  //           'onlinetests_plugin_exist'=>$onlinetests_plugin_exist,
  //           'desktop_view' => $desktop_view,
  //           'mobile_view' => $mobile_view
  //       ];

		// return $this->render_from_template('block_userdashboard/tabslist', $content);
	}
    


    public function render_manage_elearning_courses(\block_userdashboard\output\elearning_courses $page) {	  
        $data = $page->export_for_template($this);           
        $data->inprogress_elearning=json_decode($data->inprogress_elearning, true); 
        return parent::render_from_template('block_userdashboard/userdashboard_courses', $data);

	} 

	public function dashboard_for_endusers($courses, $curr_tab){

    	switch ($courses) {
		/******This case is for the e-learning tabs to view By Ravi_369******/
		case 'elearning_courses':   

		        $page = new \block_userdashboard\output\elearning_courses('inprogress','', 0, 2);
					return $this->render_manage_elearning_courses($page);
			break;

        }// end of switch

	} // end of function
}
