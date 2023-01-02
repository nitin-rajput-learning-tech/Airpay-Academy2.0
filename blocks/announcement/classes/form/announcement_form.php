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
 * @subpackage blocks_announcement
 */
namespace block_announcement\form;
use core;
use moodleform;
use context_system;
use coursecat;
use html_writer;
use costcenter;
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/lib/badgeslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class announcement_form extends moodleform {

    /**
     * Defines the form
     */
    public function definition() {
        global $USER, $PAGE, $OUTPUT, $DB;
        $mform = $this->_form;
        $id = $this->_customdata['id'];
        
        $courseid = $this->_customdata['courseid'];
        $context =  (new \block_announcement\lib\accesslib())::get_module_context();
        if(!is_siteadmin()){
            $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();

            $userinfo = $DB->get_record('user',array('id' => $USER->id),'open_path');
            $open_path = explode('/',$userinfo->open_path);
            $open_costcenterid = $open_path[1];

            $mform->addElement('hidden',  'open_costcenterid', $open_costcenterid);
            $mform->setType('open_costcenterid', PARAM_INT);
            $mform->setConstant('open_costcenterid', $open_costcenterid);
        }else{

        local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1, 1), false, 'block_announcement', $context, $multiple = false);
        }

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        
        $mform->addElement('text', 'name', get_string('announcement_title', 'block_announcement'));
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_RAW);
        
        $mform->addElement('html', html_writer::div(html_writer::span(get_string('description_help', 'block_announcement')), 'pull-right fp-restrictions', array('style' => 'font-size:11px;')));
        
		$mform->addElement('editor', 'description', get_string('announcement_text', 'block_announcement'), null,
        $this->get_description_editor_options());
		
        $mform->addRule('description', null, 'required', null, 'client');
        $mform->addHelpButton('description', 'description','block_announcement');
        $mform->setType('description', PARAM_RAW);
        
        $mform->addElement('filemanager', 'attachment', get_string('attachments', 'block_announcement'), null, array('accepted_types' => '*'));

        $startdate_options = array(
                                'startyear' => \local_costcenter\lib::get_userdate('Y'),
                                'stopyear'  => \local_costcenter\lib::get_userdate('Y')+3,
                                'applydst'  => true,
                                'step'      => 1,
                                'optional' => true
                                );
        $mform->addElement('date_time_selector', 'startdate', get_string('startdate', 'block_announcement'), $startdate_options);
        
        $enddate_options = array(
                                'startyear' => \local_costcenter\lib::get_userdate('Y'),
                                'stopyear'  => \local_costcenter\lib::get_userdate('Y')+3,
                                'applydst'  => true,
                                'step'      => 1,
                                'optional' => true
                                );
        $mform->addElement('date_time_selector', 'enddate', get_string('enddate', 'block_announcement'), $enddate_options);
        
        $this->add_action_buttons();
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
            $context =  (new \block_announcement\lib\accesslib())::get_module_context();
        }
        //print_object($context);
        $itemid = $this->_customdata['itemid'];
        //print_object($itemid);
        return array(
            'maxfiles'  => EDITOR_UNLIMITED_FILES,
            'maxbytes'  => $CFG->maxbytes,
            'trusttext' => true,
            'context'   => $context,
			'autosave' => false,
            'subdirs'   => file_area_contains_subdirs($context, 'system', 'description', $itemid),
        );
    }
    
    /**
     * Validates form data
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        
        $startdate = '';
        $enddate = '';
        if (isset($data['startdate']) && !empty($data['startdate'])) {
            $timestamp = $data['startdate'];
            $presenttime = time();
            //$startdate = strtotime(date("d m Y", $timestamp));
            $startdate = $timestamp;
            
        }
        if (isset($data['enddate']) && !empty($data['enddate'])) {
            $timestamp = $data['enddate'];
            $presenttime = time();
            //$enddate = strtotime(date("d m Y", $timestamp));
            $enddate = $timestamp;
            
        }
        
        if(!empty($startdate) && !empty($enddate)){
            if($startdate >= $enddate){
                $errors['enddate'] = get_string('nohighandsameenddate', 'block_announcement');
            }
        }
        
        return $errors;
    }
}