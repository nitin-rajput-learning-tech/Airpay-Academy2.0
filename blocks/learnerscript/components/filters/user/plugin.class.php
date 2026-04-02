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

class plugin_user extends pluginbase
{

    public function init()
    {
        $this->form = false;
        $this->unique = true;
        $this->fullname = get_string('filteruser', 'block_learnerscript');
        $this->reporttypes = array('sql');
    }

    public function summary($data)
    {
        return get_string('filteruser_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data)
    {
        $filteruser = optional_param('filter_user', 0, PARAM_INT);
        if (!$filteruser) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filteruser);
        } else {
            if (preg_match("/%%FILTER_COURSEUSER:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filteruser;
                return str_replace('%%FILTER_COURSEUSER:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }
    public function filter_data($selectoption = true)
    {
        global $DB, $USER;

        $params = array();
        $sql = "SELECT u.id, CONCAT(u.firstname,' ',u.lastname) as employeename 
                FROM {user} u 
                WHERE u.deleted = :deleted AND u.suspended = :suspended AND id > 2 ";

        $params['deleted'] = 0;
        $params['suspended'] = 0;


        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path', null, 'lowerandsamepath');

        if (is_siteadmin()) {
            $sql .= "";
        } else {
            $sql .= $costcenterpathconcatsql;
        }
        $sql .= " ORDER BY u.firstname ASC ";
        $users = $DB->get_records_sql_menu($sql, $params);

        $selectusersopt = array();
        $selectusersopt[0] = get_string('filter_user', 'block_learnerscript');

        $userslist = $selectusersopt + $users;

        return $userslist;
    }

    public function selected_filter($selected)
    {
        $filterdata = $this->filter_data();
        return $filterdata[$selected];
    }

    public function print_filter(&$mform)
    {

        $useroptions = $this->filter_data();
        $array = array('data-select2' => 1, 'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_user', get_string('user'), $useroptions, $array);
        $select->setHiddenLabel(true);
        $mform->setType('filter_user', PARAM_INT);
    }
}
