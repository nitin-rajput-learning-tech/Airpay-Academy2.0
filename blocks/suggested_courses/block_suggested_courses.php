<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This courselister is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This courselister is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this courselister.  If not, see <http://www.gnu.org/licenses/>.


 * Suggested Courses list block.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage block_suggested_courses
 */

use block_suggested_courses\output\blockview;
use block_suggested_courses\plugin;

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_suggested_courses
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms
 * @subpackage block_suggested_courses
 */
final class block_suggested_courses extends block_base {

    /**
     * Initialize the block title
     * @throws coding_exception
     */
    public function init() {
        $this->title = get_string('blockname', plugin::COMPONENT);
    }
    /**
     * Generate the block content
     * @return stdClass|null
     * @throws coding_exception
     */
    public function get_content() {

        global $OUTPUT, $PAGE,$CFG, $COURSE,$DB,$USER;

        $systemcontext = context_system::instance();

        $this->config->coursetype = 1;
        //$PAGE->requires->js_call_amd('local_catalog/courseinfo', 'load', array());
        $PAGE->requires->js_call_amd('local_search/courseinfo', 'load', array());
        $PAGE->requires->js_call_amd('local_skillrepository/skillsInterested', 'load', array());
        // $PAGE->requires->js('/blocks/user_bookmarks/js/javascript_file.js');
        if (isloggedin() and ($this->content === null)) {
            if(get_user_preferences('auth_forcepasswordchange') || $COURSE->id != 1){
                return (object)[
                    'text' => '',
                    'footer' => ''
                ];
            }        
           
            /** @var stdClass $config */
           
            $returnoutput='';

            $tabs=array();
            $filterdata = json_encode(array());

            $options = array('targetID' => 'mysuggestedcourses','perPage' => '3', 'cardClass' => 'pl-0 pr-4 col-md-4 col-sm-6', 'viewType' => 'card');

            $dataoptions['search_query'] = '' ;
            $options['methodName']='block_suggested_courses_get_courses';
            $options['templateName']='block_suggested_courses/viewmysuggestedcourses';
            
            $carddataoptions = json_encode($dataoptions);
            $cardoptions = json_encode($options);
            $cardparams = array(
                'targetID' => 'mysuggestedcourses',
                'options' => $cardoptions,
                'dataoptions' => $carddataoptions,
                'filterdata' => $filterdata,
                
            );   

            $skills_interested = $DB->get_record('local_interested_skills', array('usercreated'=>$USER->id), $fields = 'id,count(*)', $strictness = IGNORE_MISSING);

            if ((!empty($skills_interested)) && $skills_interested->id > 0) {
                $skills_interested_id = $skills_interested->id;
            } else {
                $skills_interested_id = 0;
            }
               
            $cardparams['filtertype']= 'mysuggestedcourses';
            $tabs[] = array('active' => 'active','type' => 'mysuggestedcourses', 'filterform' => array(), 'canfilter' => true, 'show' => '','name' => 'selectedcourses','coursetype'=>'suggestedcourses');

            $fncardparams=$cardparams;
          
            if($tabs){   
                $cardparams = $fncardparams+array(
                        'tabs' => $tabs,
                        'contextid' => $systemcontext->id,
                        'plugintype' => 'block',
                        'plugin_name' =>'suggested_courses',
                        'cfg' => $CFG,
                    'skillsinterestedid' => $skills_interested_id );
               $returnoutput.=$OUTPUT->render_from_template('block_suggested_courses/block_suggested_courses', $cardparams);
            }

            $this->content = (object)[
                'text' => $returnoutput,
                'footer' => ''
            ];
        }
        return $this->content;
    }


}
