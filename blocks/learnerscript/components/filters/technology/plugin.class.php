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

class plugin_technology extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('technology', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('technology_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $technology = isset($filters['filter_technology']) ? $filters['filter_technology'] : null;
        $filtertechnology = optional_param('filter_technology', $technology, PARAM_INT);
        if (!$filtertechnology) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtertechnology);
        } else {
            if (preg_match("/%%FILTER_TECHNOLOGY:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtertechnology;
                return str_replace('%%FILTER_TECHNOLOGY:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $sql = "SELECT lti.id, lti.name
                FROM {local_tag_categories} ltc 
                JOIN {local_tag_items} lti ON lti.tagcategoryid = ltc.id
                WHERE 1 = 1 AND ltc.shortname = 'TECHNOLOGY' ";

        $technologys = $DB->get_records_sql_menu($sql);

        $selectoption = array();
        $selectoption[0] = get_string('selecttechnology', 'block_learnerscript');

        $technologylist = $selectoption + $technologys;

        return $technologylist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $technologylist = $this->filter_data();

        $array = array('data-select2'=>true, 'data-action' => 'tagfilters','data-maximum-selection-length' => $this->maxlength, 'data-placeholder' => get_string('selecttechnology', 'block_learnerscript'));
        $select = $mform->addElement('select', 'filter_technology', null, $technologylist, $array); 
        $select->setMultiple(true);
        $mform->setType('filter_technology', PARAM_RAW);
    }

}