<?php
namespace local_forum\local;
class userdashboard_content extends \block_userdashboard\content{
	public function userdashboard_menu_content(){
		$returndata = array();
		$returndata['id'] = 'forum';
		$returndata['order'] = 5;
		$returndata['pluginname'] = 'local_forum';
		$returndata['tabname'] = 'inprogress';
		$returndata['status'] = 'inprogress';
		$returndata['class'] = 'userdashboard_menu_link';
		$returndata['iconclass'] = 'fa fa-desktop ot_icon';
		$returndata['label'] = get_string('forum', 'block_userdashboard');
		$returndata['templatename'] = 'local_forum/userdashboard_content';
		return $returndata;
	}
}