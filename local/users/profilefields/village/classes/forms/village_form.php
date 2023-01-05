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
        $subdistrictid = $this->_customdata['subdistrictid'];

        if($id){
            $subdistrictname = $DB->get_field('local_subdistrict', 'subdistrict_name',array('id' => $subdistrictid));
            $mform->addElement('static','subdistrictname', get_string('costcentername', 'usersprofilefields_states'),$subdistrictname);
            $mform->addElement('hidden', 'subdistrictid');
        } else {
            $subdistrictsql = "SELECT lsd.id, lsd.subdistrict_name FROM {local_subdistrict} AS lsd WHERE 1 = 1 ";
            if(!is_siteadmin()){
                $orgcond = [];
                foreach($USER->access['currentroleinfo']['contextinfo'] AS $contextinfo){
                    $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                    $orgcond[] = " lsd.costcenterid = {$costcenterid} ";
                }
                // print_r($orgcond);die;
                if(!empty($orgcond)){
                    $subdistrictsql .= " AND ( ".implode(' OR ', $orgcond)." ) ";
                }else{
                    $subdistrictsql .= " AND 1 <> 1 ";
                }
            }

            $subdistricts = $DB->get_records_sql_menu($subdistrictsql);
            $subdistricts = [null => get_string('selectsubdistrict', 'usersprofilefields_village')] + $subdistricts;
            $mform->addElement('autocomplete', 'subdistrictid',  get_string('subdistrictname', 'usersprofilefields_village'), $subdistricts);
        }
        $mform->setType('subdistrictid', PARAM_INT);

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

        if(empty($data['subdistrictid'])){
            $errors['subdistrictid'] = get_string('requirsubdistrict', 'usersprofilefields_village');
        }
        return $errors;
    }
}