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
namespace local_request\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/completionlib.php');

use context_system;
use moodleform;

class request_form extends moodleform {

    public $formstatus;

    public function __construct($action = null, $customdata = null, $method = 'post',
        $target = '', $attributes = null, $editable = true, $formdata = null) {
        $this->formstatus = array(
            'manage_request' => get_string('manage_classroom', 'local_classroom'),
            'location_date' => get_string('location_date', 'local_classroom'),
            'classroom_misc' => get_string('assign_course', 'local_classroom'),
        );
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $formdata);
    }

    function definition() {
   
        global $CFG;
        global $currentsess, $DB, $currentrecord;
    
        $mform =& $this->_form; // Don't forget the underscore! 
        
        $mform->addElement('header', 'mainheader','<span style="font-size:18px">'.  
                           get_string('modrequestfacility','local_request'). '</span>');
  
        $field1desc = $DB->get_field('local_request_config', 'value', 
                                     array('varname'=>'page1_fielddesc1'), IGNORE_MULTIPLE);
        $field2desc = $DB->get_field('local_request_config', 'value', 
                                     array('varname'=>'page1_fielddesc2'), IGNORE_MULTIPLE);
    
   
        // Get the field values
        $field1title = $DB->get_field('local_request_config', 'value', 
                                      array('varname'=>'page1_fieldname1'), IGNORE_MULTIPLE);
        $field2title = $DB->get_field('local_request_config', 'value', 
                                      array('varname'=>'page1_fieldname2'), IGNORE_MULTIPLE);
        $field3desc = $DB->get_field('local_request_config', 'value', 
                                      array('varname'=>'page1_fielddesc3'), IGNORE_MULTIPLE);
        $field4title = $DB->get_field('local_request_config', 'value', 
                                      array('varname'=>'page1_fieldname4'), IGNORE_MULTIPLE);
        $field4desc = $DB->get_field('local_request_config', 'value', 
                                      array('varname'=>'page1_fielddesc4'), IGNORE_MULTIPLE);
        //get field 3 status
        $field3status = $DB->get_field('local_request_config', 'value', 
                                      array('varname'=>'page1_field3status'), IGNORE_MULTIPLE);
        
        //get the value for autokey - the config variable that determines enrolment key auto or prompt
        $autoKey = $DB->get_field_select('local_request_config', 'value', "varname LIKE 'autoKey'");
                
        $selfcat = $DB->get_field_select('local_request_config', 'value', "varname LIKE 'selfcat'");
    
        // Page description text
        $mform->addElement('html', '<p></p>'.get_string('courserequestline1','local_request'));
        $mform->addElement('html', '<p></p><div style="width:545px; text-align:left"><b>' . 
                           get_string('step1text','local_request'). '</b></div><p></p><br>');

        // Programme Code
        $attributes = array();

        $attributes['value'] = $currentrecord->modcode;
        $mform->addElement('text', 'programmecode', $field1title, $attributes, '');
        $mform->addRule('programmecode', get_string('request_rule1','local_request'), 'required', 
                        null, 'server', false, false);
    

        $mform->addElement('static', 'description', '', $field1desc);
        $mform->addElement('html', '<p></p>');
        $mform->setType('programmecode', PARAM_TEXT);

        // Programme Title  
        $attributes = array();
        $attributes['value'] = $currentrecord->modname;
        $mform->addElement('text', 'programmetitle', $field2title, $attributes);
        $mform->addRule('programmetitle', get_string('request_rule1','local_request'), 
                        'required', null, 'server', false, false);
        $mform->setType('programmetitle', PARAM_TEXT);

        $mform->addElement('static', 'description', '', $field2desc);
        $mform->addElement('html', '<p>&nbsp;<br>');
        
     
        // Programme Mode
        if ($field3status == 'enabled') {
            $options = array();
            $selectQuery = "varname LIKE 'page1_field3value'";
            $field3Items = $DB->get_recordset_select('local_request_config', $select=$selectQuery);

            foreach ($field3Items as $item) {
                $value = $item->value;
                if ($value != '') {
                    $options[$value] = $value;
                    $options[$value] = $value;
                }
            } 

            $mform->addElement('select', 'programmemode', $field3desc , $options); 
            $mform->addRule('programmemode', get_string('request_rule2','local_request'), 
                            'required', null, 'server', false, false);
            $mform->setDefault('programmemode', $currentrecord->modmode);
            $mform->setType('programmemode', PARAM_TEXT);
        }

     
        // If enabled, give the user the option
        // to select a category location for the course.
        if ($selfcat == 'yes') {
          //  $movetocategories = array();
            $options = core_course_category::make_categories_list(); 
            $mform->addElement('select', 'menucategory', 'Category', $options);
            
            if ($_SESSION['editingmode'] == 'true') {
                $mform->setDefault('menucategory', $currentrecord->cate);
             }
        }

        if (!$autoKey) {
            // enrolment key
            $attributes = array();
            $mform->addElement('html', '<br><br>');
            $attributes['value'] = $currentrecord->modkey;
            $mform->addElement('text', 'enrolkey', $field4title, $attributes);
            $mform->addRule('enrolkey', get_string('request_rule3','local_request'), 'required', 
                            null, 'server', false, false);
            $mform->setType('enrolkey', PARAM_TEXT);
        }

        // Hidden form element to pass the key
      
        if (isset($_GET['edit'])) {
       
            $mform->addElement('hidden', 'editingmode', $currentsess); 
            $mform->setType('editingmode', PARAM_TEXT);
         }

        $mform->addElement('html', '<p></p>&nbsp<p></p>');
        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('Continue','local_request'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancel', get_string('cancel','local_request'));
      //  $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false); 

    }


   
    

}