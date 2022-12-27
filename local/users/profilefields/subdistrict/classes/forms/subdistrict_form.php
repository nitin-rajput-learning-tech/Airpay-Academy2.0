<?php
namespace usersprofilefields_subdistrict\forms;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use moodleform;
class subdistrict_form extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $systemcontext = (new \usersprofilefields_subdistrict\lib\accesslib())::get_module_context();
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $district = $DB->get_records_menu('local_district', array(),'district_name', 'id,district_name');
            $district = [null => get_string('district', 'usersprofilefields_subdistrict')]+$district;
            $mform->addElement('autocomplete', 'districtid',  get_string('districtname', 'usersprofilefields_subdistrict'), $district);
            $mform->setType('districtid', PARAM_INT);
            $subdistrict_select = [null => get_string('selectsubdistrict', 'local_users')];
        }else if(!is_siteadmin() && !has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $mform->addElement('hidden', 'districtid');
            $mform->setType('districtid', PARAM_INT);

        }


        $mform->addElement('text', 'subdistrict_name', get_string('subdistrictname', 'usersprofilefields_subdistrict'));
        $mform->setType('subdistrict_name', PARAM_TEXT);
        $mform->addRule('subdistrict_name', get_string('subdistrictnamerequired', 'usersprofilefields_subdistrict'), 'required', null, 'client');

        $mform->addElement('text', 'code', get_string('subdistrictcode', 'usersprofilefields_subdistrict'));
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', get_string('subdistrictcoderequired', 'usersprofilefields_subdistrict'), 'required', null, 'client');

        $mform->addElement('hidden',  'id',  $id);
        $mform->setType('id', PARAM_INT);
    }
    //validations
    public function validation($data, $files) {
        global $DB,$USER;
        $systemcontext = (new \usersprofilefields_subdistrict\lib\accesslib())::get_module_context();
        $errors = array();

        $recordid = $DB->get_field('local_subdistrict','id',array('code' => $data['code'],'districtid' => $data['districtid']));

        if($recordid && $recordid!=$data['id']){
            $errors['code'] = get_string('subdistrictcodeexist', 'usersprofilefields_subdistrict');
        }
        $recordid = $DB->get_field('local_subdistrict','id',array('subdistrict_name' => $data['subdistrict_name'],'districtid' => $data['districtid']));
        if($recordid && $recordid!=$data['id']){
            $errors['subdistrict_name'] = get_string('subdistrictnameexist', 'usersprofilefields_subdistrict');
        }
        if(empty($data['districtid'])){
            $errors['districtid'] = get_string('selectdistrict', 'usersprofilefields_subdistrict');
        }
        return $errors;
    }
}