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
        global $DB;
        $this->fullname = get_string('userfield', 'block_learnerscript');
        $this->type = 'advanced';
        $this->form = true;
        $this->reporttypes = array();
        $this->costcenterarray = $DB->get_records_menu('local_costcenter',array());
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

        //$userrecord = $DB->get_record('user',array('id'=>$row->id));
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$row->open_path);
        
        switch ($data->column) {
            case 'employeeid':
                $row->{$data->column} = $row->open_employeeid;
            break;
            case 'reportingmanager':
                if($row->open_supervisorid > 0){
                    $fields = 'id,firstname,lastname,open_employeeid';
                    $reportingto = $DB->get_record('user', array('id'=>$row->open_supervisorid),$fields);
                    $row->{$data->column} = $reportingto->firstname.' '.$reportingto->lastname.' ('.$reportingto->open_employeeid.')';
                }else{
                    $row->{$data->column} = 'NA';
                }
                
                break;
            case 'userstatus':
                $row->{$data->column} = ($row->suspended == 0) ?
                                            '<span class="label label-success">' .  get_string('active') . '</span>' :
                                            '<span class="label label-warning">' . get_string('inactive') . '</span>';
                break;
            case 'designation':
                $row->{$data->column} = ($row->open_designation) ? $row->open_designation : 'NA';
                break;
            case 'level':
                if(!empty($row->open_level)){
                    $row->{$data->column} = $row->open_level;
                }else{
                    $row->{$data->column} = 'NA';
                }
                break;
            case 'state':
                $row->{$data->column} = ($row->open_state) ? $row->open_state : '--';
                break;
            case 'branch':
                $row->{$data->column} = ($row->open_branch) ? $row->open_branch : '--';
                break;
            case 'organization':
                $row->{$data->column} =  $this->costcenterarray[$org];
             /*    $u_org = $DB->get_field('local_costcenter', 'fullname', array('id'=>$org));
                $row->{$data->column} = $u_org; */
                break;
            case 'department':
                if(!empty($ctr)){
                    $row->{$data->column} =  $this->costcenterarray[$ctr];//$DB->get_field('local_costcenter', 'fullname', array('id'=>$ctr));
                }else{
                    $row->{$data->column} = 'All';
                }
                break;
            case 'subdepartment':
                if(!empty($bu)){
                    $row->{$data->column} =  $this->costcenterarray[$bu];//$DB->get_field('local_costcenter', 'fullname', array('id'=>$bu));
                }else{
                    $row->{$data->column} = 'All';
                }
                break;
            case 'depart4level':
                if(!empty($cu)){
                    $row->{$data->column} =  $this->costcenterarray[$cu];//$DB->get_field('local_costcenter', 'fullname', array('id'=>$cu));
                }else{
                    $row->{$data->column} = 'All';
                }
                break;
            case 'depart5level':
                if(!empty($territory)){
                    $row->{$data->column} =  $this->costcenterarray[$territory];//$DB->get_field('local_costcenter', 'fullname', array('id'=>$territory));
                }else{
                    $row->{$data->column} = 'All';
                }
                break;
            case 'open_states':
                if(!empty($row->open_states)){
                    $row->{$data->column} = $this->statesarray[$row->open_states];//$DB->get_field('local_states', 'states_name', array('id'=>$row->open_states));
                }else{
                    $row->{$data->column} = 'NA';
                }
                break;
            case 'open_district':
                if(!empty($row->open_district)){
                    $row->{$data->column} = $this->districtsarray[$row->open_district];//$DB->get_field('local_district', 'district_name', array('id'=>$row->open_district));
                }else{
                    $row->{$data->column} = 'NA';
                }
                break;
            case 'open_subdistrict':
                if(!empty($row->open_subdistrict)){
                    $row->{$data->column} = $this->subdistrictsarray[$row->open_subdistrict];//$DB->get_field('local_subdistrict', 'subdistrict_name', array('id'=>$row->open_subdistrict));
                }else{
                    $row->{$data->column} = 'NA';
                }
                break;
            case 'open_village':
                if(!empty($row->open_village)){
                    $row->{$data->column} = $this->villagesarray[$row->open_village];//$DB->get_field('local_village', 'village_name', array('id'=>$row->open_village));
                }else{
                    $row->{$data->column} = 'NA';
                }
                break;
            default:
                $row->{$data->column} = isset($row->{$data->column}) ? $row->{$data->column} : $row->{$data->column};
            break;
        }
  
        return (isset($row->{$data->column}) && !empty($row->{$data->column})) ? $row->{$data->column} : 'NA';
    }
}
