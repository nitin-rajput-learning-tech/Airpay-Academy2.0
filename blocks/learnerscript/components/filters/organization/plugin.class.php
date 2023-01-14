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

class plugin_organization extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->filtertype = 'custom'; 
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'organization') {
                    $this->filtertype = 'basic';
                }
            }
        }
        $this->fullname = get_string('filterorganization', 'block_learnerscript');
        $this->reporttypes = array('sql','coursesoverview');
    }

    public function summary($data) {
        return get_string('filterorganization_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {

        $filterusers = optional_param('filter_organization', 0, PARAM_RAW);
        if (!$filterusers) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterusers);
        } else {
            if (preg_match("/%%FILTER_ORGANIZATION:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterusers;
                return str_replace('%%FILTER_ORGANIZATION:' . $output[1] . '%%', $replace,
                    $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data($selectoption = true, $request){
        global $DB;

        $sql = " SELECT id,fullname
                    FROM {local_costcenter} 
                    WHERE visible = 1 AND parentid = 0 
                    ORDER BY id ASC";

        $organizations = $DB->get_records_sql_menu($sql);
        $organizations =array_replace(array(0=>'Select Organization'),$organizations);
        return $organizations;
    }

    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(false, $request);
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {
        global $USER;
        $depth = $USER->useraccess['currentroleinfo']['depth'];
        if(count($USER->useraccess['currentroleinfo']['contextinfo']) > 1){
            $depth--;
        }

        if(is_siteadmin() || $depth < 2){
            $selectoption = true; 
            $request = array_merge($_POST, $_GET);
            $organizations = $this->filter_data(false, $request); 
            if ((!$this->placeholder || $this->filtertype == 'basic') && COUNT($organizations) > 1) { 
                unset($organizations[0]);
            } 
            $select = $mform->addElement('select', 'filter_organization', null,
            $organizations,
            array('data-select2' => true,
                  'data-maximum-selection-length' => $this->maxlength,
                  'data-action' => 'filterorganization',
                  'data-instanceid' => $this->reportclass->config->id));
            // $select = $mform->addElement('select', 'filter_organization', null, $organizations,
            //             array('data-select2' => 1,
            //                   'data-maximum-selection-length' => $this->maxlength,'onchange' =>'(function(e){ require("block_learnerscript/dependencyfilter").init({name:"organization"}) })(event)'));
            $select->setHiddenLabel(true);
            $mform->setType('filter_organization', PARAM_INT);
        }
    }
}