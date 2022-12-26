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
 * local local_costcenter
 *
 * @package    local_costcenter
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_costcenter\form;
use core;
use moodleform;
use context_system;
use core_component;

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class costcenterform extends moodleform { /*costcenter creation form*/

    public function definition() {
        global $USER, $CFG,$DB;
        $costcenter = new \costcenter();
        $corecomponent = new core_component();

        $mform = $this->_form;
        $id = $this->_customdata['id'];
        $costcenters = $this->_customdata['tool'];
        $editoroptions = $this->_customdata['editoroptions'];
        $depts = $this->_customdata['dept'];
        $subdept = $this->_customdata['subdept']; 
        $parentid = $this->_customdata['parentid']; 
        $formtype = $this->_customdata['formtype'];

        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();

        if($formtype == 'createdeptmodal'){
            if((is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $categorycontext))){
                $depsql = "SELECT lc.id,lc.fullname 
                            FROM {local_costcenter} as lc 
                            WHERE parentid = :parentid";
                $parents = $DB->get_records_sql_menu($depsql, ['parentid' => 0]);

                // $departments = array(null =>'Select');          
                // $parents = $departments + $parents;      
            }else{
                $sql = "SELECT id,fullname 
                        FROM {local_costcenter} WHERE id = ? ";
                $parents = $DB->get_records_sql_menu($sql, [$USER->open_costcenterid]);
            }
            $mform->addElement('select', 'parentid', get_string('organisation','local_costcenter'), $parents);
            $mform->setType('parentid', PARAM_INT);
            $mform->addRule('parentid', get_string('orgemptymsg', 'local_costcenter'), 'required', null, 'client');
            // $mform->addHelpButton('parentid', 'parent', 'local_costcenter');
        }else{
            $mform->addElement('hidden', 'parentid', 0);
            $mform->setType('parentid', PARAM_INT);
        }

        
        // if($subdept){
        //     $depsql = "SELECT lc.id,lc.fullname FROM {local_costcenter} as lc WHERE parentid = :parentid";
        //     $parents = $DB->get_records_sql_menu($depsql, ['parentid' => 0]);
        //     $departments = array(null =>'Select');
        //     // $departments[null] = get_string('select');
        //     // foreach($depts as $dept){
        //     //     $departments[$dept->id] =$dept->fullname;
        //     //     // $subsql = "SELECT lc.id,lc.fullname FROM {local_costcenter} as lc WHERE parentid = :parentid ";
        //     //     // $subdepts =  $DB->get_records_sql_menu($subsql, ['parentid' => 0]);
        //     // }            
        //     $parents = $departments+$parents;            
        // } elseif($depts){
        //     $sql = "SELECT id,fullname FROM {local_costcenter} WHERE id=?";
        //     $sqlrec = $DB->get_record_sql($sql, [$USER->open_costcenterid]);
        //     $parents = $sqlrec;           
        // } else{
        //     // $items = $costcenter->get_costcenter_items(true);
        //     // $parents = $costcenter->get_costcenter_parent($items, $costcenters->id);
        //     // $parents = array_filter($parents);
        //     $departments = array(null =>'Select');
        //     $depsql = "SELECT lc.id,lc.fullname FROM {local_costcenter} as lc WHERE parentid = :parentid";
        //     $parents = $DB->get_records_sql_menu($depsql, ['parentid' => 0]);
        //     $parents = $departments+$parents; 
        // }

        // if (count($parents) <= 1) {
        //     $mform->addElement('hidden', 'parentid', $parents->id);
        //     $mform->setType('parentid', PARAM_RAW);
        // } else {
        //     $mform->addElement('select', 'parentid', get_string('parent', 'local_costcenter'), $parents);
        //     $mform->setType('parentid', PARAM_RAW);
        // }
        // // issue num OL29
        // if($id && isset($parentid)){
        //     $mform->disabledIf('parentid', 'id');
        // }
        // end of OL29
        // issue num OL-912
        // if(!is_siteadmin() && $subdept){
        //     $mform->addRule('parentid', get_string('parentcannotbeempty', 'local_costcenter'), 'required', null, 'client');
        // }
        // end of OL-912
        
        $mform->addElement('text', 'fullname', get_string('costcentername', 'local_costcenter'), array());
        $mform->setType('fullname', PARAM_TEXT);
        $mform->addRule('fullname', get_string('missingcostcentername', 'local_costcenter'), 'required', null, 'client');
        
        $mform->addElement('text', 'shortname', get_string('shortname','local_costcenter'), 'maxlength="100" size="20"');
        
        $mform->addRule('shortname', get_string('shortnamecannotbeempty', 'local_costcenter'), 'required', null, 'client');
            
        $mform->setType('shortname', PARAM_TEXT);
        $attributes = array('rows' => '8', 'cols' => '40');

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        
        // $now = \local_costcenter\lib::get_userdate("d/m/Y H:i");
        // $now = strtotime($now);
        $mform->addElement('hidden', 'timecreated', time());
        $mform->setType('timecreated', PARAM_INT);
        
        $mform->addElement('hidden', 'usermodified', $USER->id);
        $mform->setType('usermodified', PARAM_INT);

        if(!$depts && !$subdept && !$parentid){
            $theme_epsilon_plugin_exist = $corecomponent::get_plugin_directory('theme', 'epsilon');
            if(!empty($theme_epsilon_plugin_exist)){
                $choices = array('scheme1' => get_string('scheme_1', 'theme_epsilon'),
                                 'scheme2' => get_string('scheme_2', 'theme_epsilon'),
                                 'scheme3' => get_string('scheme_3', 'theme_epsilon'),
                                 'scheme4' => get_string('scheme_4', 'theme_epsilon'),
                                 'scheme5' => get_string('scheme_5', 'theme_epsilon'),
                                 'scheme6' => get_string('scheme_6', 'theme_epsilon'),
                                 0 => get_string('customscheme', 'theme_epsilon')
                         );
                $mform->addElement('select', 'theme', get_string('preferredscheme', 'local_costcenter'), $choices);
            
            }
        }
        if(!$depts && !$subdept && !$parentid){
            $logoupload = array('maxbytes'       => $CFG->maxbytes,
                              'subdirs'        => 0,                             
                              'maxfiles'       => 1,                             
                              'accepted_types' => 'web_image');
            $mform->addElement('filemanager', 'costcenter_logo', get_string('costcenter_logo', 'local_costcenter'), '', $logoupload);
        }

        $submit = ($id > 0) ? get_string('update_costcenter', 'local_costcenter') : get_string('create', 'local_costcenter');
        $this->add_action_buttons('false', $submit);
    }

    /**
     * validates costcenter name and returns instance of this object
     *
     * @param [object] $data 
     * @param [object] $files 
     * @return costcenter validation errors
     */
     public function validation($data, $files) {
        global $COURSE, $DB, $CFG;
        $errors = parent::validation($data, $files);
        // fix for OL01 issue by mahesh
        if(empty(trim($data['shortname']))){
            $errors['shortname'] = get_string('shortnamecannotbeempty', 'local_costcenter');
        }
        if(empty(trim($data['fullname']))){
            $errors['fullname'] = get_string('fullnamecannotbeempty', 'local_costcenter');
        }
        // OL01 fix ends.
        if ($DB->record_exists('local_costcenter', array('shortname' => trim($data['shortname'])), '*', IGNORE_MULTIPLE)) {
            $costcenter = $DB->get_record('local_costcenter', array('shortname' => trim($data['shortname'])), '*', IGNORE_MULTIPLE);
            if (empty($data['id']) || $costcenter->id != $data['id']) {
                $errors['shortname'] = get_string('shortnametakenlp', 'local_costcenter', $costcenter->shortname);
            }
        }
        return $errors;
     }
     
}
