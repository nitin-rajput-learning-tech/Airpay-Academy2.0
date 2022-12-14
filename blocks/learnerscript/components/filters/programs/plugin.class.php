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

class plugin_programs extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('filterprograms', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('filterprograms_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $filterprogram = optional_param('filter_programs', null, PARAM_INT);
        if (!$filterprogram) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterprogram);
        } else {
            if (preg_match("/%%FILTER_PROGRAMS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterprogram;
                return str_replace('%%FILTER_PROGRAMS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }
    public function filter_data($selectoption = true){
        global $DB, $CFG;
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
                        $deptorgid = NULL;
                    }
                }else {
                    $deptorgid = null;
                } 
            }
        } else {
            $deptorgid = null;
        }
        $sql = "SELECT id, name
                FROM {local_program} lp 
                WHERE 1 = 1 ";
        if (!empty($deptorgid)) {
            $sql .= " AND lp.costcenter = " . $deptorgid;
            $departmentid = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE parentid = $deptorgid AND depth = 2 ORDER BY id ASC LIMIT 0, 1");
            if (!empty($departmentid)) {
                $sql .= " AND lp.department = " . $departmentid;
                $subdepartmentid = $DB->get_field_sql("SELECT id FROM {local_costcenter} WHERE parentid = $departmentid AND depth = 3 ORDER BY id ASC LIMIT 0, 1");
                if (!empty($subdepartmentid)) {
                    $sql .= " AND lp.subdepartment = " . $subdepartmentid;
                }
            }
        } else {
            $params = array();
            $systemcontext = \context_system::instance();
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                $sql .= " ";
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
                $sql .= " AND lp.costcenter = :costcenterid ";
                $params['costcenterid'] = $USER->open_costcenterid;
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
                $sql .= " AND lp.costcenter = :costcenterid AND lp.department = :departmentid ";
                $params['costcenterid'] = $USER->open_costcenterid;
                $params['departmentid'] = $USER->open_departmentid;
            }else{
                $sql .= " AND lp.costcenter = :costcenterid AND lp.department = :departmentid 
                AND lp.subdepartment = :subdepartmentid";
                $params['costcenterid'] = $USER->open_costcenterid;
                $params['departmentid'] = $USER->open_departmentid;
                $params['subdepartmentid'] = $USER->open_subdepartment;

            }            
        }

        $sql .= " ORDER BY lp.name ASC ";
        
        $programs = $DB->get_records_sql_menu($sql, $params);
        
        $selectproption = array();
        $selectproption[null] = get_string('selectprogram', 'block_learnerscript');

        $programslist = $selectproption + $programs;
        return $programslist;
    }    
    public function print_filter(&$mform, $selectoption = true) {
        global $DB, $USER;
        $programoptions = $this->filter_data();
        $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_programs', null, $programoptions, $array);

        $mform->setType('filter_programs', PARAM_INT);
    }
}
