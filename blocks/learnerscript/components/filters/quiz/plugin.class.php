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

class plugin_quiz extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filterquiz', 'block_learnerscript');
        $this->reporttypes = array('sql');
    }

    public function summary($data) {
        return get_string('filterquiz_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {
        $filterquiz = optional_param('filter_quiz', 0, PARAM_INT);
        if (!$filterquiz) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterquiz);
        } else {
            if (preg_match("/%%FILTER_QUIZ:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterquiz;
                return str_replace('%%FILTER_QUIZ:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true){
        global $DB, $USER;

        $params = array(); 
        $sql = "SELECT q.id, q.name as quizname 
                FROM {quiz} q
                WHERE 1= 1 ";
        $sql .= " ORDER BY q.name ASC ";
        $quizzes = $DB->get_records_sql_menu($sql,$params);

        $selectquizopt = array();
        $selectquizopt[0] = get_string('filter_quiz', 'block_learnerscript');

        $quizzeslist = $selectquizopt + $quizzes;

        return $quizzeslist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }
    
    public function print_filter(&$mform) {

        $quizoptions = $this->filter_data();
        $array = array('data-select2' => 1,'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_quiz', get_string('quiz'), $quizoptions,$array);
        $select->setHiddenLabel(true);
        $mform->setType('filter_quiz', PARAM_INT);
    }

}
