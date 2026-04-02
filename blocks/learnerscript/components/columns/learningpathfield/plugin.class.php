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
class plugin_learningpathfield extends pluginbase {

    public function init() {
        $this->fullname = get_string('learningpathfield', 'block_learnerscript');
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
        global $DB, $CFG; 
        $lpathrecord = $DB->get_record('local_learningplan',array('id'=>$row->learningpathid));
        list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$lpathrecord->open_path);
        switch ($data->column) {
            case 'learningpath_name':
                    $lpathrecord->{$data->column} = $lpathrecord->name;
                break;
            case 'learningpath_code':
                $lpathrecord->{$data->column} = $lpathrecord->shortname; 
                break;               
            case 'learningpath_org':
                $lpathrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$org));
                break;
            case 'learningpath_dept':
                if(!empty($ctr) && ($ctr != -1)){
                    $lpathrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$ctr));
                }else{
                   $lpathrecord->{$data->column} = get_string('all'); 
                }
                break;   
            case 'learningpath_subdept':
                if(!empty($bu) && ($bu != -1)){
                    $lpathrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$bu));
                }else{
                   $lpathrecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'learningpath_commercialarea':
                if(!empty($cu) && ($cu != -1)){
                    $lpathrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$cu));
                }else{
                   $lpathrecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'learningpath_territory':
                if(!empty($territory) && ($territory != -1)){
                    $lpathrecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$territory));
                }else{
                   $lpathrecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'location':
                if($lpathrecord->open_location){
                    $lpathrecord->{$data->column} = $lpathrecord->open_location;
                }else{
                    $lpathrecord->{$data->column} = 'NA';
                }
                break;
            case 'points':
                $lpathrecord->{$data->column} = ($lpathrecord->open_points) ? $lpathrecord->open_points : 'NA';
                break;
            default:
                $lpathrecord->{$data->column} = $lpathrecord->{$data->column};
                break;
        }
       return (isset($lpathrecord->{$data->column})) ? $lpathrecord->{$data->column} : '';
    }
}
