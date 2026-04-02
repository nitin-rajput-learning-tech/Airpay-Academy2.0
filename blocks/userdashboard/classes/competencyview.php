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

/**
 * Class for loading/storing competencies from the DB.
 *
 * @package    core_competency
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_competency;
defined('MOODLE_INTERNAL') || die();

use coding_exception;
use context_system;
use lang_string;
use stdClass;
use core_competency\competency;
use core_competency\course_competency; 
use core_competency\persistent; 


require_once($CFG->libdir . '/grade/grade_scale.php');

/**
 * Class for loading/storing competencies from the DB.
 *
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competencyview extends persistent{



    public static function get_the_usercompetency_courses($competencyid, $userid){
        global $DB;

        $competency_courses=$DB->get_records('competency_usercompcourse',
          array('competencyid'=>$competencyid, 'userid'=>$userid));
        
        return $competency_courses;
        
    } // end of function

   
    public static function competency_record($competencyid){
        global $DB;


        $results= $DB->get_records('competency',array('id'=>$competencyid));
        $instances = array();
        foreach ($results as $result) {
            $comp = new competency(0, $result);
           // print_object($comp);
            $instances[$comp->get('id')] = $comp;
        }
        //$results->close(); 

        return $instances;


    } // end of function


     /**
     * List the course_competencies in this course.
     *
     * @param int $courseid The course id
     * @return course_competency[]
     */    
    public static function competencycourse_info($competencyid, $courseid){
    

        global $DB;

        $sql = 'SELECT coursecomp.*
                  FROM {competency_coursecomp} coursecomp
              
                 WHERE coursecomp.courseid = ? and coursecomp.competencyid=?';
        $params = array($courseid, $competencyid);

        $sql .= ' ORDER BY coursecomp.sortorder ASC';
        $results = $DB->get_recordset_sql($sql, $params);

      //  print_object($results);
       // exit;
        $instances = array();
        foreach ($results as $result) {
           // print_object($result);
            array_push($instances, new course_competency(0, $result));
        }
        $results->close(); 
      //  $instances=new course_competency(0, $results);

       return $instances;
    } // end of function


    public static function get_user_competencyframework($userid){
      global $DB;
      
      //-----get  user enrolled courses--------
        $enrolledcourses = enrol_get_users_courses($userid);
        $usercourses = array();
        if($enrolledcourses){
            foreach($enrolledcourses as $enrolledcourse){
                $usercourses[$enrolledcourse->id] = $enrolledcourse->id;
            }
        }
        $imp_usercourses = implode(',',$usercourses);

      // print_object($enrolledcourses);

        $sql ="select cf.* from {competency_coursecomp} uc
              JOIN {competency} as cp ON cp.id=uc.competencyid 
              JOIN {competency_framework} as cf ON cf.id=cp.competencyframeworkid
              WHERE uc.courseid in ($imp_usercourses)  group by cf.id" ;

        //$params= array('userid' =>$userid);
        
        $results=$DB->get_records_sql($sql);
        return $results;
    }


    public static function get_competencyframeworkid($competencyid){
     
      global $DB;    
      $competencyframeworkid=$DB->get_field('competency', 'competencyframeworkid', array('id' => $competencyid));
      return $competencyframeworkid;
    }
      
  

   /* public static function competency_get_record($competencyid, $filters = array(), $sort = '', $order = 'ASC', $skip = 0, $limit = 0) {
        global $DB;

        $orderby = '';
        if (!empty($sort)) {
            $orderby = $sort . ' ' . $order;
        }

        $records = $DB->get_record('competency', $filters, $orderby, '*', $skip, $limit);
        $instances = array();

        foreach ($records as $record) {
            $newrecord = new static(0, $record);
            array_push($instances, $newrecord);
        }
        return $instances;
    }  */
    
    

} // end of classes
