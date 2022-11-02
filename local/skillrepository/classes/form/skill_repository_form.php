<?php
namespace local_skillrepository\form;
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_skillrepository
 */
use moodleform;
use context_system;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");

class skill_repository_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;

        $mform->addElement('header', 'displayinfo', get_string('create_skill', 'local_skillrepository'));
        $id = $this->_customdata['id'];
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        
        $context = context_system::instance();
        if (is_siteadmin($USER->id) || has_capability('local/costcenter:manage_multiorganizations',$context)) {
            $sql="select id,fullname from {local_costcenter} where visible =1 AND parentid = 0";
            $costcenters = $DB->get_records_sql($sql);
            $organizationlist=array(null=>'--Select Organization--');
            foreach ($costcenters as $scl) {
                $organizationlist[$scl->id]=$scl->fullname;
            }
            $mform->addElement('autocomplete', 'costcenterid', get_string('organization', 'local_users'), $organizationlist);
            $mform->addRule('costcenterid', null, 'required', null, 'client');
            $mform->setType('costcenterid', PARAM_INT);
        } else {
            $user_dept = $DB->get_field('user','open_costcenterid', array('id'=>$USER->id));
            $mform->addElement('hidden', 'costcenterid', null);
            $mform->setType('costcenterid', PARAM_INT);
            $mform->setConstant('costcenterid', $user_dept);
        }

        $skillsoptions = array();
        $skillsoptions[null] = get_string('select');
        if(is_siteadmin()){
            $skills = $DB->get_records_select_menu('local_skill_categories', '', array());
        }else{
            $sql="select * from {local_skill_categories} where costcenterid={$USER->open_costcenterid} ORDER BY name ";
            $skills= $DB->get_records_sql($sql);
        }

        $repository = isset($this->_ajaxformdata['repository']);
        $repos = array(0=>'Select');
        if (!empty($repository)) {
            $repository = implode(',', $repository);
            $repos_sql = "SELECT sc.id, sc.name
                            FROM {local_skill_categories} AS sc
                            JOIN {local_skill} AS s ON s.category = sc.id
                            WHERE sc.id IN ($repository)  AND sc.id <> " . SITEID;
            $reposlist = $DB->get_records_sql($repos_sql);
            foreach($reposlist as $rl) {
                $repos[$rl->id] = $rl->name;
            }
        } else if ($id > 0) {
            $repos_sql = "SELECT sc.id, sc.name
                            FROM {local_skill_categories} AS sc 
                            JOIN {local_skill} AS s ON s.category = sc.id
                            WHERE s.id = $id";
            $reposlist = $DB->get_records_sql($repos_sql);
            foreach($reposlist as $rl) {
                $repos[$rl->id] = $rl->name;
            }
        // print_object($repos);exit;
        } else {
            foreach($skills as $rl){
                $repos[$rl->id] = $rl->name;
            }
        }
        $context = context_system::instance();
        $options = array(
            'ajax' => 'local_skillrepository/form-repository-selector',
            'multiple' => false,
            'data-contextid' => $context->id,
            'data-includes' => 'all',
        );
        $mform->addElement('autocomplete', 'category', get_string('category', 'local_skillrepository'), $repos, $options);
        $mform->addRule('category', null, 'required', null, 'client');

        $mform->addElement('text', 'name', get_string('name', 'local_skillrepository'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_skillrepository'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');
       /* 
        $mform->addElement('editor', 'description', get_string('description'), array());*/
        $mform->addElement('editor', 'description', get_string('description'),NULL, array("autosave"=>false));
        $mform->setType('description', PARAM_RAW);

        $submit = ($this->_customdata['id'] > 0) ? get_string('update', 'local_skillrepository') : get_string('save', 'local_skillrepository');
        $this->add_action_buttons(true, $submit);
        $mform->disable_form_change_checker();
    }
    
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
    
        $shortname = $data['shortname'];
        $id = $data['id'];
        $category = $data['category'];
        $record = $DB->get_record_sql('SELECT * FROM {local_skill} WHERE shortname = ? AND  id <> ?', array($shortname, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_skillrepository');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_skillrepository');
        }
        if($category <= 0){
            $errors['category'] = get_string('selectcategory', 'local_skillrepository');
        }

        return $errors;
    }
    
}
