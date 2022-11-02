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
 * @package block_learnerscript
 */
use block_learnerscript\local\pluginbase;

class plugin_learningpath extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('learningpath', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('learningpath_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $lps = isset($filters['filter_learningpath']) ? $filters['filter_learningpath'] : null;
        $filterlp = optional_param('filter_learningpath', $lps, PARAM_INT);
        if (!$filterlp) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterlp);
        } else {
            if (preg_match("/%%FILTER_LEARNINGPATH:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterlp;
                return str_replace('%%FILTER_LEARNINGPATH:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $sql = "SELECT lp.id, lp.name 
                FROM {local_learningplan} lp
                WHERE 1 = 1  ";

        $systemcontext = context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $sql .= " ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $sql .= " AND lp.costcenter = $USER->open_costcenterid ";
        }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
            $sql .= " AND lp.costcenter = $USER->open_costcenterid 
                    AND lp.department = $USER->open_departmentid";
        }
        $sql .= " ORDER BY lp.name ASC ";

        $learningpaths = $DB->get_records_sql_menu($sql);

        $selectoption = array();
        $selectoption[0] = get_string('selectlearningplan', 'block_learnerscript');

        $learningpathlist = $selectoption + $learningpaths;

        return $learningpathlist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $learningpathlist = $this->filter_data();

        $array = array('data-select2'=>true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_learningpath', null, $learningpathlist, $array);
        $mform->setType('filter_learningpath', PARAM_INT);
    }

}