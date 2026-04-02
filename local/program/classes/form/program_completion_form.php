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
 * @package Bizlms 
 * @subpackage local_program
 */

namespace local_program\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/local/program/lib.php');
use \local_program\program as program;
use moodleform;
use local_program\local\querylib;
use context_system;

class program_completion_form extends moodleform {

    public function definition() {
        global $CFG, $DB, $USER;
        $categorycontext =  (new \local_program\lib\accesslib())::get_module_context($bcid);
        $mform = &$this->_form;
        $pid = $this->_customdata['pid'];
        $id = $this->_customdata['id'];
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'programid', $pid);
        $mform->setType('programid', PARAM_INT);

        $completionflag = local_program_completion_form_flag($mform, $pid, 0);
        if(!$completionflag){
            $section_tracking=array('ALL'=>get_string('alllevels','local_program'),
                                    'AND'=>get_string('program_selectedlevelscompletion', 'local_program'),
                                    'OR'=>get_string('program_anylevelcompletion', 'local_program'));

            $mform->addElement('select', 'leveltracking', get_string('leveltracking', 'local_program'), $section_tracking, array());

            $levels = array();
            $levels = $this->_ajaxformdata['levels'];
            // if (!empty($levels)) {
            //     $levels = $levels;
            // } else if ($id > 0) {
            //     $levels = $DB->get_records_menu('local_bc_completion_criteria',
            //         array('id' => $id), 'id', 'id, levelids');
            // }
            // if (!empty(array_filter($levels))) {
            //     if(is_array($levels)){
            //         $levels=implode(',',$levels);
            //     }
            //     $levels_sql = "SELECT id, level as fullname
            //                         FROM {local_program_levels}
            //                         WHERE programid = {$pid} and id in ({$levels})";
            //     $levels = $DB->get_records_sql_menu($levels_sql);
            // }elseif (empty($levels)) {
            // }
                $levels_sql = "SELECT id, level as fullname
                                            FROM {local_program_levels}
                                            WHERE programid = {$pid} ";
                $levels = $DB->get_records_sql_menu($levels_sql);
            $options = array(
                'multiple' => true,
                'data-contextid' => $categorycontext->id,
            );
            $mform->addElement('autocomplete', 'levelids', get_string('level_completion', 'local_program'), $levels,$options);
            $mform->hideIf('levelids', 'leveltracking', 'eq','ALL');
        }
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $CFG, $DB, $USER;
        $errors = parent::validation($data, $files);
        if (isset($data['leveltracking']) && ($data['leveltracking'] == "OR" || $data['leveltracking'] == "AND") && isset($data['levelids']) && empty($data['levelids'])) {
            $errors['levelids'] = get_string('select_levels', 'local_program');
        }
        return $errors;
    }
}