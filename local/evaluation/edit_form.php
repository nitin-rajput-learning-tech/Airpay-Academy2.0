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
 * @subpackage local_evaluation
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir.'/formslib.php');
if(file_exists($CFG->dirroot . '/local/lib.php')){
    require_once($CFG->dirroot . '/local/lib.php');
}
class evaluation_edit_use_template_form extends moodleform {

    /**
     * Form definition
     */
    public function definition() {
        $mform =& $this->_form;

        $elementgroup = array();
        //headline
        $mform->addElement('header', 'using_templates', get_string('using_templates', 'local_evaluation'));
        // hidden elements
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // visible elements
        $templates_options = array();
        $allemplates = evaluation_get_template_list('all');

        $options = array();
        if ($allemplates) {
            $options[''] = array('' => get_string('choosedots'));

            if ($allemplates) {
                $courseoptions = array();
                foreach ($allemplates as $template) {
                    $courseoptions[$template->id] = format_string($template->name);
                }
                $options['Template'] = $courseoptions;
            }

            $attributes = 'onChange="M.core_formchangechecker.set_form_submitted(); this.form.submit()"';
            $elementgroup[] = $mform->createElement('selectgroups',
                                                     'templateid',
                                                     get_string('using_templates', 'local_evaluation'),
                                                     $options,
                                                     $attributes);

            $elementgroup[] = $mform->createElement('submit',
                                                     'use_template',
                                                     get_string('use_this_template', 'local_evaluation'),
                                                     array('class' => 'hiddenifjs'));

            $mform->addGroup($elementgroup, 'elementgroup', '', array(' '), false);
        } else {
            $mform->addElement('static', 'info', get_string('no_templates_available_yet', 'local_evaluation'));
        }
        
        

        $this->set_data(array('id' => $this->_customdata['id']));
    }
}

class evaluation_edit_create_template_form extends moodleform {

    /**
     * Form definition
     */
    public function definition() {
        $mform =& $this->_form;

        // hidden elements
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'do_show');
        $mform->setType('do_show', PARAM_ALPHANUMEXT);
        $mform->setConstant('do_show', 'templates');

        //headline
        $mform->addElement('header', 'creating_templates', get_string('creating_templates', 'local_evaluation'));

        // visible elements
        $elementgroup = array();

        $elementgroup[] = $mform->createElement('text', 'templatename', get_string('name', 'local_evaluation'),
                                                 array('size'=>'40', 'maxlength'=>'200'));

        $mform->addGroup($elementgroup, 'elementgroup', get_string('name', 'local_evaluation'), array(' '), false);

        // Buttons.
        $mform->addElement('submit', 'create_template', get_string('save_as_new_template', 'local_evaluation'));

        $mform->setType('templatename', PARAM_TEXT);
        $this->set_data(array('id' => $this->_customdata['id']));
    }

    /**
     * Form validation
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!isset($data['templatename']) || trim(strval($data['templatename'])) === '') {
            $errors['elementgroup'] = get_string('name_required', 'local_evaluation');
        }
        return $errors;
    }
}

