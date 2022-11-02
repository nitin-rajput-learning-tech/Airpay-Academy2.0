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

class plugin_departments extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('filterdepartments', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('filterdepartments_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $fschool = isset($filters['filter_departments']) ? $filters['filter_departments'] : null;
        $filterschool = optional_param('filter_departments', $fschool, PARAM_INT);
        if (!$filterschool) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterschool);
        } else {
            if (preg_match("/%%FILTER_DEPARTMENTS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterschool;
                return str_replace('%%FILTER_DEPARTMENTS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $params = array();
        $sql = "SELECT id, fullname 
                FROM {local_costcenter} 
                WHERE depth = :depth ";
        $params['depth'] = 2;

        $systemcontext = context_system::instance();
        if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND parentid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid;
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND parentid = :costcenterid AND id = :departmentid ";
            $params['costcenterid'] = $USER->open_costcenterid;
            $params['departmentid'] = $USER->open_departmentid;
        }
        $sql .= " ORDER BY fullname ASC ";

        $deptoptions = $DB->get_records_sql_menu($sql, $params);

        $selectdept = array();
        $selectdept[null] = get_string('selectdept', 'block_learnerscript');
        $selectdept[0] = get_string('all');
        
        $deptoptions = $selectdept + $deptoptions;

        // if (!$this->placeholder) {
        //     unset($deptoptions[0]);
        // }
        return $deptoptions;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $systemcontext = context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            
            $deptoptions = $this->filter_data();
            $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);               
            $select = $mform->addElement('select', 'filter_departments', null, $deptoptions, $array);
            if (!$this->singleselection) {
                $select->setMultiple(true);
            }

            $select->setHiddenLabel(true);
            $mform->setType('filter_departments', PARAM_INT);
        }
    }

}
