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

class plugin_myonlinetestscolumns extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('myonlinetestscolumns', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('myonlinetestscolumns_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filtermyonlinetestscolumns  = optional_param('filter_myonlinetestscolumns ', 0, PARAM_INT);
        if (!$filtermyonlinetestscolumns) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtermyonlinetestscolumns);
        } else {
            if (preg_match("/%%FILTER_MYONLINETESTSCOLUMNS([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtermyonlinetestscolumns;
                return str_replace('%%FILTER_MYONLINETESTSCOLUMNS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
     public function filter_data($selectoption = true,$type = ''){
         global $DB, $USER;
             $sql = "SELECT lo.id,lo.name
                    FROM {local_onlinetests} as lo
                    JOIN {local_onlinetest_users} as lu ON lo.id = lu.onlinetestid
                    WHERE lu.userid = $USER->id
                    ORDER BY lo.name ASC ";

        $myonlinetestscolumns  = $DB->get_records_sql_menu($sql);

        $selectopt = array();
        $selectopt[0] = get_string('selectonlinetest', 'block_learnerscript');

        $onlinetestslist = $selectopt + $myonlinetestscolumns ;

        return $onlinetestslist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {     
        $onlinetestslist  = $this->filter_data();
        
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_myonlinetestscolumns', get_string('myonlinetests','block_learnerscript'), $onlinetestslist ,$array);

        $mform->setType('filter_myonlinetestscolumns', PARAM_INT);

    }

}
