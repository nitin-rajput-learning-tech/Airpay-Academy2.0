<?php
namespace usersprofilefields_states\forms;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use moodleform;
class states_form extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $systemcontext = \context_system::instance();
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $organiasations = $DB->get_records_menu('local_costcenter', array('depth' => 5),'fullname', 'id,fullname');

            $organiasations = [null => get_string('territories', 'local_users')]+$organiasations;
            $mform->addElement('autocomplete', 'territoryid',  get_string('territory', 'local_users'), $organiasations);
            $mform->setType('territoryid', PARAM_INT);
            $states_select = [null => get_string('selectstates', 'local_users')];
        }else if(!is_siteadmin() && !has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $mform->addElement('hidden', 'territoryid');
            $mform->setType('territoryid', PARAM_INT);
            $mform->setDefault('territoryid', $USER->open_costcenterid);
        }


        $mform->addElement('text', 'states_name', get_string('statesname', 'usersprofilefields_states'));
        $mform->setType('states_name', PARAM_TEXT);
        $mform->addRule('states_name', get_string('statesnamerequired', 'usersprofilefields_states'), 'required', null, 'client');

        $mform->addElement('text', 'code', get_string('statescode', 'usersprofilefields_states'));
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', get_string('statescoderequired', 'usersprofilefields_states'), 'required', null, 'client');

        $mform->addElement('hidden',  'id',  $id);
        $mform->setType('id', PARAM_INT);
    }
    //validations
    public function validation($data, $files) {
        global $DB,$USER;
        $systemcontext = \context_system::instance();
        $errors = array();

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $territoryid = $data['territoryid'];
        }else{
            $territoryid = $USER->open_costcenterid;
        }

        $recordid = $DB->get_field('local_states','id',array('code' => $data['code'],'territoryid' => $territoryid));
        if($recordid && $recordid!=$data['id']){
            $errors['code'] = get_string('statescodeexist', 'usersprofilefields_states');
        }
        $recordid = $DB->get_field('local_states','id',array('states_name' => $data['states_name'],'territoryid' => $territoryid));
        if($recordid && $recordid!=$data['id']){
            $errors['states_name'] = get_string('statesnameexist', 'usersprofilefields_states');
        }
        if($data['territoryid'] < 0){
            $errors['territoryid'] = get_string('statesnameexist', 'usersprofilefields_states');
        }
        if(empty($data['territoryid'])){
            $errors['territoryid'] = get_string('selectterritories', 'usersprofilefields_states');
        }
        return $errors;
    }
}