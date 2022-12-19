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
 * Class containing data for course competencies page
 *
 * @package    local_search
 * @copyright  2018 hemalathacarun <hemalatha@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_search\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;
use context_system;
use context_course;
use core_component;

/**
* Class containing data for course competencies page
*
* @copyright  2018 hemalatha c arun <hemalatha@eabyas.in>
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
    class searchlib {

    static public $includesobj;

    /* To hold page number */
    static public $page;

    /* To hold search text */
    static public $search;

    /* To hold category/department id */
    static public $category;

    /* To hold enrolltype */
    static  public  $enrolltype;

    /* To hold enrolltype */
    static  public  $sortid;


    public function __set($variable, $value){
        // self::$data[$variable] = $value;
         self::$variable = $value;

    } // end of set function


    public static function convert_urlobject_intoplainurl($course){
        $coursefileurl = self::$includesobj->course_summary_files($course);
        if(is_object($coursefileurl)){
            $coursefileurl=$coursefileurl->out();
        }
        return $coursefileurl;
    }  // end of convert_urlobject_intoplainurl function


    public static function format_thestring($stringcontent){
        $trimedcontent  = strip_tags(html_entity_decode(clean_text($stringcontent)),array('overflowdiv' => false, 'noclean' => false, 'para' => false));
        return $trimedcontent;
    } // end of formatthestring


    public static function format_thesummary($summary){
        if(!empty($summary)){
            $trimmedsummary =  strip_tags(html_entity_decode(clean_text($summary)));
        }else{
            $trimmedsummary = '<p class="alert alert-info">'.get_string('descriptionisnotavailable','local_search').'</p>';
        }
        return $trimmedsummary;
    } // end of format_thesummary


    public static function get_thedateformat($date){
        if($date){
            $formatted_date = date('d M', $date);
        }else{
            $formatted_date ='N/A';
        }
        return $formatted_date;
    } // end of get_thedateformat

    public static function trim_theband($bands){
        if(empty($bands)){
            $trimmedbands="N/A";
        }else if($bands!='-1'){
            $trimmedbands = strip_tags(clean_text($bands));
        }else{
            $trimmedbands= get_string('all','local_search');
        }
        return $trimmedbands;
    } // trim_theband

    public static function to_display_description($description){
        if(empty($description)){
            $description= '<span class="alert alert-info">'.get_string('descriptionisnotavailable','local_search').'</span>';
        }
        return strip_tags(html_entity_decode(clean_text($description)),array('overflowdiv' => false, 'noclean' => false, 'para' => false));
    } // end of to_display_description function
} // end of class






