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
 * @author Maheshchandra  <maheshchandra@eabyas.in>
 */
/**
 * Assign roles to users.
 * @package    local
 * @subpackage assignroles
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_assignroles\output;
class renderer extends \plugin_renderer_base{

	/**
	 * function to display the site level roles in table format
	 * returns HTML data of table
	 */
	public function display_roles($context){
		
		$assignrole = new \local_assignroles\local\assignrole();
		list($assignableroles, $assigncounts, $nameswithcounts) = $assignrole->get_assignable_roles($context, ROLENAME_BOTH, true);
		$templatedata = array();
		
		foreach($assignableroles as $roleid => $rolename){
			$rowdata['roleid'] = $roleid;
			$rowdata['rolename'] = $rolename;
			$rowdata['rolecount'] = $assigncounts[$roleid];
			$templatedata['rolerow'][] = $rowdata;
		}
		$templatedata['contextid'] = $context->id;
		if(count($templatedata)>0){
			$templatedata['enable'] = true;
		}else{
			$templatedata['enable'] = false;
			$templatedata['emptycontent'] = get_string('noroles', 'local_assignroles');
		}
		return $this->render_from_template('local_assignroles/indexcontent', $templatedata);
	}
}
