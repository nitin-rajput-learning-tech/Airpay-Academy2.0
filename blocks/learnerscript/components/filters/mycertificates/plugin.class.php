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

class plugin_mycertificates extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filtermycertificates', 'block_learnerscript');
        $this->reporttypes = array('mycertification');
    }

    public function summary($data) {
        return get_string('filtermycertificates_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filtermycertificates = optional_param('filter_mycertificates', 0, PARAM_INT);
        if (!$filtermycertificates) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtermycertificates);
        } else {
            if (preg_match("/%%FILTER_MYCERTIFICATES:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtermycertificates;
                return str_replace('%%FILTER_MYCERTIFICATES:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
     public function filter_data($selectoption = true,$type = ''){
         global $DB, $MYCERTIFICATES, $USER;

         $params = array(); 
    
        $sql = "SELECT lc.id,lc.name
                FROM {local_certification} as lc
                JOIN {local_certification_users} as lu ON lc.id = lu.certificationid
                JOIN {user} as u ON u.id = lu.userid
                WHERE 1 = 1 AND lc.status IN(1,4) ";
         //echo $sql;exit;
         if(!is_siteadmin()){
         $sql .=" AND u.id = $USER->id";
         }
        //  if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
        //     $sql .= " ";
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
        //     $sql .= " AND u.open_costcenterid = :costcenterid ";
        //     $params['costcenterid'] = $USER->open_costcenterid;
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
        //     $sql .= " AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid ";
        //     $params['costcenterid'] = $USER->open_costcenterid;
        //     $params['departmentid'] = $USER->open_departmentid;
        // }
          $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context(); //context_system::instance();
          $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path'); 
          if (is_siteadmin()) {
              $sql .= "";
          } else  {
              $sql .= $costcenterpathconcatsql;
          }
        $sql .= " ORDER BY lc.name ASC ";
        $mycertificates = $DB->get_records_sql_menu($sql,$params);

        $selectusersopt = array();
        $selectusersopt[0] = get_string('filter_mycertificates', 'block_learnerscript');

        $userslist = $selectusersopt + $mycertificates;

        return $userslist;
    }

     public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) { 

        $userslist = $this->filter_data();

        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_mycertificates', get_string('mycertificates'), $userslist,$array);
        
        $mform->setType('filter_mycertificates', PARAM_INT);

    }

}
