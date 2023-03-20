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
class report_skill extends reportbase implements report {

    public function __construct($report, $reportproperties) {
        global $DB;
        parent::__construct($report);
        $this->columns = ['userfield'=>['userfield'],'skill' => ['course','skill','level','achievedon']];
        $this->components = array('columns', 'filters','permissions');
        $this->filters = array('skills');
        $this->defaultcolumn = 'cc.id';
        $this->orderable = array('skill','course','level');
        $this->filters = array('skills');

    }
        function init() {
        parent::init();
    }
    function count() {
        $this->sql = "SELECT COUNT(DISTINCT(cc.id)) ";
    }
    function select() {
        $this->sql ="SELECT cc.id,u.id as userid,c.fullname as course,ls.name as skill,
                    cl.name as level,cc.timecompleted as achievedon, CONCAT(u.firstname,' ',u.lastname) AS fullname,u.idnumber as employeeid"; 
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} as u  ";
    }
    function joins() {
         $this->sql .= "JOIN {course_completions} as cc ON u.id = cc.userid 
                        JOIN {course} as c ON c.id = cc.course 
                        JOIN {local_skill} as ls ON ls.id = c.open_skill 
                        JOIN {local_course_levels} as cl ON c.open_level = cl.id";
          parent::joins();
    }
    function where(){
        global $USER, $DB;
         $this->sql .=  " WHERE c.visible = :visible AND
                        u.deleted = :deleted AND u.suspended = :supended 
                        AND cc.timecompleted != 'NULL' ";

         $this->params['visible']= 1;
         $this->params['deleted']= 0;
         $this->params['supended']= 0;
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
        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path', null, 'lowerandsamepath');
        if (is_siteadmin()) {
            $this->sql .= "";
        } else  {
            $this->sql .= $costcenterpathconcatsql;
        }


         parent::where();
    }
   function search() {
       if (isset($this->search) && $this->search) {
            $fields = array('ls.name',"CONCAT(u.firstname,' ',u.lastname)",'u.open_employeeid');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        } 
    }
    function filters() {    
       if (!empty($this->params['filter_skills'])) {
            $skillname = $this->params['filter_skills'];
            $this->sql .= " AND c.open_skill = :skillname";
            $this->params['skillname'] = $skillname;
        }
    }    
    public function get_rows($skill) {
      
        return $skill;
    }
}
