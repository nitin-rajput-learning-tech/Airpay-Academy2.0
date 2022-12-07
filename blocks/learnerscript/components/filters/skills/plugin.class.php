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

class plugin_skills extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filterskills', 'block_learnerscript');
        $this->reporttypes = array('coursemodule');
    }

    public function summary($data) {
        return get_string('filterskills_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filterskills = optional_param('filter_skills', 0, PARAM_INT);
        if (!$filterskills) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterskills);
        } else {
            if (preg_match("/%%FILTER_SKILLS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterskills;
                return str_replace('%%FILTER_SKILLS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
     public function filter_data($selectoption = true,$type = ''){
         global $DB, $SKILLS , $USER;

         $params = array(); 
         $sql = "SELECT ls.id,ls.name 
                 FROM {local_skill} as ls
                 JOIN {course} as c ON ls.id = c.open_skill";

        $systemcontext = context_system::instance();
         /*if(!is_siteadmin()){
         $sql .=" AND u.id = $USER->id ";
         }*/
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND c.open_costcenterid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid;
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND c.open_costcenterid = :costcenterid AND c.open_departmentid = :departmentid ";
            $params['costcenterid'] = $USER->open_costcenterid;
            $params['departmentid'] = $USER->open_departmentid;
        }//echo $sql;exit;
        $sql .= " ORDER BY ls.name ASC ";
        $skills = $DB->get_records_sql_menu($sql,$params);
        
        $selectusersopt = array();
        $selectusersopt[0] = get_string('filter_skills', 'block_learnerscript');

        $userslist = $selectusersopt + $skills;

        return $userslist;
    }
    public function print_filter(&$mform) {     
        $skills = $this->filter_data();
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_skills', get_string('skills'), $skills,$array);
        $select->setHiddenLabel(true);
        $mform->setType('filter_skills', PARAM_INT);

    }

}
