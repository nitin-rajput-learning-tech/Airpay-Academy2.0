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

class plugin_feedbacks extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('filterfeedbacks', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('filterfeedbacks_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $filterlp = optional_param('filter_feedbacks', null, PARAM_INT);
        if (!$filterlp) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterlp);
        } else {
            if (preg_match("/%%FILTER_FEEDBACKS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterlp;
                return str_replace('%%FILTER_FEEDBACKS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function print_filter(&$mform, $selectoption = true) {
        global $DB, $USER;

        $sql = "SELECT le.id, le.name 
                FROM {local_evaluations} le 
                WHERE le.instance = 0 AND le.deleted  = 0 ";

        $params = array();
        $systemcontext = \context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND le.costcenterid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid; 
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND le.costcenterid = :costcenterid 
                    AND le.departmentid = :departmentid ";
            $params['costcenterid'] = $USER->open_costcenterid; 
            $params['departmentid'] = $USER->open_departmentid; 
        }

        $sql .= " ORDER BY le.name ASC ";

        $feedbacks = $DB->get_records_sql_menu($sql, $params);

        $selectfeedback = array();
        if($selectoption){
            $selectfeedback[null] = get_string('selectfeedback', 'block_learnerscript');
        }

        $feedbacklist = $selectfeedback + $feedbacks;

        $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_feedbacks', null, $feedbacklist, $array);

        $mform->setType('filter_feedbacks', PARAM_INT);
    }
}
