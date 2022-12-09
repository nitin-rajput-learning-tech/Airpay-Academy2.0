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
class plugin_programfield extends pluginbase {

    public function init() {
        $this->fullname = get_string('programfield', 'block_learnerscript');
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
    // Row -> Complete program row obj.,
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB;

        $sql = "SELECT lp.*, ps.stream
                FROM {local_program} lp
                JOIN {Local_program_stream} ps ON ps.id = lp.stream 
                WHERE lp.id = :programid " ;

        $programrecord = $DB->get_record('local_program',array('id'=>$row->programid));

        $columns = array('programname','stream','programorg','programdept','program_subdept','points');

        switch ($data->column) {
            case 'programname':
                    $programrecord->{$data->column} = $programrecord->name;
                break;
            case 'stream':
                $stream = $DB->get_field('local_program_stream', 'stream', array('id' =>$programrecord->stream));
                $programrecord->{$data->column} = $stream;
                break;                
            case 'programorg':
                $programrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$programrecord->costcenter));
                break;
            case 'programdept':
                if($programrecord->department > 0){
                     $programrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$programrecord->department));
                }else{
                    $programrecord->{$data->column} = get_string('all');
                }
                break;
            case 'program_subdept':
                if($programrecord->subdepartment > 0){
                    $programrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$programrecord->subdepartment));
                }else{
                   $programrecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'points':
                $programrecord->{$data->column} = !empty($programrecord->points) ? $programrecord->points : 'NA';
                break;
            default:
                $programrecord->{$data->column} = $programrecord->{$data->column};
            break;
        }
       return (isset($programrecord->{$data->column})) ? $programrecord->{$data->column} : '';
    }
}
