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

class plugin_myclassroomscolumns extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('myclassroomscolumns', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('myclassroomscolumns_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $myclassroomscolumns = optional_param('filter_myclassroomscolumns', 0, PARAM_INT);
        if (!$myclassroomscolumns) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtermyclassroomcolumns);
        } else {
            if (preg_match("/%%FILTER_MYCLASSROOMSCOLUMNS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtermyclassroomcolumns;
                return str_replace('%%FILTER_MYCLASSROOMSCOLUMNS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true){
        global $DB, $USER;
      
        $sql = "SELECT lc.id,lc.name
                FROM {local_classroom} as lc
                JOIN {local_classroom_users} as cu ON lc.id = cu.classroomid
                WHERE cu.userid = $USER->id
                ORDER BY lc.name ASC ";

        $myclassrooms = $DB->get_records_sql_menu($sql,$params);

        $selectopt = array();
        $selectopt[0] = get_string('selectclassroom', 'block_learnerscript');

        $myclassroomslist = $selectopt + $myclassrooms;

        return $myclassroomslist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {     
        $myclassroomslist = $this->filter_data();

        $array = array('data-select2' => true,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_myclassroomscolumns', get_string('myclassroomscolumn', 'block_learnerscript'), $myclassroomslist,$array);
        
        $mform->setType('filter_myclassroomscolumns', PARAM_INT);
    }

}
