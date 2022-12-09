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

class plugin_webinars extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('webinars', 'block_learnerscript');
        $this->reporttypes = array();
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'webinars') {
                    $this->filtertype = 'basic';
                }
            }
        }
    }

    public function summary($data) {
        return get_string('webinars_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filterwebinars = optional_param('filter_webinars', 0, PARAM_INT);
        if (!$filterwebinars) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterwebinars);
        } else {
            if (preg_match("/%%FILTER_WEBINARS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterwebinars;
                return str_replace('%%FILTER_WEBINARS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true, $request){
        global $DB, $USER;
        $context = context_system::instance();
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name');
            if (has_capability('local/costcenter:manage_ownorganization', $context) && !is_siteadmin()) {
                $deptorgid = $USER->open_costcenterid;
            } else {
                if ($basicparams[0] == 'organization') {
                    $orgoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 1 ORDER BY id ASC"); 
                    $orgids = array_keys($orgoptions);
                    if (empty($request['filter_organization'])) {
                        $deptorgid = array_shift($orgids);
                    } else {
                        $deptorgid = $request['filter_organization'];
                    }
                }else {
                    $deptorgid = null;
                } 
            }
        } else {
            $deptorgid = null;
        }
        $sql=" SELECT c.id, c.fullname AS webinars
                FROM {course} c 
                JOIN {local_courses_learningformat} AS clf ON clf.id = c.open_learningformat
                WHERE 1 = 1 AND clf.name = 'Webinar' AND CONCAT(',',c.open_identifiedas,',') LIKE CONCAT('%,',3,',%') ";
        if (!empty($deptorgid)) {
            $sql .= " AND c.open_costcenterid = " . $deptorgid;
        } else {
            $systemcontext = context_system::instance();
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                $sql .= " ";
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
                $sql .= " AND c.open_costcenterid = $USER->open_costcenterid ";
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
                $sql .= " AND c.open_costcenterid = $USER->open_costcenterid 
                        AND c.open_departmentid = $USER->open_departmentid";
            }
        }
        $sql .= " ORDER BY c.id ASC ";

        $webinars = $DB->get_records_sql_menu($sql);
        $selectoption = array();
        $selectoption[0] = get_string('selectwebinar', 'block_learnerscript');

        $webinarslist = $selectoption + $webinars;

        return $webinarslist;
    }

    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(false, $request);        
        return $filterdata[$selected];
    }
    public function print_filter(&$mform) {     
        $request = array_merge($_POST, $_GET);     
        $userslist = $this->filter_data(false, $request);
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_webinars', get_string('webinars'),  $userslist,$array);

        $mform->setType('filter_webinars', PARAM_INT);
    }
}