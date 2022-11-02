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

class plugin_onlinetests extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('onlinetests', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('onlinetests_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $filterlp = optional_param('filter_onlinetests', null, PARAM_INT);
        if (!$filterlp) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterlp);
        } else {
            if (preg_match("/%%FILTER_ONLINETESTS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterlp;
                return str_replace('%%FILTER_ONLINETESTS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $sql = "SELECT lo.id, lo.name 
                FROM {local_onlinetests} lo
                WHERE 1 = 1 ";

        $params = array();
        $systemcontext = \context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND lo.costcenterid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid;
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND lo.costcenterid = :costcenterid 
                    AND lo.departmentid = :departmentid ";
            $params['costcenterid'] = $USER->open_costcenterid;
            $params['departmentid'] = $USER->open_costcenterid;
        }

        $sql .= " ORDER BY lo.name ASC ";

        $onlinetests = $DB->get_records_sql_menu($sql, $params);

        $otselectoption = array();
        $otselectoption[0] = get_string('select_onlinetest', 'block_learnerscript');

        $onlinetestslist = $otselectoption + $onlinetests;

        return $onlinetestslist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {

        $onlinetestslist = $this->filter_data();

        $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_onlinetests', null, $onlinetestslist,$array);

        $mform->setType('filter_onlinetests', PARAM_INT);
    }

}
