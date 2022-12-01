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

class evaluation_import_form extends moodleform {
    public function definition() {
        global $CFG;
        $mform =& $this->_form;

        $strdeleteolditmes = get_string('delete_old_items', 'local_evaluation').
                             ' ('.get_string('oldvalueswillbedeleted', 'local_evaluation').')';

        $strnodeleteolditmes = get_string('append_new_items', 'local_evaluation').
                               ' ('.get_string('oldvaluespreserved', 'local_evaluation').')';

        $mform->addElement('radio', 'deleteolditems', '', $strdeleteolditmes, true);
        $mform->addElement('radio', 'deleteolditems', '', $strnodeleteolditmes);

        // hidden elements
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('filepicker',
                           'choosefile',
                           get_string('file'),
                           null,
                           array('maxbytes' => $CFG->maxbytes, 'filetypes' => '*'));

        // buttons
        $this->add_action_buttons(true, get_string('yes'));

    }
}
