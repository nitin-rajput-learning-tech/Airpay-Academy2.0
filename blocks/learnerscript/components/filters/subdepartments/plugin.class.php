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
        $fsubdepartments = isset($request['filter_subdepartments']) ? $request['filter_subdepartments'] : 0;
        $filtersubdepartments = optional_param('filter_subdepartments', $fsubdepartments, PARAM_RAW);
        if (empty($this->reportclass->basicparams)) {
            $cohortoptions = array(get_string('filter_subdepartments', 'block_learnerscript'));
        } 
        $filtersubdepartment = $this->reportclass->filters;
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name'); 
            if (in_array('organization', $basicparams) && $basicparams[0] == 'organization') {
                $organizationoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 1 ORDER BY id ASC");
                $organizationids = array_keys($organizationoptions);
                if (empty($request['filter_organization'])) {
                    $organizationid = array_shift($organizationids);
                } else {
                    $organizationid = $request['filter_organization'];
                }
            } else {
                $organizationid = 0;
            }
        } else {
            $this->cohortid = null;
        } 
        $systemcontext = context_system::instance();
        if(is_siteadmin()){
            $this->organizationid = isset($organizationid) ? $organizationid : 0;
        } else {
            $this->organizationid = $USER->open_costcenterid;
        }
        
        $this->filtersubdeptid = isset($filtersubdepartments) ? $filtersubdepartments : 0;
        $departmentid = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE parentid = $this->organizationid AND depth = 2 ORDER BY id ASC LIMIT 0, 1"); //print_object($this);exit;
        $this->departmentid = isset($departmentid) ? $departmentid : 0;
        $querylib = new \block_learnerscript\local\querylib();
        $subdepartmentoptions = array(); 
        if(!empty($departmentid)){
            $subdepartmentoptions = $DB->get_records_sql_menu("SELECT lc.id, lc.fullname FROM {local_costcenter} lc WHERE lc.parentid = $this->departmentid AND lc.depth = 3");
        }

        $selectsubdept = array(); 
        // $selectsubdept[-1] = 'Select SubDepartment';
        if(empty($this->reportclass->basicparams)){
            $subdepartmentoptions[-1] = 'Commercial Unit'; 
        }else{
            $subdepartmentoptions[-1] = 'All';
        }
        $subdepartmentoptions = $subdepartmentoptions;   
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
