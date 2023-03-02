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

class plugin_departmentoverviewcolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('departmentoverviewcolumns', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('departmentoverview');
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

    // Data -> Plugin configuration data.
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB;
        
        switch ($data->column) {
            case 'coursecount':
                $sql = "SELECT count(c.id) 
                            FROM {course} as c 
                            WHERE concat('/',c.open_path,'/') LIKE :costcenterpath ";   
                $params['costcenterpath'] = '%/'.$row->path.'/%';                         
               
                $coursecount = $DB->count_records_sql($sql, $params);
                if($coursecount){
                    $row->{$data->column} = $coursecount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'iltcount':               
                $sql = "SELECT count(ilt.id) 
                            FROM {local_classroom} as ilt 
                            WHERE concat('/',ilt.open_path,'/') LIKE :costcenterpath ";
                $params['costcenterpath'] = '%/'.$row->path.'/%';   
                $iltcount = $DB->count_records_sql($sql, $params);
                if($iltcount){
                    $row->{$data->column} = $iltcount;
                }else{
                    $row->{$data->column} = 0;
                }   
                break;
            case 'programcount':
                $sql = "SELECT count(lp.id)
                            FROM {local_program} as lp 
                            WHERE concat('/',lp.open_path,'/') LIKE :costcenterpath ";
                $params['costcenterpath'] = '%/'.$row->path.'/%';   
                $programcount = $DB->count_records_sql($sql, $params);
                if($programcount){
                    $row->{$data->column} = $programcount;
                }else{
                    $row->{$data->column} = 0;
                }  
                break;
            case 'plancount':
                $sql = "SELECT count(plan.id) 
                            FROM {local_learningplan} as plan 
                            WHERE concat('/',plan.open_path,'/') LIKE :costcenterpath ";
                $params['costcenterpath'] = '%/'.$row->path.'/%';   
                $plancount = $DB->get_field_sql($sql, $params);
                if($plancount){
                    $row->{$data->column} = $plancount;
                }else{
                    $row->{$data->column} = 0;
                }  
                break;   
            case 'activeusers':
                $sql = "SELECT count(u.id) 
                            FROM {user} as u 
                            WHERE concat('/',u.open_path,'/') LIKE :costcenterpath AND u.suspended=0 AND u.deleted=0 ";
                $params['costcenterpath'] = '%/'.$row->path.'/%';  
                $activeusers = $DB->count_records_sql($sql, $params);
                if($activeusers){
                    $row->{$data->column} = $activeusers;
                }else{
                    $row->{$data->column} = 0;
                }     
                break;           
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--'; 
        
    }

}
