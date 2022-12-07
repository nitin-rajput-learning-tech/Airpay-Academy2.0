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

class plugin_exam extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('exam', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data) {
        return get_string('exam_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $exam = isset($filters['filter_exam']) ? $filters['filter_exam'] : null;
        $filterexam = optional_param('filter_exam', $exam, PARAM_INT);
        if (!$filterexam) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterexam);
        } else {
            if (preg_match("/%%FILTER_EXAM:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterexam;
                return str_replace('%%FILTER_EXAM:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;

        $sql = "SELECT lti.id, lti.name
                FROM {local_tag_categories} ltc 
                JOIN {local_tag_items} lti ON lti.tagcategoryid = ltc.id
                WHERE 1 = 1 AND ltc.shortname = 'EXAM' ";

        $exams = $DB->get_records_sql_menu($sql);

        $selectoption = array();
        $selectoption[0] = get_string('selectexam', 'block_learnerscript');

        $examlist = $selectoption + $exams;

        return $examlist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $examlist = $this->filter_data();

        $array = array('data-select2'=>true, 'data-action' => 'tagfilters','data-maximum-selection-length' => $this->maxlength, 'data-placeholder' => get_string('selectexam', 'block_learnerscript'));
        $select = $mform->addElement('select', 'filter_exam', null, $examlist, $array); 
        $select->setMultiple(true);
        $mform->setType('filter_exam', PARAM_RAW);
    }

}