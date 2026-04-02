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
 * @date: 2019
 */
use block_learnerscript\local\pluginbase;

class plugin_feedback_courses extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('feedback_courses', 'block_learnerscript');
        $this->reporttypes = array('sql');
    }

    public function summary($data) {
        return get_string('feedback_courses_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $fcourse = isset($filters['filter_courses']) ? $filters['filter_courses'] : null;
        $filtercourses = optional_param('filter_courses', $fcourse, PARAM_INT);
        if (!$filtercourses) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtercourses);
        } else {
            if (preg_match("/%%FILTER_COURSES:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercourses;
                return str_replace('%%FILTER_COURSES:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true){
        global $DB,$USER;
        $systemcontext = context_system::instance();
        
        $sql .= " SELECT c.id,c.fullname 
                    FROM {course} as c 
                    JOIN {feedback} f ON f.course = c.id
                    WHERE 1 = 1 ";

        // if($this->report->type == 'customuseractivity'){
        //     $sql .= " AND le.quarterlyfb = 1 ";
        // }

        $systemcontext = context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= "";
        }else if(!is_siteadmin() && (has_capability('local/costcenter:manage_ownorganization', $systemcontext) || has_capability('block/learnerscript:view_organization_report', $systemcontext))){
            $sql .= " AND c.open_costcenterid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid;
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid";
            $params['costcenterid'] = $USER->open_costcenterid;
            $params['departmentid'] = $USER->open_departmentid;
        }
        $sql .= " ORDER BY c.fullname ASC";
        
        $courseoptions = $DB->get_records_sql_menu($sql, $params);
        if($courseoptions){
            $courseoptions = array(NULL => 'Select Courses')+$courseoptions;
        }else{
            $courseoptions = array(NULL => 'Select Courses');
        }
        return $courseoptions;
    }
    public function print_filter(&$mform) {
        global $DB, $CFG, $USER;

        $courseoptions = $this->filter_data();
        // if(!$this->placeholder){
        //     unset($classroomsoptions[0]);
        // }

        $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_courses', null, $courseoptions,$array);
        $select->setHiddenLabel(true);
        if(!$this->singleselection){
            $select->setMultiple(true);
        }
        if($this->required){
            $select->setSelected(array_keys($courseoptions)[1]);
        }else {
            $select->setSelected(array_keys($courseoptions)[0]);
        }
        $mform->setType('filter_courses', PARAM_INT);
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

}
