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

class plugin_vendor extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('vendor', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('vendor_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $vendor = isset($filters['filter_vendor']) ? $filters['filter_vendor'] : null;
        $filtervendor = optional_param('filter_vendor', $vendor, PARAM_INT);
        if (!$filtervendor) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtervendor);
        } else {
            if (preg_match("/%%FILTER_VENDOR:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtervendor;
                return str_replace('%%FILTER_VENDOR:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $sql = "SELECT lti.id, lti.name
                FROM {local_tag_categories} ltc 
                JOIN {local_tag_items} lti ON lti.tagcategoryid = ltc.id
                WHERE 1 = 1 AND ltc.shortname = 'VENDOR' ";

        $vendors = $DB->get_records_sql_menu($sql);

        $selectoption = array();
        $selectoption[0] = get_string('selectvendor', 'block_learnerscript');

        $vendorlist = $selectoption + $vendors;

        return $vendorlist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $vendorlist = $this->filter_data();

        $array = array('data-select2'=>true, 'data-action' => 'tagfilters','data-maximum-selection-length' => $this->maxlength, 'data-placeholder' => get_string('selectvendor', 'block_learnerscript'));
        $select = $mform->addElement('select', 'filter_vendor', null, $vendorlist, $array); 
        $select->setMultiple(true);
        $mform->setType('filter_vendor', PARAM_RAW);
    }

}