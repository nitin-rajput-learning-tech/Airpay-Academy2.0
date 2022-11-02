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
class skill_category_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;
        
        $mform->addElement('header', 'create_category_form', get_string('create_newskill_category', 'local_skillrepository'));

        $id = optional_param('id', 0, PARAM_INT);

        //print_object($id);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);



        //$parentid = $this->_customdata['parentid']; 
        
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

        /*print_object($this->_customdata['id']); */
        $edit_id=$this->_customdata['id'];

        $selectoptions = array();
        $selectoptions[null] = get_string('select');
        if(is_siteadmin()){
           /* $skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 ORDER BY name ASC");*/
           //issue OL-2557(to not to select same category as primary skill)

           if($this->_customdata['id'] > 0){

            $skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 and id <> $edit_id ORDER BY name ASC");

           }else{

            $skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 ORDER BY name ASC");

           }
            
        } else {
            $systemcontext = context_system::instance();
            $costcenter=$DB->get_field('user','open_costcenterid',array('id'=>$USER->id));
            /*$skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 and costcenterid=$costcenter  ORDER BY name ASC");*/

            //Issue OL-3189 fixes//
            if($this->_customdata['id'] > 0){

             /* $skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 and costcenterid=$costcenter  ORDER BY name ASC");*/
               $skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 and costcenterid=$costcenter and id <> $edit_id ORDER BY name ASC");
            }else{

                /*$skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 and costcenterid=$costcenter and id <> $edit_id  ORDER BY name ASC");*/
                 $skills = $DB->get_records_sql_menu("select id, name from {local_skill_categories} where parentid = 0 and costcenterid=$costcenter   ORDER BY name ASC");
            }

            //Issue OL-3189 fixes//

            
        }

        if($skills){
            $options = $selectoptions + $skills;
        }else{
            $options = $selectoptions;
        }
        
        $mform->addElement('select', 'parentid', get_string('parent_skillcategory', 'local_skillrepository'), $options);
        $mform->setType('parentid', PARAM_INT);

       

        $mform->addElement('text', 'name', get_string('name', 'local_skillrepository'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');


        /*if($id && isset($parentid)){
            $mform->disabledIf('parentid', 'id');
        }*/

        //$mform->disabledIf('parentid', 'name', 'eq', 'Category1');
        //$mform->disabledIf('parentid', 'name');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_skillrepository'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $submit = ($this->_customdata['id'] > 0) ? get_string('update', 'local_skillrepository') : get_string('save', 'local_skillrepository');
        $this->add_action_buttons(true, $submit);
    }
    
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
    
        $shortname = $data['shortname'];
        $id = $data['id'];
        $record = $DB->get_record_sql('SELECT * FROM {local_skill_categories} WHERE shortname = ? AND  id <> ?', array($shortname, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_skillrepository');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_skillrepository');
        }
        return $errors;
    }
    
}
