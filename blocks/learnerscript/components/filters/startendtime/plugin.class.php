<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @author: eAbyas Info Solutions
 * @date: 2017
 */
use block_learnerscript\local\pluginbase;

class plugin_startendtime extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('startendtime', 'block_learnerscript');
        $this->reporttypes = array('sql', 'timeline', 'uniquelogins');
    }

    public function summary($data) {
        return get_string('filterstartendtime_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        global $CFG;
        if ($this->report->type != 'sql') {
            return $finalelements;
        }
            $fstarttime = isset($filters['filter_starttime']) ? $filters['filter_starttime'] :null;
            $fendtime = isset($filters['filter_endtime']) ? $filters['filter_endtime'] :null;
        if ($CFG->version < 2011120100) {
            $filterstarttime = optional_param('filter_starttime',$fstarttime , PARAM_RAW);
            $filterendtime = optional_param('filter_endtime', $fendtime, PARAM_RAW);
        } else {
            $filterstarttime = optional_param_array('filter_starttime', $fstarttime, PARAM_RAW);
            $filterendtime = optional_param_array('filter_endtime', $fendtime, PARAM_RAW);
        }

        // if (!$filterstarttime || !$filterendtime) {
        //     return $finalelements;
        // }
        if(isset($filters['filter_starttime']) && $filters['filter_starttime']['enabled']) {
            $filterstarttime = make_timestamp($filterstarttime['year'], $filterstarttime['month'], $filterstarttime['day'], $filterstarttime['hour'], $filterstarttime['minute']);
        } else {
            $filterstarttime = 0;
        }
        if(isset($filters['filter_endtime']) && $filters['filter_endtime']['enabled']) {
            $filterendtime = make_timestamp($filterendtime['year'], $filterendtime['month'], $filterendtime['day'], $filterendtime['hour'], $filterendtime['minute']);
        } else {
            $filterendtime = time();
        }
        $operators = array('<', '>', '<=', '>=');
        if (preg_match("/%%FILTER_STARTTIME:([^%]+)%%/i", $finalelements, $output)) {
            list($field, $operator) = preg_split('/:/', $output[1]);
            if (!in_array($operator, $operators)) {
                print_error('nosuchoperator');
            }
            $replace = ' AND ' . $field . ' ' . $operator . ' ' . $filterstarttime;
            $finalelements = str_replace('%%FILTER_STARTTIME:' . $output[1] . '%%', $replace, $finalelements);
        }
        if (preg_match("/%%FILTER_ENDTIME:([^%]+)%%/i", $finalelements, $output)) {
            list($field, $operator) = preg_split('/:/', $output[1]);
            if (!in_array($operator, $operators)) {
                print_error('nosuchoperator');
            }
            $replace = ' AND ' . $field . ' ' . $operator . ' ' . $filterendtime;
            $finalelements = str_replace('%%FILTER_ENDTIME:' . $output[1] . '%%', $replace, $finalelements);
        }

        $finalelements = str_replace('%STARTTIME%%', $filterstarttime, $finalelements);
        $finalelements = str_replace('%ENDTIME%%', $filterendtime, $finalelements);

        return $finalelements;
    }

    public function print_filter(&$mform) {
        global $DB, $CFG;
        $startdateattr = array(
                    'startyear' => 2000,
                    'stopyear'  => date("Y"),
                    'timezone'  => 99,
                    'optional'  => true
                );
        $enddateattr = array(
                    'startyear' => 2000,
                    'stopyear'  => date("Y")+10,
                    'timezone'  => 99,
                    'optional'  => true
                );
        $mform->addElement('date_time_selector', 'filter_starttime', get_string('starttime', 'block_learnerscript'), $startdateattr);
        // $mform->setDefault('filter_starttime', time() - 3600 * 24);
        $mform->addElement('date_time_selector', 'filter_endtime', get_string('endtime', 'block_learnerscript'), $enddateattr);
        // $mform->setDefault('filter_endtime', time() + 3600 * 24);
    }

}
