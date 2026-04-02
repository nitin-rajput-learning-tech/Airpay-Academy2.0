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
 * @subpackage local_request
 */
namespace local_request\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use core_competency\api;
use local_competency\course_competency_statistics;
use core_competency\competency;
use core_competency\course_competency;
use core_competency\external\performance_helper;
use core_competency\external\competency_exporter;
use core_competency\external\course_competency_exporter;
use core_competency\external\course_competency_settings_exporter;
use core_competency\external\user_competency_course_exporter;
use core_competency\external\user_competency_exporter;
use local_competency\external\competency_path_exporter;
use local_competency\external\course_competency_statistics_exporter;
use core_course\external\course_module_summary_exporter;
use core_competency\course_module_competency;
use local_competency\competencyview;


/**
 * Class containing data for course competencies page
 *
 * @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_manage implements renderable, templatable {

    /** @var int $courseid Course id for this page. */
    protected $pendinglist = null;

    /** @var context $context The context for this page. */
    protected $includerightpanel = null;

    /** @var \core_competency\course_competency[] $competencies List of competencies. */
    protected $includeleftcheckbox=null;

    protected  $editcatavailable= null;

    protected $rightpaneltype = null;


    /**
     * Construct this renderable.
     * @param int $courseid The course record for this page.
     */
    public function __construct($pendinglist, $includerightpanel, $includeleftcheckbox, 
                                           $editcatavailable, $rightpaneltype) {
        global $USER;
      
        $this->competencyid = $competencyid;    
        $this->userid = $USER->id;   
        $this->competencycourselist = competencyview::get_competency_courses($competencyid);
        $this->competencyframeworkid=competencyview::get_competencyframeworkid($competencyid);    

    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $USER, $DB;

        $data = new stdClass();
       
        $data->competencies = array();
        $data->competencyframeworkid =  $this->competencyframeworkid;
        $helper = new performance_helper();
        

        $competency = competencyview::competency_record($this->competencyid);

        $context = $helper->get_context_from_competency($competency[$this->competencyid]);
        $compexporter = new competency_exporter($competency[$this->competencyid], array('context' => $context));

        foreach($this->competencycourselist as $competencycourse){
            
            $coursefullname = $DB->get_field('course','fullname',array('id'=>$competencycourse->courseid));
             $onerow = array(                
                'coursefullname' => $coursefullname,
                'competencycourse' =>$competencycourse->courseid,
                 
                //'comppath' => $pathexporter->export($output)
            );
            array_push($data->competencies, $onerow);  
        } // end of foreach          
       
         //if($data->competencies)
        $data->competency =$compexporter->export($output);

        $data->competencies= json_encode($data->competencies);

        return $data; 

    } // end of  function



} // end of class






