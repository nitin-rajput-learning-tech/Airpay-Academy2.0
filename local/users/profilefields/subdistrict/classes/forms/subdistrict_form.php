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

        $districtsql = "SELECT ld.id, ld.district_name FROM {local_district} AS ld WHERE 1 = 1 ";
        if(!is_siteadmin()){
            $orgcond = [];
            foreach($USER->access['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $orgcond[] = " ld.costcenterid = {$costcenterid} ";
            }
            // print_r($orgcond);die;
            if(!empty($orgcond)){
                $districtsql .= " AND ( ".implode(' OR ', $orgcond)." ) ";
            }else{
                $districtsql .= " AND 1 <> 1 ";
            }
        }

        $districts = $DB->get_records_sql_menu($districtsql);
        $districts = [null => get_string('district', 'local_users')] + $districts;
        $mform->addElement('autocomplete', 'districtid',  get_string('district', 'local_users'), $districts);
        $mform->setType('districtid', PARAM_INT);


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

        if(empty($data['districtid'])){
            $errors['districtid'] = get_string('selectdistrict', 'usersprofilefields_subdistrict');
        }
        return $errors;
    }
}