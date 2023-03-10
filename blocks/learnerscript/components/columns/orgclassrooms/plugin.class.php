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

class plugin_orgclassrooms extends pluginbase {

    public function init() {
        $this->fullname = get_string('orgclassrooms', 'block_learnerscript');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = array('orgclassrooms');
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
     /*    echo $costcenterpathconcatsql = (new \local_classroom\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.open_path'); 
        die; */
       
        switch ($data->column) {           
         
            case 'newcount':
           
                $sql = "SELECT count(ilt.id) FROM {local_classroom} AS ilt WHERE  concat('/',ilt.open_path,'/') LIKE :id AND ilt.status = 0 ";
                $params = array( 'id' => '%'.$row->id.'%');
                $newcount = $DB->count_records_sql($sql, $params);
                if($newcount){
                    $row->{$data->column} = $newcount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'activecount':
                $sql = "SELECT count(ilt.id) FROM {local_classroom} AS ilt WHERE  concat('/',ilt.open_path,'/') LIKE :id AND ilt.status = 1 ";
                $params = array( 'id' => '%'.$row->id.'%');
                $activecount = $DB->count_records_sql($sql, $params);
                if($activecount){
                    $row->{$data->column} = $activecount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'holdcount':
                $sql = "SELECT count(ilt.id) FROM {local_classroom} AS ilt WHERE  concat('/',ilt.open_path,'/') LIKE :id AND ilt.status = 2 ";
                $params = array( 'id' => '%'.$row->id.'%');
                $holdcount = $DB->count_records_sql($sql, $params);
                if($holdcount){
                    $row->{$data->column} = $holdcount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'cancelledcount':
                $sql = "SELECT count(ilt.id) FROM {local_classroom} AS ilt WHERE  concat('/',ilt.open_path,'/') LIKE :id AND ilt.status = 3 ";
                $params = array( 'id' => '%'.$row->id.'%');
                $cancelledcount = $DB->count_records_sql($sql, $params);
                if($cancelledcount){
                    $row->{$data->column} = $cancelledcount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'completedcount':
               $sql = "SELECT count(ilt.id) FROM {local_classroom} AS ilt WHERE  concat('/',ilt.open_path,'/') LIKE :id AND ilt.status = 4 ";            
               $params = array( 'id' => '%'.$row->id.'%');
               $completedcount = $DB->count_records_sql($sql, $params);
                if($completedcount){
                    $row->{$data->column} = $completedcount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'usercount':
                $sql = "SELECT count(iltu.id) FROM {local_classroom_users} AS iltu JOIN {local_classroom} as ilt on ilt.id = iltu.classroomid 
                            WHERE concat('/',ilt.open_path,'/') LIKE :id  ";
                $params = array( 'id' => '%'.$row->id.'%');
                $holdcount = $DB->count_records_sql($sql, $params);
                if($holdcount){
                    $row->{$data->column} = $holdcount;
                }else{
                    $row->{$data->column} = 0;
                }     
            break;
            case 'totalclassrooms':
                $sql = "SELECT count(ilt.id) FROM {local_classroom} AS ilt 
                            WHERE  concat('/',ilt.open_path,'/') LIKE :id AND ilt.status In(0, 1,2,3,4) ";
                $params = array( 'id' => '%'.$row->id.'%');
                $totalclassrooms = $DB->count_records_sql($sql, $params);
                if($totalclassrooms){
                    $row->{$data->column} = $totalclassrooms;
                }else{
                    $row->{$data->column} = 0;
                }     
                   
            break;
                         
        }
        return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
    }
}