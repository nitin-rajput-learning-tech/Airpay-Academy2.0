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

class plugin_mycoursescolumn extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('mycoursecolumn', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('mycoursescolumn_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filtermycoursescolumn = optional_param('filter_mycoursescolumn', 0, PARAM_INT);
        if (!$filtermycoursescolumn) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtermycoursescolumn);
        } else {
            if (preg_match("/%%FILTER_MYCOURSESCOLUMN:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtermycoursescolumn;
                return str_replace('%%FILTER_MYCOURSESCOLUMN:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
     public function filter_data($selectoption = true,$type = ''){
         global $DB, $USER;

         $params = array(); 

        $sql=" SELECT c.id,c.fullname 
                FROM {user_enrolments} as ue
                JOIN {enrol} as e ON e.id = ue.enrolid 
                JOIN {role_assignments} as ra ON ra.userid = ue.userid
                JOIN {context} AS cxt ON cxt.id = ra.contextid AND cxt.contextlevel = 50
                JOIN {role} as r ON r.id = ra.roleid AND r.shortname IN ('employee','student')
                JOIN {course} as c ON c.id = e.courseid
                WHERE CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') 
                AND ue.userid = $USER->id ";

        $sql .= " ORDER BY c.fullname ASC ";
        
        $mycourses = $DB->get_records_sql_menu($sql,$params);
        
        $selectusersopt = array();
        $selectusersopt[0] = get_string('filter_mycoursess', 'block_learnerscript');

        $userslist = $selectusersopt + $mycourses;

        return $userslist;
    }

     public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    }
    public function print_filter(&$mform) {     
        $userslist = $this->filter_data();

        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_mycoursescolumn', get_string('mycoursess'),  $userslist,$array);

        $mform->setType('filter_mycoursesscolumn', PARAM_INT);

    }

}
