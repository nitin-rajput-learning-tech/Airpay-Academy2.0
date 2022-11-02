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
 * Classroom View
 *
 * @package    block_quick_navigation
 * @copyright  2017 Syed Hameed Ullah <hameed@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_quick_navigation\output;

use html_writer;
use core_component;

class quick_links{
    public function display_quick_navigation_links(){
        global $CFG;
        $core_component = new core_component();
        $local_pluginlist = $core_component::get_plugin_list('local');

        $out = html_writer::start_tag("div", array("class" => "quick_nav_container"));
            $out .= html_writer::start_tag("ul", array("class" => "quick_nav list-inline"));
            $pluginnavs = array();
            foreach($local_pluginlist as $key => $local_pluginname){
                if(file_exists($CFG->dirroot.'/local/'.$key.'/lib.php')){
                    require_once($CFG->dirroot.'/local/'.$key.'/lib.php');
                    $functionname = 'local_'.$key.'_quicklink_node';
                    if(function_exists($functionname)){
                        $data = $functionname();
                        foreach($data as  $key => $val){
                            $pluginnavs[$key][] = $val;
                        }
                    }
                }
            }
            ksort($pluginnavs);
            $data = array();
            foreach($pluginnavs as $pluginnav){
                foreach($pluginnav  as $key => $value){
                    $data[] = $value;
                }
            }
            $users = $data[0];
            $feedbacks = $data[1];
            $courses = $data[2];
            $classrooms = $data[3];
            $learningplans = $data[4];
            $forums = $data[5];
            //$certifications = $data[6];
            $programs = $data[6];
            $onlineexams = $data[7];
            $out .= html_writer::start_tag('li', array('class'=>'quick_nav_list_wrapper first_row'));
                $out .= $users.$feedbacks;
            $out .= html_writer::end_tag('li');
            $out .= html_writer::start_tag('li', array('class'=>'quick_nav_list_wrapper second_row'));
                $out .= $courses.$classrooms;
            $out .= html_writer::end_tag('li');
            $out .= html_writer::start_tag('li', array('class'=>'quick_nav_list_wrapper third_row'));
               // $out .= $learningplans.$forums.$certifications;
                $out .= $learningplans.$forums;
            $out .= html_writer::end_tag('li');
            $out .= html_writer::start_tag('li', array('class'=>'quick_nav_list_wrapper fourth_row'));
                $out .= $programs.$onlineexams;
            $out .= html_writer::end_tag('li');
            $out .= html_writer::end_tag("ul");
        $out .= html_writer::end_tag("div");
        
        return $out;
    }
}
