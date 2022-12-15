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

class plugin_usergroup extends pluginbase {

    public function init() {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = false;
        $this->maxlength = 0;
        $this->fullname = get_string('usergroup', 'block_learnerscript');
        $this->reporttypes = array();
        if (!empty($this->reportclass->basicparams)) {
            foreach ($this->reportclass->basicparams as $basicparam) {
                if ($basicparam['name'] == 'usergroup') {
                    $this->filtertype = 'basic';
                }
            }
        }        
    }

    public function summary($data) {
        return get_string('usergroup_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters) {
        $usergroup = isset($filters['filter_usergroup']) ? $filters['filter_usergroup'] : null;
        $filterusergroup = optional_param('filter_usergroup', $usergroup, PARAM_INT);
        if (!$filterusergroup) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterusergroup);
        } else {
            if (preg_match("/%%FILTER_USERGROUP:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterusergroup;
                return str_replace('%%FILTER_USERGROUP:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function filter_data(){
        global $DB, $USER;
        $context = context_system::instance();
        if($this->reportclass->basicparams){
            $basicparams = array_column($this->reportclass->basicparams, 'name');
            if (has_capability('local/costcenter:manage_ownorganization', $context) && !is_siteadmin()) {
                $deptorgid = $USER->open_costcenterid;
            } else {
                if ($basicparams[0] == 'organization') {
                    $orgoptions = $DB->get_records_sql_menu("SELECT id FROM {local_costcenter} WHERE depth = 1 ORDER BY id ASC"); 
                    $orgids = array_keys($orgoptions);
                    if (empty($request['filter_organization'])) {
                        $deptorgid = array_shift($orgids);
                    } else {
                        $deptorgid = $request['filter_organization'];
                    }
                }else {
                    $deptorgid = null;
                } 
            }
        } else {
            $deptorgid = null;
        }
        $sql = "SELECT ch.id, ch.name
                FROM {cohort} ch 
                JOIN {local_groups} lg ON lg.cohortid = ch.id
                WHERE 1 = 1 AND ch.visible = 1 ";
        if (!empty($deptorgid)) {
            $sql .= " AND lg.costcenterid = " . $deptorgid;
        } else {
            $systemcontext = context_system::instance();
            if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
                $sql .= " ";
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
                $sql .= " AND lg.costcenterid = $USER->open_costcenterid ";
            }else if(!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)){
                $sql .= " AND lg.costcenterid = $USER->open_costcenterid 
                        AND lg.departmentid = $USER->open_departmentid";
            }
        }
        $usergroups = $DB->get_records_sql_menu($sql);

        $selectoption = array();
        $selectoption[0] = get_string('selectusergroup', 'block_learnerscript');

        $usergrouplist = $selectoption + $usergroups;

        return $usergrouplist;
    }

    public function selected_filter($selected) {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform, $selectoption = true) {
        
        $usergrouplist = $this->filter_data();

        $array = array('data-select2'=>true, 'data-action' => 'tagfilters','data-maximum-selection-length' => $this->maxlength, 'data-placeholder' => get_string('selectusergroup', 'block_learnerscript'));
        $select = $mform->addElement('select', 'filter_usergroup', null, $usergrouplist, $array); 
      //  $select->setMultiple(true);
        $mform->setType('filter_usergroup', PARAM_RAW);
    }

}
