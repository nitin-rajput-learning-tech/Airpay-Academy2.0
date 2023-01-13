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
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$userrecord->open_path);
        // $userrecord->fullname = '<span class = "userdp_name">';
        // $userrecord->fullname .= $OUTPUT->user_picture($userrecord);
        // $userrecord->fullname .= html_writer::tag('a', fullname($userrecord),
        //                             array('href' =>  $CFG->wwwroot.'/user/profile.php?id='.$row->id.''));
        // $userrecord->fullname .= '</span>';
        
        // $userprofilereport = $DB->get_field('block_learnerscript', 'id', array('type'=> 'userprofile'), IGNORE_MULTIPLE);
        // $userrecord = $DB->get_record('user',array('id'=>$row->id));
        // $userrecord->fullname = '<span class = "userdp_name">';
        // $userrecord->fullname .= $OUTPUT->user_picture($userrecord);
        // $checkpermissions = empty($userprofilereport) ? false : (new reportbase($userprofilereport))->check_permissions($USER->id, $context);
        // if ($this->report->type == 'userprofile' || empty($userprofilereport) || empty($checkpermissions)) {
        //     $userrecord->fullname .= html_writer::tag('a', fullname($userrecord),
        //                 array('href' => $CFG->wwwroot.'/user/profile.php?id='.$row->id.''));
        // }else {
        //     $userrecord->fullname .= html_writer::tag('a', fullname($userrecord),
        //                             array('href' => $CFG->wwwroot.'/blocks/learnerscript/viewreport.php?id='.$userprofilereport.'&filter_users='.$row->id.'&filter_organization='.$this->reportfilterparams['filter_organization'].'&filter_departments='.$this->reportfilterparams['filter_departments'].'&filter_subdepartments='.$this->reportfilterparams['filter_subdepartments'].''));
        // }
        // $userrecord->fullname .= '</span>';
        // $userfullname = $userrecord->fullname;
        // if($CFG->messaging){
        //     $userrecord->fullname .= "<sup id='communicate'>";
        //     $userrecord->fullname .= html_writer::start_span('ls icon sendsms', array('id'=>"sendsms_" . $this->reportinstance . "_" . $row->id,
        //                                                  'onclick'=>'(function(e){
        //                                                     require("block_learnerscript/helper").sendmessage({userid: '.$row->id.', reportinstance: ' . $this->reportinstance . '}, \''.$userfullname.'\'); e.stopImmediatePropagation(); }) (event)'));
        //     $userrecord->fullname .= html_writer::end_span();
        //     $userrecord->fullname .='</sup>';
        // }



        switch ($data->column) {
            case 'employeeid':
                $userrecord->{$data->column} = $row->open_employeeid;
            break;
            case 'reportingmanager':
                if($userrecord->open_supervisorid > 0){
                    $fields = 'id,firstname,lastname,open_employeeid';
                    $reportingto = $DB->get_record('user', array('id'=>$row->open_supervisorid),$fields);
                    $userrecord->{$data->column} = $reportingto->firstname.' '.$reportingto->lastname.' ('.$reportingto->open_employeeid.')';
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                
                break;
            case 'userstatus':
                $userrecord->{$data->column} = ($row->suspended == 0) ?
                                            '<span class="label label-success">' .  get_string('active') . '</span>' :
                                            '<span class="label label-warning">' . get_string('inactive') . '</span>';
                break;
            case 'designation':
                $userrecord->{$data->column} = ($row->open_designation) ? $row->open_designation : 'NA';
                break;
            case 'level':
                if(!empty($row->open_level)){
                    $userrecord->{$data->column} = $row->open_level;
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            case 'state':
                $userrecord->{$data->column} = ($row->open_state) ? $row->open_state : '--';
                break;
            case 'branch':
                $userrecord->{$data->column} = ($row->open_branch) ? $row->open_branch : '--';
                break;
            case 'organization':
                $u_org = $DB->get_field('local_costcenter', 'fullname', array('id'=>$org));
                $userrecord->{$data->column} = $u_org;
                break;
            case 'department':
                if(!empty($ctr)){
                    $userrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id'=>$ctr));
                }else{
                    $userrecord->{$data->column} = 'All';
                }
                break;
            case 'subdepartment':
                if(!empty($bu)){
                    $userrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id'=>$bu));
                }else{
                    $userrecord->{$data->column} = 'All';
                }
                break;
            // case 'location':
            //     $userrecord->{$data->column} = ($row->city) ? $row->city : 'NA';
            //     break;
            // case 'team':
            //     $userrecord->{$data->column} = ($row->open_team) ? $row->open_team : 'NA';
            //     break;
            // case 'client':
            //     $userrecord->{$data->column} = ($row->open_client) ? $row->open_client : 'NA';
            //     break;
            // case 'hrmsrole':
            //     $userrecord->{$data->column} = ($row->open_hrmsrole) ? $row->open_hrmsrole : 'NA';
            //     break;
            // case 'zone':
            //     $userrecord->{$data->column} = ($row->open_zone) ? $row->open_zone : 'NA';
            //     break;
            // case 'region':
            //     $userrecord->{$data->column} = ($row->open_region) ? $row->open_region : 'NA';
            //     break;
            // case 'grade':
            //     $userrecord->{$data->column} = ($row->open_grade) ? $row->open_grade : 'NA';
            //     break;            
            // case 'country':
            //     $userrecord->{$data->column} = ($row->country) ? $row->country  : '--';
            //     break;
            case 'depart4level':
                if(!empty($cu)){
                    $userrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id'=>$cu));
                }else{
                    $userrecord->{$data->column} = 'All';
                }
                break;
            case 'depart5level':
                if(!empty($territory)){
                    $userrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id'=>$territory));
                }else{
                    $userrecord->{$data->column} = 'All';
                }
                break;
            case 'open_states':
                if(!empty($row->open_states)){
                    $userrecord->{$data->column} = $DB->get_field('local_states', 'states_name', array('id'=>$bu));
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            case 'open_district':
                if(!empty($row->open_district)){
                    $userrecord->{$data->column} = $DB->get_field('local_district', 'district_name', array('id'=>$bu));
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            case 'open_subdistrict':
                if(!empty($row->open_subdistrict)){
                    $userrecord->{$data->column} = $DB->get_field('local_subdistrict', 'subdistrict_name', array('id'=>$bu));
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            case 'open_village':
                if(!empty($row->open_village)){
                    $userrecord->{$data->column} = $DB->get_field('local_village', 'village_name', array('id'=>$bu));
                }else{
                    $userrecord->{$data->column} = 'NA';
                }
                break;
            default:
                $userrecord->{$data->column} = isset($row->{$data->column}) ? $row->{$data->column} : $row->{$data->column};
            break;
        }
        return (isset($userrecord->{$data->column})) ? $userrecord->{$data->column} : 'NA';
    }
}
