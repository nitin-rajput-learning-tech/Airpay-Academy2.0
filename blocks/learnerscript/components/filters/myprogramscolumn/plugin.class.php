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

class plugin_myprogramscolumn extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('myprogramscolumn', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('myprogramscolumn_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filtermyprogramscolumn  = optional_param('filter_myprogramscolumn', 0, PARAM_INT);
        if (!$filtermyprogramscolumn ) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtermyprogramscolumn );
        } else {
            if (preg_match("/%%FILTER_MYPROGRAMSCOLUMN([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtermyprogramcolumn;
                return str_replace('%%FILTER_MYPROGRAMSCOLUMN:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
     public function filter_data($selectoption = true,$type = ''){
         global $DB, $USER;
          $params = array(); 
            $sql = "SELECT lp.id,lp.name
                FROM {local_program} as lp
                JOIN {local_program_users} as lu ON lp.id = lu.programid
                WHERE lu.userid = :userid
                ORDER BY lp.name ASC ";

            $params['userid'] = $USER->id;

        $myprograms  = $DB->get_records_sql_menu($sql,$params);

        $selectopt = array();
        $selectopt[0] = get_string('selectprogram', 'block_learnerscript');

        $programslist = $selectopt + $myprograms ;

        return $programslist;
    }

     public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    } 

    public function print_filter(&$mform) {     
        $programslist  = $this->filter_data();
        
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_myprogramscolumn', get_string('program', 'block_learnerscript'), $programslist ,$array);
        
        $mform->setType('filter_myprogramscolumn', PARAM_INT);
    }

}
