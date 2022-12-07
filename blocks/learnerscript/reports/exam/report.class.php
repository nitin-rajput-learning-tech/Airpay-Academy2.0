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
/** LearnerScript Reports
	* A Moodle block for creating customizable reports
	* @package blocks
	* @subpackage learnerscript
 * @author: Revanth Kumar
 * @date: 2021
	*/
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_exam extends reportbase implements report {
	/**
	 * [__construct description]
	 * @param object $report Report object
	 * @param object $reportproperties Report properties object
	 */
	public function __construct($report, $reportproperties) {
		global $USER;
		parent::__construct($report);
		$this->components = array('columns', 'permissions','orderable','plot');
		$this->columns = ['examcolumns'=> ['vendor', /*'inprogress', 'enrolments',*/ 'completed', /*'completionpercentage',*/ 'upcomingdeadline', 'overduedeadline','upcomingexpiry','upcomingendoflife']];    
		$this->parent = true;
		$this->orderable = array('vendor', /*'enrolments', 'inprogress',*/ 'completed', /*'completionpercentage',*/ 'upcomingdeadline', 'overduedeadline','upcomingexpiry','upcomingendoflife');
		if ($this->loggedinuserrole != 'dh') {
				$this->basicparams = array(['name' => 'organization'], ['name' => 'departments'], ['name'=>'subdepartments']); 
		}else if ($this->loggedinuserrole == 'dh') {
				$this->basicparams = array(['name' => 'subdepartments']); 
		}
        $this->filters = array('contentprovider', 'learningtype',  'solutionarea', 'technology', 'topic', 'vendor', 'level', 'language', 'jobrole');		
		$this->defaultcolumn = 'lcv.id';
		$this->excludedroles = array("'employee'");
	}
	function init() {
		if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
				$basicparams = array_column($this->basicparams, 'name');
				foreach ($basicparams as $basicparam) {
						if (empty($this->params['filter_' . $basicparam])) {
								return false;
						}
				}
		}        
		parent::init();
	}
	function count() { 
		$this->sql = "SELECT COUNT(DISTINCT lcv.id)";
	}
	function select() {
		$this->sql  = " SELECT DISTINCT lcv.id, lcv.vendorname AS vendor";
		parent::select();                
	}
	function from() {
		$this->sql .= " FROM {local_courses_venderslist} lcv 
						JOIN {block_ls_exams} le ON lcv.id = le.vendorid ";
	}

	function joins() {
		parent::joins();
	}

	function where(){ 
		$this->sql .= " WHERE 1 = 1 ";
		parent::where();
	}

	function search(){
		if (isset($this->search) && $this->search) {
				$fields = array('le.vendorname', 'le.username');
				$fields = implode(" LIKE '%$this->search%' ", $fields);
				$fields .= " LIKE '%$this->search%' ";
				$this->sql .= " AND ($fields) ";
		}       
	} 

	public function filters(){ 
		global $DB, $USER;
		$systemcontext = context_system::instance();
		if (!is_siteadmin()) {
				$scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
				if (!empty($scheduledreport)) {
				$compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
				$ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
				$dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
				} else {
						$ohs = $dhs = 1;
				}
		}
		if (!$this->scheduling) {
				if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
						if ($this->params['filter_organization'] > 0) {
		                    $this->sql .= " AND le.costcenterid IN (" .$this->params['filter_organization'] .','. 0 .") AND le.user_costcenterid = ".$this->params['filter_organization'];
						}
						if ($this->params['filter_departments'] > 0) {
								$this->sql .= " AND le.departmentid IN (".$this->params['filter_departments']. ", 0) AND le.user_departmentid = ".$this->params['filter_departments'] ;
						}
				} else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
                $this->sql .= " AND le.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")  AND le.user_costcenterid = ".$USER->open_costcenterid;
						if ($this->params['filter_departments'] > 0) {
								$this->sql .= " AND le.departmentid IN (".$this->params['filter_departments'].", 0) AND le.user_departmentid=".$this->params['filter_departments'];
						}
				}else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
               $this->sql .= " AND le.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND le.user_costcenterid = ".$USER->open_costcenterid ." AND le.departmentid = ".$USER->open_departmentid ." AND le.user_departmentid IN (". $USER->open_departmentid .", 0)" ;
				} else { 
                	$this->sql .= " AND le.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND le.user_costcenterid = ".$USER->open_costcenterid ." AND le.departmentid = ".$USER->open_departmentid ." AND le.user_departmentid IN (". $USER->open_departmentid .", 0) AND le.subdepartment IN (" .$USER->open_subdepartment . ',' . 0 .") AND le.user_subdepartment = ".$USER->open_subdepartment ;
				} 

				if ($this->params['filter_subdepartments'] > 0) {
					$this->sql .= " AND le.subdepartment IN (".$this->params['filter_subdepartments']. ", 0) AND le.user_subdepartment = ".$this->params['filter_subdepartments'] ;
				}
		}  
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $this->sql .= " AND le.open_contentvendor IN ($contentproviderids) ";
        }
        $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0;
        $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        $tagslist = array($learningtype, $solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        if (array_sum($tagslist) > 0) {
            $tagslist = implode(',', $tagslist); 
            $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
            if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                $this->sql .= " AND ble.examid IN (".$tagcoursesql.")";
            } else {
                $this->sql .= " AND ble.examid IN (0)";
            } 
        }
		if ($this->ls_startdate >= 0 && $this->ls_enddate) {
			$this->sql .= " AND le.timecreated BETWEEN $this->ls_startdate AND $this->ls_enddate ";
		} 
	}
	/**
	 * [get_rows description]
	 * @param  array  $exams [description]
	 * @return [type]        [description]
	 */
	public function get_rows($exams = array()) {
			return $exams;
	}
	public function column_queries($columnname, $vendorid, $courses = null) {
		global $DB, $USER; 
		$systemcontext = context_system::instance();
		if (!is_siteadmin()) {
				$scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
				if (!empty($scheduledreport)) {
				$compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
				$ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
				$dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
				} else {
						$ohs = $dhs = 1;
				}
		}
		$filtersql = " ";
		if (!$this->scheduling) {
				if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){ 
						if ($this->params['filter_organization']>0) {
								$filtersql .= " AND ble.costcenterid IN (" .$this->params['filter_organization'] .','. 0 .")  AND ble.user_costcenterid = ".$this->params['filter_organization']; 
						}
						if ($this->params['filter_departments'] > 0) {
								$filtersql .= " AND ble.departmentid IN (".$this->params['filter_departments'].", 0) AND ble.user_departmentid =".$this->params['filter_departments'];
						}
				} else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs) { 
						$filtersql .= " AND ble.costcenterid IN (" .$USER->open_costcenterid .','. 0 .")  AND ble.user_costcenterid = ".$USER->open_costcenterid; 
						if ($this->params['filter_departments'] > 0) {
								$filtersql .= " AND ble.departmentid IN (".$this->params['filter_departments'].", 0) AND ble.user_departmentid=".$this->params['filter_departments'];
						}
				}else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs) { 
					$filtersql .=  " AND ble.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND ble.user_costcenterid = ".$USER->open_costcenterid ." AND ble.user_departmentid = ".$USER->open_departmentid ." AND ble.departmentid IN (". $USER->open_departmentid .','. 0 .") AND ble.subdepartment IN (" .$USER->open_subdepartment . ',' . 0 . ") AND ble.user_subdepartment = ".$USER->open_subdepartment ;
				} else { 
					$filtersql .=  " AND ble.costcenterid IN (" .$USER->open_costcenterid .','. 0 .") AND ble.user_costcenterid = ".$USER->open_costcenterid ." AND ble.user_departmentid = ".$USER->open_departmentid ." AND ble.departmentid IN (". $USER->open_departmentid .','. 0 .")" ; 
				} 

				if ($this->params['filter_subdepartments'] > 0) {
					$filtersql .= " AND ble.subdepartment IN (".$this->params['filter_subdepartments'].", 0) AND ble.user_subdepartment=".$this->params['filter_subdepartments'];
				}
		}
        if (!empty($this->params['filter_contentprovider'])) {
            $contentproviderids = $this->params['filter_contentprovider']; 
            $filtersql .= " AND ble.open_contentvendor IN ($contentproviderids) ";
        }
        $learningtype = isset($this->params['filter_learningtype']) ? implode(',', $this->params['filter_learningtype']) : 0;
        $solutionarea = isset($this->params['filter_solutionarea']) ? implode(',', $this->params['filter_solutionarea']) : 0;
        $technology = isset($this->params['filter_technology']) ? implode(',', $this->params['filter_technology']) : 0;
        $topic = isset($this->params['filter_topic']) ? implode(',', $this->params['filter_topic']) : 0;
        $vendor = isset($this->params['filter_vendor']) ? implode(',', $this->params['filter_vendor']) : 0;
        $level = isset($this->params['filter_level']) ? implode(',', $this->params['filter_level']) : 0;
        $language = isset($this->params['filter_language']) ? implode(',', $this->params['filter_language']) : 0;
        $jobrole = isset($this->params['filter_jobrole']) ? implode(',', $this->params['filter_jobrole']) : 0;

        $tagslist = array($learningtype,$solutionarea, $technology, $topic, $vendor, $level, $language, $jobrole); 
        if (array_sum($tagslist) > 0) {
            $tagslist = implode(',', $tagslist); 
            $tagcoursesql  = (new querylib)->gettagcourses($tagslist);
            if (!empty($tagcoursesql) && $tagcoursesql > 0) { 
                $filtersql .= " AND ble.examid IN (".$tagcoursesql.")";
            } else {
                $filtersql .= " AND ble.examid IN (0)";
            } 
        }
		$where = " AND %placeholder% = $vendorid"; 
		switch ($columnname) {
				/*case 'inprogress': 
						$identy = 'ble.vendorid';
						if (isset($this->params['filter_status'])) {
								if($this->params['filter_status'] == 'inprogress') {
										$statussql = " AND ble.completiondate IS NULL ";
								}
						}                
						$query = " SELECT COUNT(id) AS inprogress 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.completiondate = 0 $where $filtersql $statussql";
				break;*/
				case 'completed': 
						$identy = 'ble.vendorid';
						if (isset($this->params['filter_status'])) {
								if($this->params['filter_status'] == 'completed') {
										$statussql = " AND ble.completiondate IS NOT NULL ";
								}
						}
						$query = " SELECT COUNT(id) AS completed 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.completiondate != 0 $where $filtersql $statussql ";
				break;
				/*case 'completionpercentage':
						$identy = 'ble.vendorid';
						$query = "SELECT ROUND((c1.completed / c2.enrolments) * 100, 0) AS progress FROM ((SELECT COUNT(id) AS completed 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.completiondate != 0 $where $filtersql ) 
											AS c1,
									 (SELECT COUNT(id) AS enrolments 
										FROM {block_ls_exams} ble
										WHERE 1 $where $filtersql ) AS c2 )";
						break;*/
				case 'upcomingdeadline': 
						$identy = 'ble.vendorid';
						$query = " SELECT COUNT(id) AS upcomingdeadline 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.deadline != 0 AND ble.completiondate = 0 AND 
										ble.deadline > UNIX_TIMESTAMP() $where $filtersql ";
						break;
				case 'overduedeadline': 
						$identy = 'ble.vendorid';
						$query = " SELECT COUNT(id) AS overduedeadline 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.deadline != 0 AND ble.completiondate = 0 AND 
										ble.deadline < UNIX_TIMESTAMP() $where $filtersql ";
						break; 
				case 'upcomingexpiry': 
						$identy = 'ble.vendorid';
						$query = " SELECT COUNT(id) AS upcomingexpiry 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.upcomingexpiry != 0 $where $filtersql  ";
					break; 
				case 'upcomingendoflife': 
						$identy = 'ble.vendorid';
						$query = " SELECT COUNT(id) AS upcomingeol 
										FROM {block_ls_exams} ble
										WHERE 1 AND ble.upcomingeol != 0 AND ble.vendorid !=0 AND ble.completiondate > 0 $where $filtersql ";
					break;
				default:
				return false;
						break;
		} 
		$query = str_replace('%placeholder%', $identy, $query);
		return $query;
	}
}
