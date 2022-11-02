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
 * @subpackage local_courses
 */

namespace local_courses\form;
use core;
use moodleform;
use context_system;
use context_course;
use core_component;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir.'/formslib.php');


class custom_courseevidence_form extends moodleform {
    /**
     * Form definition.
     */
    function definition() {
        global $DB,$OUTPUT,$CFG, $PAGE, $USER;

        $mform = $this->_form;
        $mform->_attributes['id'] = 'courseevidenceform'.$this->_customdata['courseid'].'';
        $courseid = $this->_customdata['courseid']; 
        $userid = $this->_customdata['userid']; 

        $data =(object)array('userid'=>$userid,'courseid'=>$courseid); 
        

        $coursecontext = context_course::instance($courseid);
        

        $fileoptions = array('subdirs' => 1,
                                'maxbytes' => 0,
                                'maxfiles' => 20,
                                'accepted_types' => array(),
                                'return_types' => (FILE_INTERNAL | FILE_CONTROLLED_LINK));

        // file_prepare_draft_area($data->files_filemanager, $coursecontext->id, 'local_courses', 'usercourseevidence_files', $data->userid, null);

        $data = file_prepare_standard_filemanager($data,
                                                  'files',
                                                  $fileoptions,
                                                  $coursecontext,
                                                  'local_courses',
                                                  'usercourseevidence_files',
                                                  $userid);
         //print_object($data);


        $mform->addElement('filemanager', 'files_filemanager', get_string('browseevidences','local_courses'), null,$fileoptions);
            
        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);  
        
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);  
        if ($data) {
            $this->set_data($data);
        }
		$mform->disable_form_change_checker();
    }
}
