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

/**************To Check The Manager By Ravi_369**************/

function is_organisation_head(){
	global $DB,$USER;
	$sql="select ra.*
	from {context} as cxt
	JOIN {role_assignments} as ra on ra.contextid=cxt.id
	JOIN {role} as r on r.id=ra.roleid
	WHERE cxt.contextlevel=10 and r.shortname='manager' and ra.userid=$USER->id";
	$organization=$DB->record_exists_sql($sql);
	if($organization){
	 $organizationhead = true;
	}else{
		$organizationhead = false;
	}
	return $organizationhead;
}

function get_mydepartments($withoutselect = 0) {
	global $DB, $USER;
    $context = (new \local_costcenter\lib\accesslib())::get_module_context();
    $select = array(null=>get_string('all'));
    $costcenters = array();
    if ( has_capability('local/costcenter:manage_multiorganizations', $context) ) {
		if ($withoutselect == 1) {
			$costcenters = $DB->get_records_sql_menu('select fullname,id from {local_costcenter} ');
		} else {
			$costcenters = $DB->get_records_sql_menu('select id,fullname from {local_costcenter} ');			
		}        
    } else if (has_capability('local/costcenter:manage_multidepartments', $context)) {		
		$costcenter = $DB->get_record_sql("SELECT cc.id, cc.parentid FROM {user} u JOIN {local_costcenter} cc ON u.open_costcenterid = cc.id WHERE u.id={$USER->id}");
        if ($costcenter->parentid != 0) {
            //echo 'one';
            if ($withoutselect == 1) {
				$costcenters = $DB->get_records_sql_menu('select fullname,id from {local_costcenter} where parentid='.$costcenter->parentid.' OR id = '.$costcenter->parentid.'');
            } else {
                $costcenters = $DB->get_records_select_menu('local_costcenter','parentid=?',array($costcenter->parentid),'id','id,fullname');
            }            
        } else {
            //echo 'two';
            if ($withoutselect == 1) {
				$costcenters = $DB->get_records_sql_menu('select fullname,id from {local_costcenter} where parentid='.$costcenter->id.' OR id = '.$costcenter->id.'');
			} else {
				$costcenters = $DB->get_records_select_menu('local_costcenter','parentid=?',array($costcenter->id),'id','id,fullname');
			}            
        }
	}  else {
		if ($withoutselect == 1)
		$costcenters = $DB->get_records_sql_menu("SELECT cc.fullname, cc.id FROM {user} u JOIN {local_costcenter} cc ON u.open_costcenterid = cc.id WHERE u.id={$USER->id}");
		else
		$costcenters = $DB->get_records_sql_menu("SELECT cc.id, cc.fullname  FROM {user} u JOIN {local_costcenter} cc ON u.open_costcenterid = cc.id WHERE u.id={$USER->id}");
	}
    if ($withoutselect == 0) {
        $list = array_replace($select, $costcenters );
    } elseif ($withoutselect == 1) {
        $list = implode(',', $costcenters);
    }
    return $list;	
}


function get_myparentdepartment() {
    global $DB, $USER;
    $costcenter = $DB->get_record_sql("SELECT cc.id, cc.parentid FROM {user} u JOIN {local_costcenter} cc ON u.open_costcenterid = cc.id WHERE u.id={$USER->id}");        
        // $result = $DB->get_records_select_menu($table,$select,$params,$sort,$fields);
    $parent = ($costcenter->parentid != 0) ? $costcenter->parentid : $costcenter->id;
    return $parent;
}

function get_mydepartment() {
    global $DB, $USER;
    $costcenter = $DB->get_field_sql("SELECT cc.id FROM {user} u JOIN {local_costcenter} cc ON u.open_costcenterid = cc.id WHERE u.id={$USER->id}");
    return $costcenter;
}

function get_filterslist() {
	$context = (new \local_costcenter\lib\accesslib())::get_module_context();
	if (is_siteadmin() OR has_capability('local/costcenter:manage_multiorganizations', $context )) {
		$filterlist = array('organizations', 'departments','idnumber', 'email', 'groups','users', 'location', 'hrmsrole');
	}
	else if (has_capability('local/costcenter:manage_ownorganization',$context) ) {
		$filterlist = array('departments','idnumber', 'email', 'groups','users', 'location', 'hrmsrole');
	} 
	else if (has_capability('local/costcenter:manage_owndepartments',$context) ) {
		$filterlist = array('idnumber', 'email', 'groups', 'users', 'location', 'hrmsrole');
	} 
	else {
		$filterlist = array('idnumber', 'email','users', 'location', 'hrmsrole');
	}
	return $filterlist;
}

function get_more_filters($existingfilters) {	
	$newlist = array(null=>get_string('select'), 'idnumber'=>'idnumber', 'username'=>'username','users'=>'users');
	$unique=array_unique( array_merge($existingfilters, $newlist) );
	$filterlist  = array_diff($unique, $existingfilters);
	return $filterlist;
}


