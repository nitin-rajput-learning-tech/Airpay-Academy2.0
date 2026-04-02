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
   
        $sql = "SELECT id, name
                FROM {local_program} lp 
                WHERE 1 = 1 ";
        $sql .= (new \local_courses\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lp.open_path');  

        $sql .= " ORDER BY lp.name ASC ";
        
        $programs = $DB->get_records_sql_menu($sql, array());
        
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
