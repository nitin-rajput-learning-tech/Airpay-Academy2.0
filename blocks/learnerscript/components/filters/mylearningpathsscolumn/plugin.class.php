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

class plugin_mylearningpathsscolumn extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('mylearningpathsscolumn', 'block_learnerscript');
        $this->reporttypes = array('mylearningplan');
    }

    public function summary($data) {
        return get_string('mylearningpathsscolumn_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $mylearningpathsscolumn  = optional_param('filter_mylearningpathsscolumn ', 0, PARAM_INT);
        if (!$mylearningpathsscolumn ) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($mylearningpathsscolumn );
        } else {
            if (preg_match("/%%FILTER_MYLEARNINGPATHSCOLUMN:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $mylearningpathsscolumn ;
                return str_replace('%%FILTER_MYLEARNINGPATHSCOLUMN:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
     public function filter_data($selectoption = true,$type = ''){
         global $DB, $USER;
          $sql = "SELECT ll.id,ll.name
                    FROM {local_learningplan} as ll
                    JOIN {local_learningplan_user} as lu ON ll.id = lu.planid
                    WHERE lu.userid = $USER->id 
                    ORDER BY ll.name ASC ";
            
        $mylearningpathscolumn  = $DB->get_records_sql_menu($sql);

        $selectopt = array();
        $selectopt[0] = get_string('select_learningpath', 'block_learnerscript');

        $mylearningpaths = $selectopt + $mylearningpathscolumn ;

        return $mylearningpaths;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {     
        $mylearningpaths  = $this->filter_data();
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_mylearningpathsscolumn', get_string('learningpath', 'block_learnerscript'), $mylearningpaths ,$array);
       
        $mform->setType('filter_mylearningpathsscolumn', PARAM_INT);

    }

}
