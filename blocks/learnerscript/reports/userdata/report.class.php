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
        $this->columns = ['userfield'=>['userfield','fullname','username','firstname','lastname','email']];
        $this->filters = ['organization','departments', 'subdepartments', 'level4department', 'level5department', 'user'];
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
        $this->sql = "SELECT u.id as userid,CONCAT(u.firstname, ' ', u.lastname) AS fullname,u.* ";
        parent::select();
    }
    function from() {
        $this->sql .= " FROM {user} as u ";
    }
    function joins() {
        parent::joins();
    }
    function where(){
        global $USER, $DB;
        $this->sql .=  " WHERE u.deleted = 0 ";
        // $categorycontext =  (new \local_users\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        if (is_siteadmin()) {
          $this->sql .= "";
        } else  {
          $this->sql .= $costcenterpathconcatsql;
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
