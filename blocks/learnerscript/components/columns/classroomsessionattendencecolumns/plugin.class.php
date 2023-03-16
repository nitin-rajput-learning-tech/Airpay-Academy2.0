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

class plugin_classroomsessionattendencecolumns extends pluginbase {

    public function init() {
        $this->fullname = get_string('classroomsessionattendencecolumns', 'block_learnerscript');
        $this->type = 'undefined';
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
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG,$OUTPUT;
        $classroomrecord = $DB->get_record('local_classroom_sessions',array('id'=>$row->sessionid)); 
        //print_r($row);

            switch ($data->column) {
            case 'sessionname':
                    $classroomrecord->{$data->column} = $classroomrecord->name;
                break;                      
            case 'trainer':
                    $sql = "SELECT u.id, CONCAT(u.firstname,' ',u.lastname) as trainer 
                        FROM {local_classroom_sessions} as lcs
                        JOIN {user} as u ON u.id = lcs.trainerid 
                        WHERE lcs.classroomid = :classroomid 
                        AND u.deleted =:deleted AND u.suspended =:suspended ";

                    $trainer = $DB->get_records_sql_menu($sql, array('classroomid' => $row->classroomid,'deleted' => 0,'suspended' => 0));
                    $classroomrecord->{$data->column} = !empty($trainer) ? implode(', ',$trainer) : '--';
                break;
            
            case 'attendendencestatus':
                     $classroomsessionrecord = $DB->get_record('local_classroom_attendance',array('sessionid'=>$row->sessionid));
                    if ($row->attendendencestatus == 2) {
                       $classroomrecord->{$data->column} = $row->sessionid > 0 ? "Absent" : "A";
                    } else if ($row->attendendencestatus == 1) {
                        $classroomrecord->{$data->column} = $row->sessionid > 0 ? "Present" : "P";
                    } else {
                       $classroomrecord->{$data->column} = $row->sessionid > 0 ? "Not yet given" : "NY";
                    }
                break;
            case 'timestart':
                    if(!isset($row->timestart) && isset($data->subquery)){
                        $timestart =  $DB->get_field_sql($data->subquery);
                    }else{
                        $timestart = $row->{$data->column};
                    }
                    $classroomrecord->{$data->column} = !empty($timestart) ? $timestart : '--';            
             break;
            case 'timefinish':
                    if(!isset($row->timefinish) && isset($data->subquery)){
                        $timefinish =  $DB->get_field_sql($data->subquery);
                    }else{
                        $timefinish = $row->{$data->column};
                    }
                    $classroomrecord->{$data->column} = !empty($timefinish) ? $timefinish : '--';            
            default:
                $classroomrecord->{$data->column} = $classroomrecord->{$data->column};
                break;
        }
       return (isset($classroomrecord->{$data->column})) ? $classroomrecord->{$data->column} : '';
    }
    

}
