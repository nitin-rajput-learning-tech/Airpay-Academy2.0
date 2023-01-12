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

class plugin_certificates extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('filtercertificates', 'block_learnerscript');
        $this->reporttypes = array('certificatesinfo', 'certificatecompletions');
    }

    public function summary($data) {
        return get_string('filtercertificates_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $fcertificate = isset($filters['filter_certificates']) ? $filters['filter_certificates'] : null;
        $filtercertificate = optional_param('filter_certificates', $fcertificate, PARAM_INT);
        if (!$filtercertificate) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtercertificate);
        } else {
            if (preg_match("/%%FILTER_CERTIFICATES:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercertificate;
                return str_replace('%%FILTER_CERTIFICATES:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $selectcert = array();
        if($selectoption){
            $selectcert[0] = $pluginclass->singleselection ? get_string('filter_certificates', 'block_learnerscript') :
            get_string('select') . ' ' . get_string('certification_nameforreports', 'block_learnerscript');
        }

        $params = array();
        $sql = "SELECT id, name 
                FROM {local_certification} 
                WHERE 1 = 1 ";

        $systemcontext = context_system::instance();
        // if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
        //     $sql .= " AND costcenter = :costcenterid ";
        //     $params['costcenterid'] = $USER->open_costcenterid;
        // }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
        //     $sql .= " AND costcenter = :costcenterid AND department = :departmentid ";
        //     $params['costcenterid'] = $USER->open_costcenterid;
        //     $params['departmentid'] = $USER->open_departmentid;
        // }

          $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context(); //context_system::instance();
          $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path'); 
          if (is_siteadmin()) {
              $sql .= "";
          } else  {
              $sql .= $costcenterpathconcatsql;
          }
        $sql .= " ORDER BY name ASC ";

        $certoptions = $DB->get_records_sql_menu($sql, $params);

        $selectcert[0] = 'Select certification'; 
        $certoptions = $selectcert + $certoptions;

        return $certoptions;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        $certoptions = $this->filter_data();

        $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_certificates', null, $certoptions, $array);
        $select->setHiddenLabel(true);
        if (!$this->singleselection) {
            $select->setMultiple(true);
        }

        $mform->setType('filter_certificates', PARAM_INT);
    }

}
