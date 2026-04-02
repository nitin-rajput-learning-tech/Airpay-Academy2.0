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
class plugin_coursefield extends pluginbase {
    public $courseslist = [];

    public function init() {
        global $DB;
        $this->fullname = get_string('coursefield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = array();
        $this->costcenterarray = array();
        $this->costcenterarray = $DB->get_records_menu('local_costcenter',array());
        $this->coursecategory = $DB->get_records_menu('local_custom_category',array());
        $this->coursetypes = $DB->get_records_menu('local_course_types',array());
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
    // Row -> Complet course row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG; 
        // $courserecord = $DB->get_record('course',array('id'=>$row->courseid)); 
        if (!isset($this->courseslist[$row->courseid])) {
            $courserecord = $DB->get_record('course',array('id'=>$row->courseid));
            $this->courseslist[$row->courseid] = $courserecord;
        } else {
            $courserecord = $this->courseslist[$row->courseid];
        }
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$courserecord->open_path);
        $coursereportid = $DB->get_field('block_learnerscript', 'id', array('type'=>'courseprofile'), IGNORE_MULTIPLE);
        switch ($data->column) { 
            case 'coursename': 
                $checkpermissions = empty($coursereportid) ? false : (new reportbase($coursereportid))->check_permissions($USER->id, $context);
                if($this->report->type == 'courseprofile' || empty($coursereportid) || empty($checkpermissions)){
                    $courserecord->{$data->column} = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courserecord->id.'" />'.$courserecord->fullname.'</a>'; 
                } else if($coursereportid){
                    $courserecord->{$data->column} = '<a href="'.$CFG->wwwroot.'/blocks/learnerscript/viewreport.php?id='.$coursereportid.'&filter_course='.$courserecord->id.'&filter_organization='.$this->reportfilterparams['filter_organization'].'&filter_departments='.$this->reportfilterparams['filter_departments'].'" />'.$courserecord->fullname.'</a>';
                }
                break;
            // case 'coursename':
            //         $courserecord->{$data->column} = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$courserecord->id.'" />'.$courserecord->fullname.'</a>';
            //     break;
            case 'coursecode':
                $courserecord->{$data->column} = $courserecord->shortname;
                break;
            case 'coursecategory':
                $coursecategory = $this->coursecategory[$courserecord->open_categoryid]; //$DB->get_field('local_custom_category', 'fullname', array('id' =>$courserecord->open_categoryid));
                $courserecord->{$data->column} = $coursecategory ? $coursecategory: 'NA';
            break;
            case 'coursevisible':
                $courserecord->{$data->column} = ($courserecord->visible) ?
                                            '<span class="label label-success">' . get_string('active') .'</span>':
                                            '<span class="label label-warning">' . get_string('inactive'). '</span';
            break;                
            case 'courseorg':
                $courseorg = $this->costcenterarray[$org];
                $courserecord->{$data->column} = $courseorg ? $courseorg: 'NA';
                // $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$org));
                break;
            case 'coursedept':
                if($ctr){
                    $coursedept = $this->costcenterarray[$ctr];
                    $courserecord->{$data->column} = $coursedept ? $coursedept: 'NA';
                    // $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$ctr));
                }else{
                   $courserecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'course_subdept':
                if($bu){
                    $course_subdept = $this->costcenterarray[$bu];
                    $courserecord->{$data->column} = $course_subdept ? $course_subdept: 'NA';
                    // $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$bu));
                }else{
                   $courserecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'course_commercialarea':
                if($cu){
                    $course_commercialarea = $this->costcenterarray[$cu];
                    $courserecord->{$data->column} = $course_commercialarea ? $course_commercialarea: 'NA';
                    // $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$cu));
                }else{
                   $courserecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'course_territory':
                if($territory){
                    $course_territory = $this->costcenterarray[$territory];
                    $courserecord->{$data->column} = $course_territory ? $course_territory: 'NA';
                    // $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$territory));
                }else{
                   $courserecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'points':
                $courserecord->{$data->column} = ($courserecord->open_points) ? $courserecord->open_points : 'NA';
                break;
            case 'courseskill':
                if($courserecord->open_skill){
                    $skill = $DB->get_field('local_skill', 'name', array('id' =>$courserecord->open_skill));
                }else{
                    $skill = 'NA';
                }
                $courserecord->{$data->column} = $skill;
                break;
            case 'courselevel':
                if($courserecord->open_level){
                    $level = $DB->get_field('local_course_levels', 'name', array('id' =>$courserecord->open_level));
                }else{
                    $level = 'NA';
                }
                $courserecord->{$data->column} = $level;
                break;
            case 'coursetype':
                if($courserecord->open_identifiedas){
                    $coursetype = $this->coursetypes[$courserecord->open_identifiedas];
                    // $coursetype = $DB->get_field('local_course_types', 'name', array('id' =>$courserecord->open_identifiedas));
                }else{
                    $coursetype = 'NA';
                }
                $courserecord->{$data->column} = $coursetype;
                break;

            default:
                $courserecord->{$data->column} = $courserecord->{$data->column};
            break;
        }
       return (isset($courserecord->{$data->column})) ? $courserecord->{$data->column} : '';
    }
}
