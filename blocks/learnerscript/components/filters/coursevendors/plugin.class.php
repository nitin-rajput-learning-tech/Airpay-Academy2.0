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

class plugin_coursevendors extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filtercoursevendors', 'block_learnerscript');
        $this->reporttypes = array('sql');
    }

    public function summary($data) {
        return get_string('filtercoursevendors_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filtercoursevendors = optional_param('filter_coursevendors', 0, PARAM_INT);
        if (!$filtercoursevendors) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtercoursevendors);
        } else {
            if (preg_match("/%%FILTER_COURSEVENDORS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercoursevendors;
                return str_replace('%%FILTER_COURSEVENDORS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true){
        global $DB, $USER;

        $params = array(); 
        $sql = "SELECT lcv.id, lcv.vendorname FROM {local_courses_venderslist} lcv 
            WHERE 1 = 1 ";

        $params['deleted'] = 0;
        $params['suspended'] = 0;

        $systemcontext = context_system::instance();

        
        // if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        //     $sql .= " ";
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
        //     $sql .= " AND u.open_costcenterid = :costcenterid ";
        //     $params['costcenterid'] = $USER->open_costcenterid;
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
        //     $sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid ";
        //     $params['costcenterid'] = $USER->open_costcenterid;
        //     $params['departmentid'] = $USER->open_departmentid;
        // }
        // $sql .= " ORDER BY u.firstname ASC ";
        $filtercoursevendors = $DB->get_records_sql_menu($sql,$params);

        $selectcoursevendorsopt = array();
        $selectcoursevendorsopt[0] = get_string('filter_coursevendors', 'block_learnerscript');

        $coursevendorslist = $selectcoursevendorsopt + $filtercoursevendors;

        return $coursevendorslist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }
    
    public function print_filter(&$mform) {

        $useroptions = $this->filter_data();
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_coursevendors', get_string('user'), $useroptions,$array);
        $select->setHiddenLabel(true);
        $mform->setType('filter_coursevendors', PARAM_INT);
    }

}