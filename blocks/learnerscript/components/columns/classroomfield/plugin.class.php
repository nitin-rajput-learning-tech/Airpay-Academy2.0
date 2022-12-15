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
use block_learnerscript\local\reportbase;
class plugin_classroomfield extends pluginbase {

    public function init() {
        $this->fullname = get_string('classroomfield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = array();
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
    // Row -> Complete learningpath row obj.,
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB; 
        $classroomrecord = $DB->get_record('local_classroom',array('id'=>$row->classroomid));

        switch ($data->column) {
            case 'classroomname':
                    $classroomrecord->{$data->column} = $classroomrecord->name;
                break;
            case 'startdate':
                $classroomrecord->{$data->column} = date('d-M-Y', $classroomrecord->startdate);
                break;
            case 'enddate':
                $classroomrecord->{$data->column} = date('d-M-Y', $classroomrecord->enddate);
                break;
            case 'capacity':
                $classroomrecord->{$data->column} = ($classroomrecord->capacity) ? $classroomrecord->capacity : 'NA';
                break;
            case 'classroomorg':
                $classroomrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$classroomrecord->costcenter));
                break;
            case 'classroomdept':
                if($classroomrecord->department > 0){
                    $classroomrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$classroomrecord->department));
                }else{
                   $classroomrecord->{$data->column} = get_string('all'); 
                }
                break;   
            case 'classroom_subdept':
                if(!empty($classroomrecord->subdepartment) && ($classroomrecord->subdepartment != -1)){
                    $classroomrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$classroomrecord->subdepartment));
                }else{
                   $classroomrecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'location':
               if($classroomrecord->instituteid){
                    $location = $DB->get_field('local_location_institutes', 'fullname', array('id' =>$classroomrecord->instituteid));
                    $classroomrecord->{$data->column} = $location;
                }else{
                    $classroomrecord->{$data->column} = 'NA';
                }
                break;
            case 'trainers':
                $sql = "SELECT u.id, CONCAT(u.firstname,' ',u.lastname) as trainer 
                        FROM {local_classroom_trainers} as lct
                        JOIN {user} as u ON u.id = lct.trainerid 
                        WHERE lct.classroomid = :classroomid 
                        AND u.deleted =:deleted AND u.suspended =:suspended ";

                $trainers = $DB->get_records_sql_menu($sql, array('classroomid' => $classroomrecord->id,'deleted' => 0,'suspended' => 0));
                $classroomrecord->{$data->column} = !empty($trainers) ? implode(', ',$trainers) : '--';
                break;
            case 'points':
                $classroomrecord->{$data->column} = ($classroomrecord->open_points) ? $classroomrecord->open_points : 'NA';
                break;
            case 'classroomstatus':
                switch ($classroomrecord->{$data->column}){
                    case 0:
                        $classroomrecord->{$data->column} = 'New';
                        break;
                    case 1:
                        $classroomrecord->{$data->column} = 'Active';
                        break;
                    case 2:
                        $classroomrecord->{$data->column} = 'Hold';
                        break;
                    case 3:
                        $classroomrecord->{$data->column} = 'Cancelled';
                        break;
                    case 4:
                        $classroomrecord->{$data->column} = 'Completed';
                        break;
                }
                break;
            default:
                $classroomrecord->{$data->column} = $classroomrecord->{$data->column};
                break;
        }
       return (isset($classroomrecord->{$data->column})) ? $classroomrecord->{$data->column} : '';
    }
}
