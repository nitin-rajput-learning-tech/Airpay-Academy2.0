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

class plugin_trainers extends pluginbase
{

    public function init()
    {
        $this->form = false;
        $this->unique = true;
        $this->singleselection = true;
        $this->placeholder = true;
        $this->maxlength = 0;
        $this->fullname = get_string('filtertrainers', 'block_learnerscript');
        $this->reporttypes = array();
    }

    public function summary($data)
    {
        return get_string('filtertrainers_summary', 'block_learnerscript');
    }

    public function execute($finalelements, $data, $filters)
    {
        $filterlp = optional_param('filter_trainers', null, PARAM_INT);
        if (!$filterlp) {
            return $finalelements;
        }

        if ($this->report->type != 'sql') {
            return array($filterlp);
        } else {
            if (preg_match("/%%FILTER_TRAINERS:([^%]+)%%/i", $finalelements, $output)) {
                $replace = ' AND ' . $output[1] . ' = ' . $filterlp;
                return str_replace('%%FILTER_TRAINERS:' . $output[1] . '%%', $replace, $finalelements);
            }
        }
        return $finalelements;
    }

    public function print_filter(&$mform, $selectoption = true)
    {
        global $DB;
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'trainer'));
        $sql = "SELECT u.id, CONCAT(u.firstname,' ',u.lastname) as trainername 
                    FROM {role_assignments} AS ra
                    JOIN {user} AS u on u.id=ra.userid
                    WHERE 1=1 AND ra.roleid=:roleid ";       

        $params = array();
        $params['roleid'] = $roleid;
        $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname = 'u.open_path');
        if (is_siteadmin()) {
            $sql .= "";
        } else {
            $sql .= $costcenterpathconcatsql;
        }
        $sql .= " ORDER BY u.firstname ASC ";

        $trainers = $DB->get_records_sql_menu($sql, $params);

        $selecttrainer = array();
        if ($selectoption) {
            $selecttrainer[null] = get_string('filtertrainers', 'block_learnerscript');
        }

        $trainerslist = $selecttrainer + $trainers;

        $array = array('data-select2' => true, 'data-maximum-selection-length' => $this->maxlength);
        $select = $mform->addElement('select', 'filter_trainers', null, $trainerslist, $array);

        $mform->setType('filter_trainers', PARAM_INT);
    }
}
