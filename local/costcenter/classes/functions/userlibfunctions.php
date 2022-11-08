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
	    // $departmentslist = array(get_string('all'));
	    // if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
	    //     $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
	    //     $costcenters = $DB->get_records_sql($sql);
	    //     $organizationlist=array(null=>get_string('select_organization', 'local_evaluation'));
	    //     foreach ($costcenters as $scl) {
	    //         $organizationlist[$scl->id]=$scl->fullname;
	    //     }
	    //     $mform->addElement('autocomplete', 'costcenterid', get_string('organization', 'local_users'), 
	    //     	$organizationlist);
	    //     $mform->addRule('costcenterid', null, 'required', null, 'client');
	    //     $mform->setType('costcenterid', PARAM_RAW);
	    // } elseif (has_capability('local/costcenter:manage_ownorganization',$context)){
	    //     $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
	    //     $mform->addElement('hidden', 'costcenterid', null);
	    //     $mform->setType('costcenterid', PARAM_RAW);
	    //     $mform->setConstant('costcenterid', $user_dept);
	    //     $sql="SELECT id,fullname from {local_costcenter} where visible =1 AND parentid = ?";
	    //     $departmentslists = $DB->get_records_sql_menu($sql, [$user_dept]);
	    //     if(isset($departmentslists)&&!empty($departmentslists))
	    //     $departmentslist = $departmentslist+$departmentslists;
	    // } elseif (has_capability('local/costcenter:manage_owndepartments',$context)){
	    // 	$mform->addElement('hidden', 'costcenterid', null);
	    //     $mform->setType('costcenterid', PARAM_RAW);
	    //     $mform->setConstant('costcenterid', $USER->open_costcenterid);
	        
	    //     $mform->addElement('hidden', 'departmentid');
	    //     $mform->setType('departmentid', PARAM_INT);
	    //     $mform->setConstant('departmentid', $USER->open_departmentid);
	    // } else {
	    //     $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
	    //     $mform->addElement('hidden', 'costcenterid', null);
	    //     $mform->setType('costcenterid', PARAM_RAW);
	    //     $mform->setConstant('costcenterid', $USER->open_costcenterid);
	        
	    //     $mform->addElement('hidden', 'departmentid');
	    //     $mform->setType('departmentid', PARAM_INT);
	    //     $mform->setConstant('departmentid', $USER->open_departmentid);

	    //     $mform->addElement('hidden', 'subdepartment');
	    //     $mform->setType('subdepartment', PARAM_INT);
	    //     $mform->setConstant('subdepartment', $USER->open_subdepartment);
	    // }

	    // if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context) ||
     //            has_capability('local/costcenter:manage_ownorganization',$context)) {
     //        if($id > 0){
     //            $open_costcenterid = $DB->get_field('local_'.$plugin.'','costcenterid',array('id'=>$id));
     //        } else{
     //            $open_costcenterid = $mformajax['costcenterid'];
     //        }

     //        if(!empty($open_costcenterid)){
     //            $departments = self::find_departments_list($open_costcenterid);
     //            foreach($departments as $depart){
     //                $departmentslist[$depart->id]=$depart->fullname;
     //            }
     //        } 
     //        $departmentselect = $mform->addElement('autocomplete', 'departmentid', get_string('department'),$departmentslist);
     //        $mform->setType('departmentid', PARAM_RAW);
     //    }
     //    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations',$context) ||
     //            has_capability('local/costcenter:manage_ownorganization',$context) || has_capability('local/costcenter:manage_owndepartments',$context)){
     //    	if($id > 0){
     //            $departmentid = $DB->get_field('local_'.$plugin.'','departmentid',array('id'=>$id));
     //        } else{
     //            $departmentid = $mformajax['departmentid'];
     //        }
     //        $sub_departmentslist[NULL] = 'All';
     //        if(!empty($departmentid)){
     //            $sub_departments = self::find_subdepartments_list($departmentid);
     //            foreach($sub_departments as $subdepart){
     //                $sub_departmentslist[$subdepart->id] = $subdepart->fullname;
     //            }
     //        } 
     //        $departmentselect = $mform->addElement('autocomplete', 'subdepartment', get_string('subdepartment', 'local_costcenter'),$sub_departmentslist);
     //        $mform->setType('subdepartment', PARAM_RAW);
     //    }
        if($plugin == 'evaluations'){
            $pluginname = 'local_evaluation';
        }else{
            $pluginname = 'local_course';
        }
	    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    	if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$systemcontext)) {
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
                    'data-contextid' => $systemcontext->id,
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

            } else if (has_capability('local/costcenter:manage_ownorganization',$systemcontext)){

                $mform->addElement('hidden', 'costcenterid', null, array('id' => 'id_costcenterid', 'data-class' => 'organisationselect'));
                $mform->setType('costcenterid', PARAM_INT);
                $mform->setConstant('costcenterid', $USER->open_costcenterid);
            
            } else if (has_capability('local/costcenter:manage_owndepartments',$systemcontext)){
            
                $mform->addElement('hidden', 'costcenterid', null, array('id' => 'id_costcenterid', 'data-class' => 'organisationselect'));
                $mform->setType('costcenterid', PARAM_INT);
                $mform->setConstant('costcenterid', $USER->open_costcenterid);

                $mform->addElement('hidden', 'departmentid', $USER->open_departmentid,array('id' => 'id_departmentid', 'data-class' => 'departmentselect'));
                $mform->setType('departmentid', PARAM_INT);
                $mform->setConstant('departmentid', $USER->open_departmentid);

            } else {

                $mform->addElement('hidden', 'costcenterid', null, array('id' => 'id_costcenterid', 'data-class' => 'organisationselect'));
                $mform->setType('costcenterid', PARAM_INT);
                $mform->setConstant('costcenterid', $USER->open_costcenterid);

                $mform->addElement('hidden', 'departmentid', $USER->open_departmentid, array('id' => 'id_departmentid', 'data-class' => 'departmentselect'));
                $mform->setType('departmentid', PARAM_INT);
                $mform->setConstant('departmentid', $USER->open_departmentid);

                // if($USER->open_subdepartment){
                //     $mform->addElement('hidden', 'subdepartment', null,array('id' => 'id_subdepartment'));
                //     $mform->setType('subdepartment', PARAM_INT);
                //     $mform->setConstant('subdepartment', $USER->open_subdepartment);
                // }
            }
            if(is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$systemcontext) || has_capability('local/costcenter:manage_ownorganization',$systemcontext)){
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
                    'data-contextid' => $systemcontext->id,
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
            //     has_capability('local/costcenter:manage_multiorganizations',$systemcontext) || 
            //     has_capability('local/costcenter:manage_ownorganization',$systemcontext) || 
            //     has_capability('local/costcenter:manage_owndepartments',$systemcontext)){
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
            //         'data-contextid' => $systemcontext->id,
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