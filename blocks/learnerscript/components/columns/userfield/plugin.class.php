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
class plugin_userfield extends pluginbase {

    public function init() {
        $this->fullname = get_string('userfield', 'block_learnerscript');
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
    // Row -> Complet user row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG, $OUTPUT;
        $row->id = isset($row->userid) ? $row->userid : 2;

        $userrecord = $DB->get_record('user',array('id'=>$row->id));
        $userrecord->fullname = '<span class = "userdp_name">';
        $userrecord->fullname .= $OUTPUT->user_picture($userrecord);
        $userrecord->fullname .= html_writer::tag('a', fullname($userrecord),
                                    array('href' =>  $CFG->wwwroot.'/user/profile.php?id='.$row->id.''));
        $userrecord->fullname .= '</span>';
        switch ($data->column) {
            case 'employeeid':
                $userrecord->{$data->column} = $userrecord->open_employeeid;
                break;
            case 'reportingmanager':
                if($userrecord->open_supervisorid > 0){
                    $fields = 'id,firstname,lastname,open_employeeid';
                    $reportingto = $DB->get_record('user', array('id'=>$userrecord->open_supervisorid),$fields);
                    $userrecord->{$data->column} = $reportingto->firstname.' '.$reportingto->lastname.' ('.$reportingto->open_employeeid.')';
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                
                break;
            case 'userstatus':
                $userrecord->{$data->column} = ($userrecord->suspended == 0) ?
                                            '<span class="label label-success">' .  get_string('active') . '</span>' :
                                            '<span class="label label-warning">' . get_string('inactive') . '</span>';
                break;
            case 'designation':
                $userrecord->{$data->column} = ($userrecord->open_designation) ? $userrecord->open_designation : 'NA';
                break;
            case 'level':
                if(!empty($userrecord->open_level)){
                    $userrecord->{$data->column} = $userrecord->open_level;
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            // case 'state':
            //     $userrecord->{$data->column} = ($userrecord->open_state) ? $userrecord->open_state : '--';
            //     break;
            // case 'branch':
            //     $userrecord->{$data->column} = ($userrecord->open_branch) ? $userrecord->open_branch : '--';
            //     break;
            case 'organization':
                $org = $DB->get_field('local_costcenter', 'fullname', array('id'=>$userrecord->open_costcenterid));
                $userrecord->{$data->column} = $org;
                break;
            case 'department':
                $dept = $DB->get_field('local_costcenter', 'fullname', array('id'=>$userrecord->open_departmentid));
                $userrecord->{$data->column} = $dept;
                break;
            case 'subdepartment':
                if(!empty($userrecord->open_subdepartment)){
                    $userrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id'=>$userrecord->open_subdepartment));
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            case 'location':
                $userrecord->{$data->column} = ($userrecord->city) ? $userrecord->city : 'NA';
                break;
            case 'team':
                $userrecord->{$data->column} = ($userrecord->open_team) ? $userrecord->open_team : 'NA';
                break;
            // case 'client':
            //     $userrecord->{$data->column} = ($userrecord->open_client) ? $userrecord->open_client : 'NA';
            //     break;
            case 'hrmsrole':
                $userrecord->{$data->column} = ($userrecord->open_hrmsrole) ? $userrecord->open_hrmsrole : 'NA';
                break;
            case 'zone':
                $userrecord->{$data->column} = ($userrecord->open_zone) ? $userrecord->open_zone : 'NA';
                break;
            case 'region':
                $userrecord->{$data->column} = ($userrecord->open_region) ? $userrecord->open_region : 'NA';
                break;
            case 'grade':
                $userrecord->{$data->column} = ($userrecord->open_grade) ? $userrecord->open_grade : 'NA';
                break;
            default:
                $userrecord->{$data->column} = $userrecord->{$data->column};
        }
        return (isset($userrecord->{$data->column})) ? $userrecord->{$data->column} : 'NA';
    }
}