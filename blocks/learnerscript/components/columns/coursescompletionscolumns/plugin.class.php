<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/** LearnerScript Reports
  * A Moodle block for creating customizable reports
  * @package blocks
  * @subpackage learnerscript
  * @date: 2019
  */
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\reportbase;

class plugin_coursescompletionscolumns extends pluginbase{
	public function init(){
		$this->fullname = get_string('coursescompletionscolumns', 'block_learnerscript');
		$this->type = 'undefined';
		$this->form = true;
		$this->reporttypes = array();
	}
	public function summary($data){
		return format_string($data->columname);
	}
	public function colformat($data){
		$align = (isset($data->align))? $data->align : '';
		$size = (isset($data->size))? $data->size : '';
		$wrap = (isset($data->wrap))? $data->wrap : '';
		return array($align,$size,$wrap);
	}
	public function execute($data,$row,$user,$courseid,$starttime=0,$endtime=0){
		global $DB;
        $context = context_system::instance();

        switch ($data->column) {
          //   case 'designation':
          //       if($row->{$data->column}){
	        	// 	$row->{$data->column} = $row->{$data->column};
	        	// }else{
	        	// 	$row->{$data->column} = '--';
	        	// }
          //       break;
            case 'completionstatus':
                if(!empty($row->completionstatus)){
		            $row->{$data->column} = 'Completed';
		        }else{
		            $row->{$data->column} = 'Not Completed';
		        }
                break;
            case 'completiondate':
                $row->{$data->column} = !empty($row->{$data->column}) ? date('d-m-Y',$row->{$data->column}) : 'NA';
                break;
            case 'skill':
            	if(!empty($row->{$data->column})){
            		$skill = $DB->get_field('local_skill', 'name', array('id'=>$row->skill));
            		$row->{$data->column} = $skill;
            	}else{
            		$row->{$data->column} = 'NA';
            	}
                break;
            // case 'reportingto':
            // 	if(!empty($row->{$data->column})){
            // 		$fields = 'id,firstname,lastname';
            // 		$reportingto = $DB->get_record('user', array('id'=>$row->{$data->column}),$fields);
            // 		$row->{$data->column} = $reportingto->firstname.' '.$reportingto->lastname;
            // 	}else{
            // 		$row->{$data->column} = 'NA';
            // 	}
            //     break;
            default:
            	break;
        }

		return (isset($row->{$data->column})) ? $row->{$data->column} : '--';
	}
}
