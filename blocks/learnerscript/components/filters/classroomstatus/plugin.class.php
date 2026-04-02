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

class plugin_classroomstatus extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('classroomstatus', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('classroomstatus_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {

        $filterusers = optional_param('filter_classroomstatus', 0, PARAM_INT);
        if (!$filterusers) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterusers);
        } else {
            if (preg_match("/%%FILTER_CLASSROOMSTATUS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterusers;
                return str_replace('%%FILTER_CLASSROOMSTATUS:' . $output[1] . '%%', $replace,
                    $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        $classroomoptions = array(-1 => 'Select Status',
                                    0 => get_string('new'),
                                    1 => get_string('active'),
                                    2 => 'Hold',
                                    3 => get_string('cancel'),
                                    4 => 'Completed');
        return $classroomoptions;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {
        
        $classroomoptions = $this->filter_data();
        
        $mform->addElement('select', 'filter_classroomstatus', null, $classroomoptions,
                    array('data-select2' => 1,
                          'data-maximum-selection-length' => $this->maxlength));
        $mform->setType('filter_classroomstatus', PARAM_INT);
    }
}