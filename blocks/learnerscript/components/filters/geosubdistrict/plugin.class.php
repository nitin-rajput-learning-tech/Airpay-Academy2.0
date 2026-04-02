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

class plugin_geosubdistrict extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->filtertype = 'custom'; 
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'geosubdistrict') {
                    $this->filtertype = 'basic';
                }
            }
        }
        $this->fullname = get_string('filtergeosubdistrict', 'block_learnerscript');
        $this->reporttypes = array('sql','coursesoverview');
    }

    public function summary($data) {
        return get_string('filtergeosubdistrict_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data) {

        $filterusers = optional_param('filter_geosubdistrict', 0, PARAM_RAW);
        if (!$filterusers) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterusers);
        } else {
            if (preg_match("/%%FILTER_GEOSUBDISTRICT:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterusers;
                return str_replace('%%FILTER_GEOSUBDISTRICT:' . $output[1] . '%%', $replace,
                    $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data($selectoption = true, $request){
        global $DB;
        $condition = '';
        if(!is_siteadmin()){
            $condition = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path');
        }
        $sql = " SELECT lsd.id, lsd.subdistrict_name
                    FROM {local_subdistrict} AS lsd
                    JOIN {local_costcenter} AS lc ON lc.id = lsd.costcenterid
                    WHERE 1=1 {$condition}
                    ORDER BY lsd.id ASC ";

        $geosubdistricts = $DB->get_records_sql_menu($sql);
        $geosubdistricts =array_replace(array(0=>get_string('selectsubdistrict', 'usersprofilefields_village')),$geosubdistricts);
        ksort($geosubdistricts);
        return $geosubdistricts;
    }

    public function selected_filter($selected, $request = array()) {
        $filterdata = $this->filter_data(false, $request);
        return $filterdata[$selected];
    }

    public function print_filter(&$mform) {
        global $USER;

        $selectoption = true;
        $request = array_merge($_POST, $_GET);
        $geosubdistricts = $this->filter_data(false, $request);
        if ((!$this->placeholder || $this->filtertype == 'basic') && COUNT($geosubdistricts) > 1) {
            unset($geosubdistricts[0]);
        }
        $select = $mform->addElement('select', 'filter_geosubdistrict', null,
        $geosubdistricts,
        array('data-select2' => true,
              'data-maximum-selection-length' => $this->maxlength,
              'data-action' => 'filtergeosubdistrict',
              'data-instanceid' => $this->reportclass->config->id));
        $select->setHiddenLabel(true);
        $mform->setType('filter_geosubdistrict', PARAM_INT);

    }
}