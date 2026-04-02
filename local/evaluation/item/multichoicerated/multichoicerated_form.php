<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or localify
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

require_once($CFG->dirroot.'/local/evaluation/item/evaluation_item_form_class.php');

class evaluation_multichoicerated_form extends evaluation_item_form {
    protected $type = "multichoicerated";

    public function definition() {
        $item = $this->_customdata['item'];
        $common = $this->_customdata['common'];
        $positionlist = $this->_customdata['positionlist'];
        $position = $this->_customdata['position'];

        $mform =& $this->_form;

        $mform->addElement('header', 'general', get_string($this->type, 'local_evaluation'));

        $mform->addElement('advcheckbox', 'required', get_string('required', 'local_evaluation'), '' , null , array(0, 1));

        $mform->addElement('text',
                            'name',
                            get_string('item_name', 'local_evaluation'),
                            array('size'=>EVALUATION_ITEM_NAME_TEXTBOX_SIZE,
                                  'maxlength'=>255));
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('text',
                            'label',
                            get_string('item_label', 'local_evaluation'),
                            array('size'=>EVALUATION_ITEM_LABEL_TEXTBOX_SIZE,
                                  'maxlength'=>255));

        $mform->addElement('select',
                            'subtype',
                            get_string('multichoicetype', 'local_evaluation').'&nbsp;',
                            array('r'=>get_string('radio', 'local_evaluation'),
                                  'd'=>get_string('dropdown', 'local_evaluation')));

        $mform->addElement('select',
                            'horizontal',
                            get_string('adjustment', 'local_evaluation').'&nbsp;',
                            array(0 => get_string('vertical', 'local_evaluation'),
                                  1 => get_string('horizontal', 'local_evaluation')));
        $mform->disabledIf('horizontal', 'subtype', 'eq', 'd');

        $mform->addElement('selectyesno',
                           'hidenoselect',
                           get_string('hide_no_select_option', 'local_evaluation'));
        $mform->disabledIf('hidenoselect', 'subtype', 'eq', 'd');

        $mform->addElement('hidden',  'ignoreempty',  0);

        $this->values = $mform->addElement('textarea',
                            'values',
                            get_string('multichoice_values', 'local_evaluation'),
                            'wrap="virtual" rows="10" cols="65"');

        $mform->addElement('static',
                            'hint',
                            '',
                            get_string('use_one_line_for_each_value', 'local_evaluation'));

        parent::definition();
        $this->set_data($item);

    }

    public function set_data($item) {
        $info = $this->_customdata['info'];
        if(!$item){
            $item= (object) $item;
        }
        $item->horizontal = $info->horizontal;

        $item->subtype = $info->subtype;

        $item->values = $info->values;

        return parent::set_data($item);
    }

    public function get_data() {
        if (!$item = parent::get_data()) {
            return false;
        }
        $itemobj = new evaluation_item_multichoicerated();

        $presentation = $itemobj->prepare_presentation_values_save(trim($item->values),
                                                EVALUATION_MULTICHOICERATED_VALUE_SEP2,
                                                EVALUATION_MULTICHOICERATED_VALUE_SEP);
        if (!isset($item->subtype)) {
            $subtype = 'r';
        } else {
            $subtype = substr($item->subtype, 0, 1);
        }
        if (isset($item->horizontal) AND $item->horizontal == 1 AND $subtype != 'd') {
            $presentation .= EVALUATION_MULTICHOICERATED_ADJUST_SEP.'1';
        }
        $item->presentation = $subtype.EVALUATION_MULTICHOICERATED_TYPE_SEP.$presentation;
        if (!isset($item->hidenoselect)) {
            $item->hidenoselect = 1;
        }
        if (!isset($item->ignoreempty)) {
            $item->ignoreempty = 0;
        }
        return $item;
    }
}
