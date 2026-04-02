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

class plugin_userstatus extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('userstatus', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('userstatus_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {

        $filterusers = optional_param('filter_userstatus', null, PARAM_INT);
        if (!$filterusers) {
            return $finalelements;
        }
        return $finalelements;
    }

    public function print_filter(&$mform) {
        
        $usersoptions = array(-1 => 'Select Employee Status',
                             0=>get_string('active'),
                             1=>get_string('inactive'));

        $select = $mform->addElement('select', 'filter_userstatus', null, $usersoptions,
                    array('data-select2' => 1,
                          'data-maximum-selection-length' => $this->maxlength));
        $mform->setType('filter_userstatus', PARAM_INT);
    }
}