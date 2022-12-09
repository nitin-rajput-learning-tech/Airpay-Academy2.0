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
use block_learnerscript\local\querylib;
use block_learnerscript\report;

class report_userdata extends reportbase implements report {

    /**
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
      parent::__construct($report);
      $this->parent = true;
      $this->components = array('columns', 'filters', 'permissions');
      $this->columns = ['userfield'=>['userfield']];
      $this->filters = ['user'];
      $this->defaultcolumn = 'u.id';
      $this->orderable = array('');
    }
    function init() {
      parent::init();
    }
    function count() {
      $this->sql = "SELECT count(u.id)";
    }
    function select() {
      $this->sql = "SELECT u.id as userid";
      parent::select();
    }
    function from() {
      $this->sql .= " FROM {user} as u ";
    }
    function joins() {
      $this->sql .= " JOIN {local_costcenter} as c ON c.id = u.open_costcenterid ";
      parent::joins();
    }
    function where(){
      global $USER, $DB;
       $this->sql .=  " WHERE u.deleted = 0 ";
      $systemcontext = \context_system::instance();
      // getscheduled report
        if (!is_siteadmin()) {
            $scheduledreport = $DB->get_record_sql('select id,roleid from {block_ls_schedule} where reportid =:reportid AND sendinguserid IN (:sendinguserid)', ['reportid'=>$this->reportid,'sendinguserid'=>$USER->id], IGNORE_MULTIPLE);
            if (!empty($scheduledreport)) {
            $compare_scale_clause = $DB->sql_compare_text('capability')  . ' = ' . $DB->sql_compare_text(':capability');
            $ohs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_ownorganization']);
            $dhs = $DB->record_exists_sql("select id from {role_capabilities} where roleid =:roleid AND $compare_scale_clause", ['roleid'=>$scheduledreport->roleid, 'capability'=>'local/costcenter:manage_owndepartments']);
            } else {
                $ohs = $dh = 1;
            }
        }
      if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        $this->sql .= "";
      }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext) && $ohs){
        $this->sql .= " AND u.open_costcenterid = :costcenterid ";
        $this->params['costcenterid']= $USER->open_costcenterid;
      }else if(has_capability('local/costcenter:manage_owndepartments', $systemcontext) && $dhs){
        $this->sql .= " AND u.open_costcenterid = :costcenterid  AND u.open_departmentid = :departmentid";
        $this->params['costcenterid']= $USER->open_costcenterid;
        $this->params['departmentid']= $USER->open_departmentid;
      }else{
        $this->sql .= " AND u.open_costcenterid = :costcenterid  AND u.open_departmentid = :departmentid AND u.open_subdepartment = :subdepartment";
        $this->params['costcenterid']= $USER->open_costcenterid;
        $this->params['departmentid']= $USER->open_departmentid;
        $this->params['subdepartment']= $USER->open_subdepartment;
      }
      parent::where();
    }
    function search(){
      if (isset($this->search) && $this->search) {
        $fields = array("CONCAT(u.firstname, ' ', u.lastname)", "u.email");
        $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
        $fields .= " LIKE '%" . $this->search . "%' ";
        $this->sql .= " AND ($fields) ";
      }
    }  
    function filters(){ 
      if (!empty($this->params['filter_user'])) {
        $userid = $this->params['filter_user'];
        $this->sql .= " AND u.id = :userid ";
        $this->params['userid'] = $userid;
      }
    }    
    function get_rows($userdata){
      return $userdata;
    }
 }
