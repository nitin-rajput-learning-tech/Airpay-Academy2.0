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

defined('MOODLE_INTERNAL') || die();

// global $CFG;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/course/lib.php');


/**
 * Upload a file CVS file with course information.
 *
 * @package    tool_uploadcourse
 * @copyright  eAbyas <www.eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_uploadcourse_step1_form extends tool_uploadcourse_base_form {

    /**
     * The standard form definiton.
     * @return void
    */
    public function definition () {
        global $USER, $DB;

        $mform = $this->_form;
        $mform->setDisableShortforms(true);
        //$mform->addElement('header', 'generalhdr', get_string('general'));

        $mform->addElement('filepicker', 'coursefile', get_string('coursefile', 'local_courses'));
        $mform->addRule('coursefile', null, 'required');
        $mform->addHelpButton('coursefile', 'coursefile', 'tool_uploadcourse');

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'local_courses'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }
        $mform->addHelpButton('delimiter_name', 'csvdelimiter', 'tool_uploadcourse');

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'local_courses'), $choices);
        $mform->setDefault('encoding', 'UTF-8');
        $mform->addHelpButton('encoding', 'encoding', 'tool_uploadcourse');

        $choices = array('10' => 10, '20' => 20, '100' => 100, '1000' => 1000, '100000' => 100000);
        $mform->addElement('select', 'previewrows', get_string('rowpreviewnum', 'local_courses'), $choices);
        $mform->setType('previewrows', PARAM_INT);
        $mform->addHelpButton('previewrows', 'rowpreviewnum', 'tool_uploadcourse');

        $this->add_import_options();

        $mform->addElement('hidden', 'showpreview', 1);
        $mform->setType('showpreview', PARAM_INT);

        $this->add_action_buttons(false, get_string('preview', 'local_courses'));
    }
}

// Form 2
class local_uploadcourse_step2_form extends tool_uploadcourse_base_form {

    /**
     * The standard form definiton.
     * @return void.
     */
    public function definition () {
        global $CFG,$DB,$USER;
        $mform   = $this->_form;
        $mform->setDisableShortforms(true);
        $data    = $this->_customdata['data'];
        $courseconfig = get_config('moodlecourse');

        // Import options.
        $this->add_import_options();

        
        if(is_siteadmin($USER)){
            $displaylist = $DB->get_records_menu('local_costcenter',  array('parentid' => 0),  $sort='',  $fields='id,fullname');
            $options[null] = 'select costcenter';
            $data = $options+$displaylist;
            $mform->addElement('select', 'defaults[open_costcenterid]', get_string('open_costcenterid', 'local_courses'), $data);
            $mform->addRule('defaults[open_costcenterid]', null, 'required');
            $mform->addHelpButton('defaults[open_costcenterid]', 'coursecategory');
        } else {
            $mform->addElement('hidden', 'defaults[open_costcenterid]');
            $mform->setDefault('defaults[open_costcenterid]',  $USER->open_costcenterid);
        }
        
        // Hidden fields.
        $mform->addElement('hidden', 'importid', $this->_customdata['importid']);
        $mform->setType('importid', PARAM_INT);

        $mform->addElement('hidden', 'previewrows');
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(true, get_string('uploadcourses', 'tool_uploadcourse'));

        $this->set_data($data);
    }

    /**
     * Add actopm buttons.
     *
     * @param bool $cancel whether to show cancel button, default true
     * @param string $submitlabel label for submit button, defaults to get_string('savechanges')
     * @return void
     */
    public function add_action_buttons($cancel = true, $submitlabel = null) {
        $mform =& $this->_form;
        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'showpreview', get_string('preview', 'tool_uploadcourse'));
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitlabel);
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Sets the enddate default after set_data is called.
     */
    public function definition_after_data() {

        $mform = $this->_form;

        // The default end date depends on the course format.
        $format = course_get_format((object)array('format' => get_config('moodlecourse', 'format')));

        // Check if course end date form field should be enabled by default.
        // If a default date is provided to the form element, it is magically enabled by default in the
        // MoodleQuickForm_date_time_selector class, otherwise it's disabled by default.
       
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array the errors that were found
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }
}
