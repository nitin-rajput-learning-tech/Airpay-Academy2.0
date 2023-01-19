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
        $this->filters = ['organization','departments', 'subdepartments', 'level4department', 'level5department', 'geostate', 'geodistrict', 'geosubdistrict', 'geovillage', 'user'];
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
      if ($this->params['filter_organization'] > 0) {
        $orgpath = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_organization'], 'path');
        $this->sql .= " AND u.open_path like :orgpath ";
        $this->params['orgpath'] = $orgpath.'/%';
      }
      if ($this->params['filter_departments']  > 0) {
        $l2dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_departments'], 'path');
        $this->sql .= " AND u.open_path like :l2dept ";
        $this->params['l2dept'] = $l2dept.'/%';
      }
      if ($this->params['filter_subdepartments'] > 0) {
        $l3dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_subdepartments'], 'path');
        $this->sql .= " AND u.open_path like :l3dept ";
        $this->params['l3dept'] = $l3dept.'/%';
      }
      if ($this->params['filter_level4department'] > 0) {
        $l4dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level4department'], 'path');
        $this->sql .= " AND u.open_path like :l4dept ";
        $this->params['l4dept'] = $l4dept.'/%';
      }
      if ($this->params['filter_level5department'] > 0) {
        $l5dept = \local_costcenter\lib\accesslib::get_costcenter_info($this->params['filter_level5department'], 'path');
        $this->sql .= " AND u.open_path like :l5dept ";
        $this->params['l5dept'] = $l5dept.'/%';
      }
      if ($this->params['filter_geostate'] > 0) {
        $geostate = $this->params['filter_geostate'];
        $this->sql .= " AND u.open_states = :geostate ";
        $this->params['geostate'] = $geostate;
      }
      if ($this->params['filter_geodistrict'] > 0) {
        $geodistrict = $this->params['filter_geodistrict'];
        $this->sql .= " AND u.open_district = :geodistrict ";
        $this->params['geodistrict'] = $geodistrict;
      }
      if ($this->params['filter_geosubdistrict'] > 0) {
        $subdistrict = $this->params['filter_geosubdistrict'];
        $this->sql .= " AND u.open_subdistrict = :subdistrict ";
        $this->params['subdistrict'] = $subdistrict;
      }
      if ($this->params['filter_geovillage'] > 0) {
        $geovillage = $this->params['filter_geovillage'];
        $this->sql .= " AND u.open_village = :geovillage ";
        $this->params['geovillage'] = $geovillage;
      }
      // echo $this->sql;
    }    
    function get_rows($userdata){
      return $userdata;
    }
 }
