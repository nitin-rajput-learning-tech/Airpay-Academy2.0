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

/**
 * LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2020
 */
use block_learnerscript\local\pluginbase;

class plugin_cohort extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true; 
        $this->placeholder = true;
        $this->singleselection = true;
        $this->fullname = get_string('filtercohort', 'block_learnerscript');
        $this->reporttypes = array('users');
        $this->filtertype = 'custom';
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'cohort') {
                    $this->filtertype = 'basic';
                }
            }
        }
    }

    public function summary($data) {
        return get_string('filtercohort_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {

        $filtercohort = isset($filters['filter_cohort']) ? $filters['filter_cohort'] : 0;
        if (!$filtercohort) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtercohort);
        } else {
            if (preg_match("/%%FILTER_COHORT:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercohort;
                return str_replace('%%FILTER_COHORT:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true, $request){
        global $DB, $USER;
        $filter_cohorts = '';
        $fcohorts = isset($request['filter_cohort']) ? $request['filter_cohort'] : 0;
        $filtercohorts = optional_param('filter_cohort', $fcohorts, PARAM_RAW);
        if (empty($this->reportclass->basicparams)) {
            $cohortoptions = array(get_string('filter_cohort', 'block_learnerscript'));
        } 
        $filtercohort = $this->reportclass->filters;
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name'); 
            if (in_array('organization', $basicparams) && $basicparams[0] == 'organization') {
                $organizationoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 1 ORDER BY id ASC");
                $organizationids = array_keys($organizationoptions);
                if (empty($request['filter_organization'])) {
                    $cohortorganizationid = array_shift($organizationids);
                } else {
                    $cohortorganizationid = $request['filter_organization'];
                }
            } else {
                $cohortorganizationid = 0;
            }
        } else {
            $this->cohortid = null;
        } 
        $systemcontext = context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $this->cohortorganizationid = isset($cohortorganizationid) ? $cohortorganizationid : 0;
        } else {
            $this->cohortorganizationid = $USER->open_costcenterid;
        }
        
        $departmentid = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE parentid = $this->cohortorganizationid AND depth = 2 ORDER BY id ASC LIMIT 0, 1"); 
        $this->cohortdepartmentid = isset($departmentid) ? $departmentid : 0;
        $this->filtercohortid = isset($filtercohorts) ? $filtercohorts : 0;
        $querylib = new \block_learnerscript\local\querylib(); 
        $cohortoptions = $querylib->get_cohortslist_forcoursefilter($this, $selectoption, false, false);   
        return $cohortoptions;
    }
    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(true, $request);
        return $filterdata[$selected];
    }
    public function print_filter(&$mform) {
        $request = array_merge($_POST, $_GET);
        $cohortoptions = $this->filter_data(true, $request); 
        if ((!$this->placeholder || $this->filtertype == 'basic') && COUNT($cohortoptions) > 1) {
            unset($cohortoptions[0]);
        }
        $select = $mform->addElement('select', 'filter_cohort', get_string('cohort', 'block_learnerscript'), $cohortoptions,array('data-select2'=>1));
        $select->setHiddenLabel(true);
        $mform->setType('filter_cohort', PARAM_INT);
    }

}
