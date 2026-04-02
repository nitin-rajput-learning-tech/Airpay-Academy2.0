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
 * LearnerScript
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas info solutions
 * @date: 2017
 */
use block_learnerscript\local\pluginbase;

class plugin_course extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->filtertype = 'custom';
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'course') {
                    $this->filtertype = 'basic';
                }
            }
        }
        $this->fullname = get_string('filtercourse', 'block_learnerscript');
        $this->reporttypes = array('sql','coursesoverview');
    }

    public function summary($data) {
        return get_string('filtercourse_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $fcourse = isset($filters['filter_course']) ? $filters['filter_course'] : null;
        $filtercourses = optional_param('filter_course', $fcourse, PARAM_INT);
        if (!$filtercourses) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtercourses);
        } else {
            if (preg_match("/%%FILTER_COURSE:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercourses;
                return str_replace('%%FILTER_COURSE:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true, $request){ 
        global $DB, $USER;
        $filter_courses = '';
        $fcourses = isset($request['filter_course']) ? $request['filter_course'] : 0;
        $filtercourses = optional_param('filter_course', $fcourses, PARAM_RAW);
        if (empty($this->reportclass->basicparams)) {
            $courseoptions = array(get_string('filter_course', 'block_learnerscript'));
        } 
        $filtercourse = $this->reportclass->filters;
        // $filteruserid = $filtercourse['filter_users'];
        // if($this->reportclass->basicparams){
        //     $basicparams = array_column($this->reportclass->basicparams, 'name');
        //     if ($basicparams[0] == 'users') {
        //         $useroptions = (new \block_learnerscript\local\querylib)->filter_get_users($this,
        //                     false, false, false, false, false);
        //         $userids = array_keys($useroptions);
        //         $this->courseuserid = array_shift($userids);
        //     }else {
        //         $this->courseuserid = null;
        //     }
        //     if (in_array('organization', $basicparams) && $basicparams[0] == 'organization') {
        //         $organizationoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 1 ORDER BY id ASC");
        //         $organizationids = array_keys($organizationoptions);
        //         if (empty($request['filter_organization'])) {
        //             $courseorganizationid = array_shift($organizationids);
        //         } else {
        //             $courseorganizationid = $request['filter_organization'];
        //         }
        //     } else {
        //         $courseorganizationid = 0;
        //     }
        // } else {
        //     $this->courseuserid = null;
        // }

        
        $this->filtercoursesid = isset($filtercourses) ? $filtercourses : 0;
        $querylib = new \block_learnerscript\local\querylib(); 
        $courseoptions = $querylib->get_courseslist_forcoursefilter($this, $selectoption, false, false);   
        return $courseoptions;
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
        $courseoptions = $this->filter_data(true, $request);  
        /* if ((!$this->placeholder || $this->filtertype == 'basic') && COUNT($courseoptions) > 1) { 
            unset($courseoptions[0]);
        } */
        $select = $mform->addElement('select', 'filter_course', null,
            $courseoptions,
            array('data-select2' => true,
                  'data-maximum-selection-length' => $this->maxlength,
                  'data-action' => 'filtercourses',
                  'data-instanceid' => $this->reportclass->config->id)); 

        $select->setHiddenLabel(true);
        if (!$this->singleselection) {
            $select->setMultiple(true);
        }
        if ($this->required) {
            $select->setSelected(current(array_keys($courseoptions)));
        }
        $mform->setType('filter_course', PARAM_INT);

        $mform->addElement('hidden', 'filter_course_type', $this->filtertype); 
        $mform->setType('filter_course_type', PARAM_RAW);

    }
}
