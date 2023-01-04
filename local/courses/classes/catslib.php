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
 * local courses
 *
 * @package    local_courses
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courses;
defined('MOODLE_INTERNAL') || die();

class catslib {

    /** @var array of categories in a specific costcenter */

    protected $categories = array();
    
    public function get_categories(){
        global $DB, $USER;

        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='lc.path',$costcenterpath=null,$datatype='lowerandsamepath');

        $costcentersql = "SELECT lc.category
                        FROM {local_costcenter} AS lc
                        WHERE 1=1 $costcenterpathconcatsql ";

        $category = $DB->get_field_sql($costcentersql);
        $data = $DB->get_records('course_categories',array('parent' => $category));
        $this->categories[] = $category;
        $cats = $this->get_lower_cats($data);
        return $cats;
    }

    /**
     * [get_lower_cats description] to get the information of the categories under a specific one.
     * @param  [object] $data [departments data under organisation]
     * @return [array]       [category id's lower the organisation]
    */

    protected function get_lower_cats($data){
        global $DB;
        foreach($data as $category){
            $lowercat_exist = $DB->get_records('course_categories', array('parent' => $category->id));
            if($lowercat_exist){
               $info = $this->get_lower_cats($lowercat_exist);
            }
            $this->categories[] = $category->id;
        }
        return $this->categories;
    }

}

