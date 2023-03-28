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

class plugin_orgusers extends pluginbase {

    public function init() {
        $this->fullname = get_string('orgusers', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('orgusers');
    }

    public function summary($data) {
        return format_string($data->columname);
    }

    public function colformat($data) {
        $align = (isset($data->align)) ? $data->align : '';
        $size = (isset($data->size)) ? $data->size : '';
        $wrap = (isset($data->wrap)) ? $data->wrap : '';
        return array($align, $size, $wrap);
    }

    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB;
      
        switch ($data->column) {
            case 'assignedroles':
                $condition = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
                $costcenterpath = $DB->get_field_sql("SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.id=:organisationid ",array('organisationid'=>$row->costcenterid));
             
                $context = (new \local_assignroles\lib\accesslib())::get_module_context($costcenterpath);
                $sql = " SELECT r.id, 
                            CASE
                                WHEN (r.name != '') THEN r.name
                                ELSE r.shortname
                            END as rolename
                            FROM {role_assignments} AS ra 
                            JOIN {user} AS u on u.id=ra.userid 
                            JOIN {role} r ON r.id = ra.roleid
                            WHERE  ra.contextid=:contextid and u.id = :userid $condition ";
                         
                $userroles = $DB->get_records_sql_menu($sql, array('contextid' => $context->id,'userid' => $row->id)); 
                
                $row->{$data->column} = !empty($userroles) ? implode(', ', $userroles) : '--';
                break;
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}