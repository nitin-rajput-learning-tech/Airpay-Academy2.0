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
 * local_costcenter
 *
 * @package    local_costcenter
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_costcenter\functions;
require_once($CFG->dirroot . '/local/costcenter/lib.php');

class userlibfunctions{
	/* find department list
	@param need to pass costcenter value*/
	public function find_departments_list($costcenter){
	   
	    global $DB;
	    if($costcenter) {
		    $sql="select id,fullname from {local_costcenter} ";

		    $costcenters = explode(',',$costcenter);
	        list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($costcenters, SQL_PARAMS_NAMED, 'parentid');
	        $sql .= " where parentid $relatedparentidsql";

		    $sub_dep=$DB->get_records_sql($sql,$relatedparentidparams);

	      	return $sub_dep;
	  	}else {
	  		return $costcenter;
	  	}
	   
	    
	}
	/* find sub department list
	@param need to pass department value*/
	public function find_subdepartments_list($department){
	    global $DB;
	    $sql="select id,fullname from {local_costcenter} ";

	    $departments = explode(',',$department);
	    list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($departments, SQL_PARAMS_NAMED, 'parentid');
	    $sql .= " where parentid $relatedparentidsql";

	    $sub_dep=$DB->get_records_sql($sql,$relatedparentidparams);

	    return $sub_dep;
	}

	/* find supervisors list
	@param need to pass supervisor and userid optional value*/
	public function find_supervisor_list($supervisor,$userid=0){
		global $DB;
	    if($supervisor){
		    $sql="SELECT u.id,Concat(u.firstname,' ',u.lastname) as username from {user} as u where u.suspended = :suspended AND u.deleted = :deleted AND u.open_costcenterid = :costcenterid  AND u.id > 2";
		    if($userid){
		    	$sql .= " AND u.id != :userid";
		    }
		    $sub_dep=$DB->get_records_sql($sql,array('suspended' => 0,'deleted' => 0,'costcenterid' =>$supervisor ,'userid' => $userid));
		    
		    return $sub_dep;
	    }
	    
	}

	/* find department supervisors list
	@param need to pass supervisor and userid optional value*/
	public function find_dept_supervisor_list($supervisor,$userid=0){
	    if($supervisor){
	    global $DB;
	    $sql="SELECT u.id,Concat(u.firstname,' ',u.lastname) as username from {user} as u where u.suspended!=1 AND u.deleted!=1 AND u.open_departmentid= $supervisor AND u.id!= 1 AND u.id!=2";
	    if($userid){
	    	$sql .= " AND u.id != {$userid} AND u.id IN (SELECT open_supervisorid FROM {user} WHERE id = {$userid})";
	    }
	    $sub_dep=$DB->get_records_sql($sql);
	    
	      return $sub_dep;
	    }
	    
	}

