<?php
namespace local_courses\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'elearning_courses';
		$returndata['order'] = 1;
		$returndata['pluginname'] = 'local_courses';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'fa fa-book';
		$returndata['label'] = get_string('elearning', 'block_userdashboard');
		$returndata['templatename'] = 'local_courses/userdashboard_content';
		return $returndata;
	}
}