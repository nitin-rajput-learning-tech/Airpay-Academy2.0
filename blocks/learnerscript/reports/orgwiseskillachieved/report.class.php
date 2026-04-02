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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
defined('MOODLE_INTERNAL') || die();
class report_orgwiseskillachieved extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report);
        $this->columns = ['skill' => ['costcentername','skill','skillcode','skillcatname','totalusers','usersachievedcount','progress']];
        $this->components = array('columns', 'filters','permissions');
        $this->filters = array('skills');
        $this->defaultcolumn = 'sk.id';
        $this->orderable = array('costcentername','skill','totalusers','achievedcount');
        $this->filters = array('organization','skills');

    }
        function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT count(sk.id) ";
    }
    function select() {
        $this->sql ="SELECT sk.*, lc.id as lcid,lc.fullname as costcentername, sk.name as skill,sk.shortname as skillcode, lsc.name AS skillcatname "; 
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {local_skill} AS sk ";
    }
    function joins() {
         $this->sql .= "JOIN {local_costcenter} AS lc ON concat('/',sk.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 1 
                        JOIN {local_skill_categories} AS lsc ON lsc.id = sk.category";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .= " WHERE 1=1 ";
        // getscheduled report
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
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='sk.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }


         parent::where();
    }
   function search() {
        if (isset($this->search) && $this->search) {
            $fields = array("sk.name");
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }
    function filters() {  
        
        if (!empty($this->params['filter_organization'])  && $this->params['filter_organization'] > 0) {
            $organization = $this->params['filter_organization'];
            $this->sql .= " AND lc.id = $organization";
        }

        if (!empty($this->params['filter_skills'])) {
            $skillid = $this->params['filter_skills'];
            $this->sql .= " AND sk.id = $skillid ";
        }
    }  

    public function get_rows($skills) {
        
        global $DB;
        $data = array();
        if($skills){          
            foreach ($skills as $skill) {                
                $skill->totalusers = $DB->count_records_sql("SELECT COUNT(u.id)
                                                                    FROM {user} u 
                                                                    JOIN {local_costcenter} lc ON concat('/',u.open_path,'/') LIKE concat('%/',$skill->lcid,'/%') AND lc.depth = 1
                                                                    WHERE u.id > :id ",array('id' => 2)); 
                $skill->usersachievedcount = $DB->count_records_sql("SELECT count(DISTINCT(u.id))
                                                                    FROM {course} c
                                                                    JOIN {course_completions} cc on cc.course = c.id
                                                                    JOIN {user} u on cc.userid = u.id
                                                                    WHERE c.open_skill = {$skill->id} and c.open_coursetype = 0 and cc.timecompleted IS NOT NULL ");  
                $progress  =  ROUND(($skill->usersachievedcount/$skill->totalusers)*100,0);
               
                $skill->progress = '<div class="progress">
                        <div class="progress-bar text-center" role="progressbar" aria-valuenow="'.$progress.'" aria-valuemin="0" aria-valuemax="100" style="width:'.$progress.'%">
                            <span class="progress_percentage ml-2">'.$progress.'%</span>
                        </div>
                    </div>';
                $data[] = $skill;
            }
        }
        return $data;        
    }
}