	/**
	* Description: [department_elements code]
	* @param  [mform]  $mform          [form where the filetr is initiated]
	* @return [void]                  [description]
	*/
	public function department_elements($mform, $id, $context, $mformajax, $plugin){
	    global $DB, $USER;
	    $existdata = $DB->get_record('local_'.$plugin, array('id' => $id)); 

        if($plugin == 'evaluations'){
            $pluginname = 'local_evaluation';
        }else{
            $pluginname = 'local_course';
        }
	    $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    	if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$categorycontext)) {
                $organisation_select = [null => get_string('selectorg','local_courses')];
                if($id || $mformajax['costcenterid']){
                    $open_costcenter = (int) $mformajax['costcenterid'] ? (int)$mformajax['costcenterid'] : $existdata->costcenterid;
                    $organisations = $organisation_select + $DB->get_records_menu('local_costcenter', array('id' => $open_costcenter), '',  $fields='id, fullname'); 
                }else{
                    $open_costcenter = 0;
                    $organisations = $organisation_select;
                }
                $costcenteroptions = array(
                    'ajax' => 'local_costcenter/form-options-selector',
                    'data-contextid' => $categorycontext->id,
                    'data-action' => 'costcenter_organisation_selector',
                    'data-options' => json_encode(array('id' => $open_costcenter)),
                    'class' => 'organisationnameselect',
                    'data-class' => 'organisationselect',
                    'multiple' => false,
                );

                $mform->addElement('autocomplete', 'costcenterid', get_string('organization','local_courses'), $organisations, $costcenteroptions);
                $mform->addHelpButton('costcenterid', 'costcenteridcourse', $pluginname);
                $mform->setType('costcenterid', PARAM_INT);
                $mform->addRule('costcenterid', get_string('pleaseselectorganization','local_courses'), 'required', null, 'client');

            } else if (has_capability('local/costcenter:manage_ownorganization',$categorycontext)){

                $mform->addElement('hidden', 'costcenterid', null, array('id' => 'id_costcenterid', 'data-class' => 'organisationselect'));
                $mform->setType('costcenterid', PARAM_INT);
                $mform->setConstant('costcenterid', (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=1));
            
            } else if (has_capability('local/costcenter:manage_owndepartments',$categorycontext)){
            
                $mform->addElement('hidden', 'costcenterid', null, array('id' => 'id_costcenterid', 'data-class' => 'organisationselect'));
                $mform->setType('costcenterid', PARAM_INT);
                $mform->setConstant('costcenterid', (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=1));

                $mform->addElement('hidden', 'departmentid', $USER->open_departmentid,array('id' => 'id_departmentid', 'data-class' => 'departmentselect'));
                $mform->setType('departmentid', PARAM_INT);
                $mform->setConstant('departmentid', (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=2));

            } else {

                $mform->addElement('hidden', 'costcenterid', null, array('id' => 'id_costcenterid', 'data-class' => 'organisationselect'));
                $mform->setType('costcenterid', PARAM_INT);
                $mform->setConstant('costcenterid', (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=1));

                $mform->addElement('hidden', 'departmentid', (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=2), array('id' => 'id_departmentid', 'data-class' => 'departmentselect'));
                $mform->setType('departmentid', PARAM_INT);
                $mform->setConstant('departmentid', (new \local_costcenter\lib\accesslib())::get_user_roleswitch_path($depth=2));


            }
            if(is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$categorycontext) || has_capability('local/costcenter:manage_ownorganization',$categorycontext)){
                $department_select = [0 => get_string('selectdept','local_courses')];
                if($id || $mformajax['departmentid']){
                    $open_department = (int)$mformajax['departmentid'] ? (int)$mformajax['departmentid'] : $existdata->departmentid;
                    $departments = $department_select + $DB->get_records_menu('local_costcenter', array('id' => $open_department), '',  $fields='id, fullname'); 
                }else{
                    $open_department = 0;
                    $departments = $department_select;
                }
                $departmentoptions = array(
                    'ajax' => 'local_costcenter/form-options-selector',
                    'data-contextid' => $categorycontext->id,
                    'data-action' => 'costcenter_department_selector',
                    'data-options' => json_encode(array('id' => $open_department)),
                    'class' => 'departmentselect',
                    'data-parentclass' => 'organisationselect',
                    'data-class' => 'departmentselect',
                    'multiple' => false,
                );

                $mform->addElement('autocomplete', 'departmentid', get_string('department','local_evaluation'), $departments, $departmentoptions);
                $mform->addHelpButton('departmentid', 'departmentidcourse', $pluginname);
                $mform->setType('departmentid', PARAM_INT);
            }
            // if(is_siteadmin($USER->id) || 
            //     has_capability('local/costcenter:manage_multiorganizations',$categorycontext) ||
            //     has_capability('local/costcenter:manage_ownorganization',$categorycontext) ||
            //     has_capability('local/costcenter:manage_owndepartments',$categorycontext)){
            //     $subdepartment_select = [0 => get_string('selectsubdept','local_courses')];
            //     if($id || $mformajax['subdepartment']){
            //         $subdepartment = (int)$mformajax['subdepartment'] ? (int)$mformajax['subdepartment'] : $existdata->subdepartment;
            //         $subdepartments = $subdepartment_select + $DB->get_records_menu('local_costcenter', array('id' => $subdepartment), '',  $fields='id, fullname'); 
            //     }else{
            //         $subdepartment = 0;
            //         $subdepartments = $subdepartment_select;
            //     }
            //     $subdepartmentoptions = array(
            //         'ajax' => 'local_costcenter/form-options-selector',
            //         'data-contextid' => $categorycontext->id,
            //         'data-action' => 'costcenter_subdepartment_selector',
            //         'data-options' => json_encode(array('id' => $subdepartment)),
            //         'class' => 'subdepartmentselect',
            //         'data-parentclass' => 'departmentselect',
            //         'data-class' => 'subdepartmentselect',
            //         'multiple' => false,
            //     );

            //     $mform->addElement('autocomplete', 'subdepartment', get_string('sub_departments', 'local_courses'), $subdepartments, $subdepartmentoptions);
            //     $mform->addHelpButton('subdepartment', 'subdepartmentcourse', 'local_courses');
            //     $mform->setType('subdepartment', PARAM_INT);
            // }

	}

}