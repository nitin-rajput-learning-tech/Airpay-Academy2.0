<?php
namespace usersprofilefields_states\forms;
defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
use moodleform;
class states_form extends \moodleform {
    public function definition() {
        global $USER, $CFG, $DB, $PAGE;
        $systemcontext = (new \usersprofilefields_states\lib\accesslib())::get_module_context();
        $mform = $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];

        $organisationsql = "SELECT lc.id, lc.fullname FROM {local_costcenter} AS lc WHERE 1 = 1 AND lc.depth = 1 ";
        if(!is_siteadmin()){
            $orgcond = [];
            foreach($USER->access['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $orgcond[] = " lc.id = {$costcenterid} ";
            }
            if(!empty($orgcond)){
                $organisationsql .= " AND ( ".implode(' OR ', $orgcond)." ) ";
            }else{
                $organisationsql .= " AND 1 <> 1 ";
            }
        }
        $organiasations = $DB->get_records_sql_menu($organisationsql);
        $organiasations = [null => get_string('organisation', 'local_users')] + $organiasations;
        $mform->addElement('autocomplete', 'costcenterid',  get_string('territory', 'local_users'), $organiasations);
        $mform->setType('costcenterid', PARAM_INT);
        $states_select = [null => get_string('selectstates', 'local_users')];



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
        $systemcontext = (new \usersprofilefields_states\lib\accesslib())::get_module_context();
        $errors = array();

        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $costcenterid = $data['costcenterid'];
        }else{
            $costcenterid = explode('/', $USER->open_path)[1];
        }

        $recordid = $DB->get_field('local_states','id',array('code' => $data['code'],'costcenterid' => $costcenterid));
        if($recordid && $recordid!=$data['id']){
            $errors['code'] = get_string('statescodeexist', 'usersprofilefields_states');
        }

        if($recordid && $recordid!=$data['id']){
            $errors['states_name'] = get_string('statesnameexist', 'usersprofilefields_states');
        }

        if(empty($data['costcenterid'])){
            $errors['costcenterid'] = get_string('selectorganisation', 'usersprofilefields_states');
        }
        return $errors;
    }
}
