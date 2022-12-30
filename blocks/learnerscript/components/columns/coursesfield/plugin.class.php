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
class plugin_coursesfield extends pluginbase {

    public function init() {
        $this->fullname = get_string('coursefield', 'block_learnerscript');
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
    // Row -> Complet course row c->id, c->fullname, etc...
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        global $DB, $CFG; 
        $courserecord = $DB->get_record('course',array('id'=>$row->courseid));
        $taginstancerecord = $DB->get_record('tag_instance',array('itemid'=>$row->courseid)); 
        $coursereportid = $DB->get_field('block_learnerscript', 'id', array('type'=>'courseprofile'), IGNORE_MULTIPLE);
        switch ($data->column) { 
            case 'coursename': 
                $courserecord->{$data->column} = $courserecord->fullname;
            break;
            case 'coursecode':
                $courserecord->{$data->column} = $courserecord->shortname;
            break;
            case 'coursecategory':
                $courserecord->{$data->column} = $DB->get_field('course_categories', 'name', array('id' =>$courserecord->category));
            break;
            case 'coursetype':
                $courserecord->{$data->column} = ($courserecord->course_type); 
            break;
            case 'coursesummary':
                $courserecord->{$data->column} = ($courserecord->summary); 
            break;
            case 'coursepoints':
                $courserecord->{$data->column} = ($courserecord->open_points)? ($courserecord->open_points):'NA'; 
            break;
            case 'coursecost':
                $courserecord->{$data->column} = ($courserecord->open_cost); 
            break;
            case 'coursedepartment':
                if($courserecord->open_departmentid){
                    $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$courserecord->open_departmentid));
                }else{
                   $courserecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'coursesubdepartment':
                if($courserecord->open_subdepartment){
                    $courserecord->{$data->column} = $DB->get_field('local_costcenter', 'fullname', array('id' =>$courserecord->open_subdepartment));
                }else{
                   $courserecord->{$data->column} = get_string('all'); 
                }
                break;
            case 'courselearningformat':
                $courserecord->{$data->column} = ($courserecord->open_learningformat); 
            break;
            case 'coursecontentprovider':
                $courserecord->{$data->column} = ($courserecord->open_contentvendor); 
            break;
            case 'courseformat':
                $courserecord->{$data->column} = ($courserecord->format); 
            break;
            case 'courseselfenrole':
                $courserecord->{$data->column} = ($courserecord->selfenrole); 
            break;
            case 'courseevidence':
                $courserecord->{$data->column} = ($courserecord->need_evidence); 
            break;
             case 'coursestartdate':
                $courserecord->{$data->column} = ($courserecord->startdate); 
            break;
            case 'courseenddate':
                $courserecord->{$data->column} = ($courserecord->enddate); 
            break;
            case 'customdurationinminutes':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='duration_in_minutes'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):''; 
            break;
            case 'customlastmodified':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='last_modifie'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):''; 
            break;
            case 'customurl':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='url'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';  
            break;
            case 'customvalidfor':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='valid_for'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):''; 
            break;
            case 'customfieldnumber':
               $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='number'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customendoflife':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='end_of_life'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customvalidforcomplaince':
                 $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='valid_for_compliance_until'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customcostprice':
            $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='costprice'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customcostpricecurrency':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='costpricecurrency'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customcostpricemarkup':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='costpricemarkup'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customsellingprice':
               $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='sellingprice'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customsellingpricecurrency':
               $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='sellingpricecurrency'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'customnoofquestions':
                 $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='numberofquestions'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):''; 
            break;
            case 'customacclaimtemplateid':
                $sql="Select cd.value FROM {customfield_data} as cd JOIN {customfield_field} as cf ON cf.id=cd.fieldid JOIN {course} as c ON cd.instanceid=c.id WHERE c.id='".$courserecord->id."' AND cf.shortname='acclaim_templateid'";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'coursetags':
            $sql="Select GROUP_CONCAT(t.name) as Tags from {tag} as t JOIN {tag_instance} as ti ON t.id = ti.tagid JOIN {course} as c ON c.id=ti.itemid JOIN {course_categories} as cc ON cc.id =c.category WHERE c.id='".$courserecord->id."' AND ti.component = 'local_courses' Group By c.id";
            $courserecord->{$data->column}= $DB->get_field_sql($sql, array())?$DB->get_field_sql($sql, array()):'';
            break;
            case 'coursestatus':
                $courserecord->{$data->column} = ($courserecord->open_status); 
            break;
            case 'coursevendor':
                $courserecord->{$data->column} = ($courserecord->open_vendor); 
            break;
            case 'coursenoofchildren':
                $courserecord->{$data->column} = ($courserecord->open_noofchildren); 
            break;                
            default:
                $courserecord->{$data->column} = $courserecord->{$data->column};
            break;
        }
       return (isset($courserecord->{$data->column})) ? $courserecord->{$data->column} : '';
    }
}
