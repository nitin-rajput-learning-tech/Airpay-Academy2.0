<?php
namespace usersprofilefields_district\forms;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use moodleform;
class district_form extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $systemcontext = (new \usersprofilefields_district\lib\accesslib())::get_module_context();
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];
        $statesid = $this->_customdata['statesid'];

        if($id){
            $satatename = $DB->get_field('local_states', 'states_name',array('id' => $statesid));
            $mform->addElement('static','satatename', get_string('statesname', 'usersprofilefields_district'),$satatename);
            $mform->addElement('hidden', 'statesid');
        } else {
            $statessql = "SELECT ls.id, ls.states_name FROM {local_states} AS ls WHERE 1 = 1 ";
            if(!is_siteadmin()){
                $orgcond = [];
                foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){
                    $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                    $orgcond[] = " ls.costcenterid = {$costcenterid} ";
                }
                // print_r($costcenterid);die;
                if(!empty($orgcond)){
                    $statessql .= " AND ( ".implode(' OR ', $orgcond)." ) ";
                }else{
                    $statessql .= " AND 1 <> 1 ";
                }
            }

            $state = $DB->get_records_sql_menu($statessql);
            $state = [null => get_string('selectstates', 'usersprofilefields_district')] + $state;
            $mform->addElement('autocomplete', 'statesid',  get_string('statesname', 'usersprofilefields_district'), $state);
        }
        $mform->setType('statesid', PARAM_INT);


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
        $systemcontext = (new \usersprofilefields_district\lib\accesslib())::get_module_context();
        $errors = array();

        $recordid = $DB->get_field('local_district','id',array('code' => $data['code'],'statesid' => $data['statesid']));

        if($recordid && $recordid!=$data['id']){
            $errors['code'] = get_string('districtcodeexist', 'usersprofilefields_district');
        }
        if(empty($data['statesid'])){
            $errors['statesid'] = get_string('requirestates', 'usersprofilefields_district');
        }
        return $errors;
    }
}
