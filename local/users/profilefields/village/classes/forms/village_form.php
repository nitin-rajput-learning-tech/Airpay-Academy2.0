<?php
namespace usersprofilefields_village\forms;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use moodleform;
class village_form extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $systemcontext = (new \usersprofilefields_village\lib\accesslib())::get_module_context();
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $subdistrict = $DB->get_records_menu('local_subdistrict', array(),'subdistrict_name', 'id,subdistrict_name');
            $subdistrict = [null => get_string('subdistrict', 'usersprofilefields_village')]+$subdistrict;
            $mform->addElement('autocomplete', 'subdistrictid',  get_string('subdistrictname', 'usersprofilefields_village'), $subdistrict);
            $mform->setType('subdistrictid', PARAM_INT);
            $village_select = [null => get_string('selectvillage', 'local_users')];

        }else if(!is_siteadmin() && !has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $mform->addElement('hidden', 'subdistrictid');
            $mform->setType('subdistrictid', PARAM_INT);

        }

        $mform->addElement('text', 'village_name', get_string('villagename', 'usersprofilefields_village'));
        $mform->setType('village_name', PARAM_TEXT);
        $mform->addRule('village_name', get_string('villagenamerequired', 'usersprofilefields_village'), 'required', null, 'client');

        $mform->addElement('text', 'code', get_string('villagecode', 'usersprofilefields_village'));
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', get_string('villagecoderequired', 'usersprofilefields_village'), 'required', null, 'client');

        $mform->addElement('hidden',  'id',  $id);
        $mform->setType('id', PARAM_INT);
    }
    //validations
    public function validation($data, $files) {
        global $DB,$USER;
        $systemcontext = (new \usersprofilefields_village\lib\accesslib())::get_module_context();
        $errors = array();

        $recordid = $DB->get_field('local_village','id',array('code' => $data['code'],'subdistrictid' => $data['subdistrictid']));

        if($recordid && $recordid!=$data['id']){
            $errors['code'] = get_string('villagecodeexist', 'usersprofilefields_village');
        }
        $recordid = $DB->get_field('local_village','id',array('village_name' => $data['village_name'],'subdistrictid' => $data['subdistrictid']));
        if($recordid && $recordid!=$data['id']){
            $errors['village_name'] = get_string('villagenameexist', 'usersprofilefields_village');
        }
        if(empty($data['subdistrictid'])){
            $errors['subdistrictid'] = get_string('selectsubdistrict', 'usersprofilefields_village');
        }
        return $errors;
    }
}