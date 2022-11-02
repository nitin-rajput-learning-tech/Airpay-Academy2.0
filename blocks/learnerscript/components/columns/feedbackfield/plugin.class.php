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
class plugin_feedbackfield extends pluginbase {

    public function init() {
        $this->fullname = get_string('feedbackfield', 'block_learnerscript');
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
    // Row -> Complete feedback row obj.,
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB; 
        $feedbackrecord = $DB->get_record('local_evaluations',array('id'=>$row->feedbackid));

        switch ($data->column) {
            case 'feedbackname':
                    $feedbackrecord->{$data->column} = $feedbackrecord->name;
                break;
            case 'feedbacktype':
                if($feedbackrecord->type == 1){
                    $feedbackrecord->{$data->column} = 'Feedback';
                }else{
                    $feedbackrecord->{$data->column} = 'Survey';
                }
                break;
            case 'evaluationtype':
                if($feedbackrecord->evaluationmode == 'SE'){
                    $feedbackrecord->{$data->column} = 'Self';
                }else{
                    $feedbackrecord->{$data->column} = 'Supervisor';
                }
                break;
            case 'feedbackorg':
                $feedbackrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$feedbackrecord->costcenterid));
                break;
            case 'feedbackdept':
                if($feedbackrecord->departmentid){
                    $feedbackrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$feedbackrecord->departmentid));
                }else{
                   $feedbackrecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'allowanswersfrom':
                $feedbackrecord->{$data->column} = $feedbackrecord->timeopen ? \local_costcenter\lib::get_userdate('d m Y H:i', $feedbackrecord->timeopen) : 'NA';
                break;
            case 'allowanswersto':
                $feedbackrecord->{$data->column} = $feedbackrecord->timeclose ? \local_costcenter\lib::get_userdate('d m Y H:i', $feedbackrecord->timeclose) : 'NA';
                break;
            default:
                $feedbackrecord->{$data->column} = $feedbackrecord->{$data->column};
                break;
        }
       return (isset($feedbackrecord->{$data->column})) ? $feedbackrecord->{$data->column} : '';
    }
}
