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

class plugin_completionstatus extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = false;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('completionstatus', 'block_learnerscript');
        $this->reporttypes = array('');
    }

    public function summary($data) {
        return get_string('completionstatus_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {

        $filterusers = optional_param('filter_completionstatus', -1, PARAM_INT);
        if (!$filterusers) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterusers);
        } else {
            if (preg_match("/%%FILTER_COMPLETIONSTATUS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterusers;
                return str_replace('%%FILTER_COMPLETIONSTATUS:' . $output[1] . '%%', $replace,
                    $finalelements);
            }
        }
        return $finalelements;
    }

    public function filterlist(){
        return array(-1 => 'Select Status',
                    0 => 'Not Completed',
                    1 => 'Completed');
    }

    public function selected_filter($selected) {
        $filterdata = $this->filterlist();

        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {
        
        $statussoptions = $this->filterlist();
        
        $select = $mform->addElement('select', 'filter_completionstatus', null, $statussoptions,
                    array('data-select2' => 1));
        $select = $mform->getElement('filter_completionstatus')->setSelected(array_keys($statussoptions)[0]);
        
        $mform->setType('filter_completionstatus', PARAM_INT);
    }
}