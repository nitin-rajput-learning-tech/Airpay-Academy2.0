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

class plugin_certification extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('certification', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('certification_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $certification = isset($filters['filter_certification']) ? $filters['filter_certification'] : null;
        $filtercertification = optional_param('filter_certification', $certification, PARAM_INT);
        if (!$filtercertification) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filtercertification);
        } else {
            if (preg_match("/%%FILTER_CERTIFICATION:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filtercertification;
                return str_replace('%%FILTER_CERTIFICATION:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $sql = "SELECT lti.id, lti.name
                FROM {local_tag_categories} ltc 
                JOIN {local_tag_items} lti ON lti.tagcategoryid = ltc.id
                WHERE 1 = 1 AND ltc.shortname = 'CERTIFICATION' ";

        $certifications = $DB->get_records_sql_menu($sql);

        $selectoption = array();
        $selectoption[0] = get_string('selectcertification', 'block_learnerscript');

        $certificationlist = $selectoption + $certifications;

        return $certificationlist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $certificationlist = $this->filter_data();

        $array = array('data-select2'=>true, 'data-action' => 'tagfilters','data-maximum-selection-length' => $this->maxlength, 'data-placeholder' => get_string('selectcertification', 'block_learnerscript'));
        $select = $mform->addElement('select', 'filter_certification', null, $certificationlist, $array);
        $select->setMultiple(true);
        $mform->setType('filter_certification', PARAM_RAW);
    }

}