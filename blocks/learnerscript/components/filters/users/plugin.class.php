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
 * @date: 2017
 */
use block_learnerscript\local\pluginbase;

class plugin_users extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->filtertype = 'custom';
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'users') {
                    $this->filtertype = 'basic';
                }
            }
        }
        $this->fullname = get_string('filterusers', 'block_learnerscript');
        $this->reporttypes = array('sql', 'userassignments', 'usercourses',
            'student_performance', 'uniquelogins', 'userquizzes', 'users',
            'student_overall_performance', 'topic_wise_performance', 'usersscorm');
    }

    public function summary($data) {
        return get_string('filterusers_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {

        $filterusers = optional_param('filter_users', 0, PARAM_RAW);
        if (!$filterusers) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterusers);
        } else {
            if (preg_match("/%%FILTER_SYSTEMUSER:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterusers;
                return str_replace('%%FILTER_SYSTEMUSER:' . $output[1] . '%%', $replace,
                    $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true, $request){
        global $DB, $USER;
        $filter_users = '';
        $fusers = isset($request['filter_users']) ? $request['filter_users'] : 0;
        $filterusers = optional_param('filter_users', $fusers, PARAM_RAW);
        if (empty($this->reportclass->basicparams)) {
            $useroptions = array(get_string('filter_users', 'block_learnerscript'));
        } 
        $filteruser = $this->reportclass->filters;
        // $filteruserid = $filtercourse['filter_users'];
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name');
            if ($basicparams[0] == 'courses' || $basicparams[0] == 'users') {
                $useroptions = (new \block_learnerscript\local\querylib)->filter_get_users($this,
                            false, false, false, false, false);
                $userids = array_keys($useroptions);
                $courseuserid = array_shift($userids);
            }else {
                $courseuserid = null;
            } 
            if (in_array('organization', $basicparams) && $basicparams[0] == 'organization') {
                $organizationoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 1 ORDER BY id ASC");
                $organizationids = array_keys($organizationoptions);
                if (empty($request['filter_organization'])) {
                    $courseorganizationid = array_shift($organizationids);
                } else {
                    $courseorganizationid = $request['filter_organization'];
                }
            } else {
                $courseorganizationid = 0;
            }
        } else {
            $courseuserid = null;
        }
        $this->courseorganizationid = isset($courseorganizationid) ? $courseorganizationid : 0;
        $this->filtercoursesid = isset($filterusers) ? $filterusers : 0;
        $usersoptions = (new \block_learnerscript\local\querylib)->filter_get_orgwiseusers($this, $selectoption, false, $filteruser, $filterusers);
        
        return $usersoptions;
    }
    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(true, $request);
        return $filterdata[$selected];
    }
    public function print_filter(&$mform) {
        global $DB, $CFG, $USER;
        if ($this->report->type == 'courseprofile' || $this->report->type == 'userprofile') {
            $selectoption = false;
        } else {
            $selectoption = true;
        }
        $request = array_merge($_POST, $_GET);
        $usersoptions = $this->filter_data(true, $request); 
        if (($this->placeholder || $this->filtertype == 'basic') && COUNT($usersoptions) > 1) { 
            unset($usersoptions[0]);
        } 
        $select = $mform->addElement('select', 'filter_users', null, $usersoptions,
                    array('data-select2' => true,
                          'data-maximum-selection-length' => $this->maxlength,
                        'data-action' => 'filterusers',
                        'data-instanceid' => $this->reportclass->config->id));

        $select->setHiddenLabel(true);
        $mform->setType('filter_users', PARAM_INT); 

        $mform->addElement('hidden', 'filter_users_type', $this->filtertype);
        $mform->setType('filter_users_type', PARAM_RAW);
    }
}
