    <?php

if (!defined('MOODLE_INTERNAL')) {
    //  It must be included from a Moodle page.
    die(get_string('nodirectaccess','block_learnerscript'));
}

require_once($CFG->libdir . '/formslib.php');

class myclassrooms_form extends moodleform {

    public function definition() {
        global $DB, $USER, $CFG;
        $mform = & $this->_form;
        $mform->addElement('header', 'crformheader', get_string('classroom_recordsdetails', 'block_learnerscript'), '');
        $columns = $DB->get_columns('classroom_recordsdetails');
        //print_object($columns);
        $activitycolumns = array();
        foreach ($columns as $c) {
            $activitycolumns[$c->name] = $c->name;
        }

        $mform->addElement('select', 'column', get_string('column', 'block_learnerscript'), $activitycolumns);
        $this->_customdata['compclass']->add_form_elements($mform, $this);
        // Buttons.
        $this->add_action_buttons(true, get_string('add'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $errors = $this->_customdata['compclass']->validate_form_elements($data, $errors);
        return $errors;
    }

}
