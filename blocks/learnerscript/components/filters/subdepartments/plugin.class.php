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

class plugin_subdepartments extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->filtertype = 'custom'; 
        $this->fullname = get_string('filtersubdepartments', 'block_learnerscript');
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
        return get_string('filtersubdepartments_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $subdeparts = isset($filters['filter_subdepartments']) ? $filters['filter_departments'] : null;
        $subdepartments = optional_param('filter_subdepartments', $subdeparts, PARAM_INT);
        if (!$subdepartments) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($subdepartments);
        } else {
            if (preg_match("/%%FILTER_SUBDEPARTMENTS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $subdepartments;
                return str_replace('%%FILTER_SUBDEPARTMENTS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data($selectoption = true, $request){
        global $DB, $USER;
        $filter_subdepartments = '';
        $filtersubdepartments = optional_param('filter_subdepartments', 0, PARAM_INT);
        $filterdepartment = $this->reportclass->filters;
        // $filteruserid = $filtercourse['filter_users'];
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name');
            if ($basicparams[0] == 'department') {
                $orgoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 2 ORDER BY id ASC");
                $orgids = array_keys($orgoptions);
                if (empty($request['filter_departments'])) {
                    $deptid = array_shift($orgids);
                } else {
                    $deptid = $request['filter_departments'];
                }
            }else {
                $subdeptid = null;
            }
        } else {
            $subdeptid = null;
        }
        $concatsql = " ";

        $params = array();
        $sql = "SELECT id, fullname
                FROM {local_costcenter}
                WHERE depth = :depth ";
        $params['depth'] = 3;

        $systemcontext = context_system::instance();
        if(is_siteadmin()){
            if (!empty($deptid)) {
                $concatsql .= " AND parentid = $deptid ";
            }
        } else if(!is_siteadmin()){
            // $sql .= " AND parentid = :costcenterid ";
            // $params['costcenterid'] = $USER->open_costcenterid;
            $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path');
        }
        $sql .= $concatsql;
        $sql .= " ORDER BY id ASC ";

        $subdepartmentoptions = $DB->get_records_sql_menu($sql, $params);


        $selectsubdept = array(); 
        // $selectsubdept[-1] = 'Select SubDepartment';
        if(empty($this->reportclass->basicparams)){
            $subdepartmentoptions[-1] = get_string('selectopen_subdepartment', 'local_costcenter');
        }else{
            $subdepartmentoptions[-1] = 'All';
        }
        ksort($subdepartmentoptions);

        return $subdepartmentoptions;
    }

    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(true, $request);
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        global $USER;
        $depth = $USER->useraccess['currentroleinfo']['depth'];
        if(count($USER->useraccess['currentroleinfo']['contextinfo']) > 1){
            $depth--;
        }

        if(is_siteadmin() || $depth < 4){
            $request = array_merge($_POST, $_GET);
            $subdeptoptions = $this->filter_data(true, $request);
            if ((!$this->placeholder || $this->filtertype == 'basic') && COUNT($subdeptoptions) > 1) {
                unset($subdeptoptions[-2]);
            }
            $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);               
            $select = $mform->addElement('select', 'filter_subdepartments', null, $subdeptoptions, $array);
            if (!$this->singleselection) {
                $select->setMultiple(true);
            }
            $select->setHiddenLabel(true);
            $mform->setType('filter_subdepartments', PARAM_INT);
        }
    }

}
