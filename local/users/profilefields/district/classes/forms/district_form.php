<?php
namespace usersprofilefields_district\forms;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use moodleform;
class district_form extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $systemcontext = \context_system::instance();
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $states = $DB->get_records_menu('local_states', array(),'states_name', 'id,states_name');
            $states = [null => get_string('states', 'usersprofilefields_district')]+$states;
            $mform->addElement('autocomplete', 'statesid',  get_string('statesname', 'usersprofilefields_district'), $states);
            $mform->setType('statesid', PARAM_INT);
            $district_select = [null => get_string('selectdistrict', 'local_users')];
        }else if(!is_siteadmin() && !has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $mform->addElement('hidden', 'statesid');
            $mform->setType('statesid', PARAM_INT);

        }
        $mform->addElement('text', 'district_name', get_string('districtname', 'usersprofilefields_district'));
        $mform->setType('district_name', PARAM_TEXT);
        $mform->addRule('district_name', get_string('districtnamerequired', 'usersprofilefields_district'), 'required', null, 'client');

        $mform->addElement('text', 'code', get_string('districtcode', 'usersprofilefields_district'));
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', get_string('districtcoderequired', 'usersprofilefields_district'), 'required', null, 'client');

        $mform->addElement('hidden',  'id',  $id);
        $mform->setType('id', PARAM_INT);
    }
    //validations
    public function validation($data, $files) {
        global $DB,$USER;
        $systemcontext = \context_system::instance();
        $errors = array();

        $recordid = $DB->get_field('local_district','id',array('code' => $data['code'],'statesid' => $data['statesid']));

        if($recordid && $recordid!=$data['id']){
            $errors['code'] = get_string('districtcodeexist', 'usersprofilefields_district');
        }
        $recordid = $DB->get_field('local_district','id',array('district_name' => $data['district_name'],'statesid' => $data['statesid']));
        if($recordid && $recordid!=$data['id']){
            $errors['district_name'] = get_string('districtnameexist', 'usersprofilefields_district');
        }
        if(empty($data['statesid'])){
            $errors['statesid'] = get_string('selectstates', 'usersprofilefields_district');
        }
        return $errors;
    }
}