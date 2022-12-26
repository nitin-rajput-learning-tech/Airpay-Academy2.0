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

namespace local_courses\form;
use core;
use moodleform;
use context_system;
//use coursecat;
require_once($CFG->dirroot . '/lib/formslib.php');
//require_once($CFG->libdir.'/coursecatlib.php');

class coursecategory_form extends moodleform {
    /**
     * The form definition.
     */
    public function definition() {
        global $CFG, $DB, $USER;
        $mform = $this->_form;
        $categoryid = $this->_customdata['categoryid'];
        $parent = $this->_customdata['parent'];
        
        $context = (new \local_courses\lib\accesslib())::get_module_context(); 		
        // Get list of categories to use as parents, with site as the first one.
        $options = array();
        if (has_capability('local/courses:manage', $context) || $parent == 0) {
            $option[0] = get_string('top');
        }
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations',$context)){            
            $options = categorylist('local/courses:manage');
            $options = $option+$options;

        }else if(has_capability('local/costcenter:manage_ownorganization',$context)){
            $orgcategory = $DB->get_field('local_costcenter','category',array('id' => $USER->open_costcenterid));
            $options = categorylist('local/courses:manage','','/',0,$orgcategory);
        }else if(has_capability('local/costcenter:manage_owndepartments',$context)){
            $deptcategory = $DB->get_field('local_costcenter', 'category', array('id' => $USER->open_departmentid));
            $options = categorylist('local/courses:manage','','/',$deptcategory);
        } elseif ($categoryid) {
            // Editing an existing category.
            $options = categorylist('local/courses:manage', $categoryid);
            if (empty($options[$parent])) {
                // Ensure the the category parent has been included in the options.
                $options[$parent] = $DB->get_field('course_categories', 'name', array('id'=>$parent));
            }
        }

        if (!$categoryid) {
            $mform->addElement('select', 'parent', get_string('parentcategory'), $options);
        } else {
            if (!empty($options[$parent]))
                $parentcat = $options[$parent];
            else
                $parentcat = 'Top';
            $mform->addElement('static', 'parentcat', get_string('parentcategory'), $parentcat);
            $mform->addElement('hidden', 'parent', get_string('parentcategory'), $options[$parent]);
        }

        $mform->addElement('text', 'name', get_string('categoryname'), array('size' => '30'));
        $mform->addRule('name', get_string('required'), 'required', null);
        $mform->setType('name', PARAM_TEXT);
        if (!$categoryid) {
            $mform->addElement('text', 'idnumber', get_string('categorycode','local_courses'), 'maxlength="100" size="10"');
            $mform->addRule('idnumber', get_string('required'), 'required', null);
            $mform->setType('idnumber', PARAM_RAW);
        }else{
            $mform->addElement('hidden', 'idnumber', get_string('categorycode','local_courses'), 'maxlength="100" size="10"');
            $mform->addRule('idnumber', get_string('required'), 'required', null);
            $mform->setType('idnumber', PARAM_RAW);
        }
        $mform->addElement('editor', 'description_editor', get_string('description'), null,
            $this->get_description_editor_options());

        if (!empty($CFG->allowcategorythemes)) {
            $themes = array(''=>get_string('forceno'));
            $allthemes = get_list_of_themes();
            foreach ($allthemes as $key => $theme) {
                if (empty($theme->hidefromselector)) {
                    $themes[$key] = get_string('pluginname', 'theme_'.$theme->name);
                }
            }
            $mform->addElement('select', 'theme', get_string('forcetheme'), $themes);
        }

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $categoryid);
         $mform->disable_form_change_checker();
    }

    /**
     * Returns the description editor options.
     * @return array
     */
    public function get_description_editor_options() {
        global $CFG;
        
        $context = $this->_customdata['context'];
        if(empty($context)){
            $context = (new \local_courses\lib\accesslib())::get_module_context(); 		

        }
        $itemid = $this->_customdata['itemid'];
        return array(
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'maxbytes'  => $CFG->maxbytes,
            'trusttext' => true,
            'context'   => $context,
            'subdirs'   => file_area_contains_subdirs($context, 'coursecat', 'description', $itemid),
        );
    }

    /**
     * Validates the data submit for this form.
     *
     * @param array $data An array of key,value data pairs.
     * @param array $files Any files that may have been submit as well.
     * @return array An array of errors.
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        if(!empty($data['idnumber']) && $data['parent'] != 0) {
        $idnumber = $data['idnumber'];
        $parent = $data['parent'];
        $sql = "SELECT id from {course_categories} WHERE idnumber LIKE '{$idnumber}' ";//AND parent = {$parent} 
        if($data['id']){
            $sql .= " AND id <> {$data['id']} ";
        }
        $category = $DB->get_field_sql($sql);
         if(!empty($category)){

              $errors['idnumber'] = get_string('categoryidnumbertaken', 'error');
         }
       }
        return $errors;
    }
     
}
