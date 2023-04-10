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
use block_learnerscript\local\pluginbase;

class plugin_level5department extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->filtertype = 'custom'; 
        $this->fullname = get_string('filterlevel5department', 'block_learnerscript');
        $this->reporttypes = array();
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'cohort') {
                    $this->filtertype = 'basic';
                }
            }
        }
    }

    public function summary($data) {
        return get_string('filterlevel5department_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $level5departs = isset($filters['filter_level5department']) ? $filters['filter_departments'] : null;
        $level5department = optional_param('filter_level5department', $level5departs, PARAM_INT);
        if (!$level5department) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($level5department);
        } else {
            if (preg_match("/%%FILTER_LEVEL5DEPARTMENT:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $level5department;
                return str_replace('%%FILTER_LEVEL5DEPARTMENT:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data($selectoption = true, $request = []){
        global $DB, $USER;
        $filter_level5department = '';
        $filterlevel5department = optional_param('filter_level5department', 0, PARAM_INT);
        $filterdepartment = $this->reportclass->filters;
        // $filteruserid = $filtercourse['filter_users'];
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name');
            if ($basicparams[0] == 'level4department') {
                $orgoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 2 ORDER BY id ASC");
                $orgids = array_keys($orgoptions);
                if (empty($request['filter_departments'])) {
                    $level4deptid = array_shift($orgids);
                } else {
                    $level4deptid = $request['filter_level4department'];
                }
            }
        }
        $concatsql = " ";

        $params = array();
        $sql = "SELECT id, fullname
                FROM {local_costcenter}
                WHERE depth = :depth ";
        $params['depth'] = 5;

        $systemcontext = context_system::instance();
        if(is_siteadmin()){
            if (!empty($level4deptid)) {
                $concatsql .= " AND parentid = $level4deptid ";
            }
        } else if(!is_siteadmin()){
            // $sql .= " AND parentid = :costcenterid ";
            // $params['costcenterid'] = $USER->open_costcenterid;
            $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path');
        }
        $sql .= $concatsql;
        $sql .= " ORDER BY id ASC ";

        $level5departmentoptions = $DB->get_records_sql_menu($sql, $params);


        $selectsubdept = array(); 
        // $selectsubdept[-1] = 'Select SubDepartment';
        if(empty($this->reportclass->basicparams)){
            $level5departmentoptions[-1] = get_string('select_level5department', 'local_costcenter');
        }else{
            $level5departmentoptions[-1] = 'All';
        }
        ksort($level5departmentoptions);

        return $level5departmentoptions;
    }
    public function enabledepth(){
        return 6;
    }
    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(true, $request);
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        global $USER;
        $depth = $USER->useraccess['currentroleinfo']['depth'];
        if(isset($USER->useraccess['currentroleinfo']['contextinfo']) && count($USER->useraccess['currentroleinfo']['contextinfo']) > 1){
            $depth--;
        }

        if(is_siteadmin() || $depth < 6){
            $request = array_merge($_POST, $_GET);
            $subdeptoptions = $this->filter_data(true, $request);
            if ((!$this->placeholder || $this->filtertype == 'basic') && COUNT($subdeptoptions) > 1) {
                unset($subdeptoptions[-2]);
            }
            $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);               
            $select = $mform->addElement('select', 'filter_level5department', null, $subdeptoptions, $array);
            if (!$this->singleselection) {
                $select->setMultiple(true);
            }
            $select->setHiddenLabel(true);
            $mform->setType('filter_level5department', PARAM_INT);
        }
    }

}
