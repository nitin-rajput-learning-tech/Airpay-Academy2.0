<?php
namespace local_custom_category\form;
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
 * @subpackage local_custom_category
 */
use moodleform;
use context_system;
use costcenter;
require_once(dirname(__FILE__) . '/../../../../config.php');
global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/costcenter/lib.php');
class custom_category_form extends moodleform {

    public function definition() {
        global $DB,$USER;
        $mform = $this->_form;
        $fid = $this->_customdata['id'];
        $parentid = $this->_customdata['parentid'];
        $costcenterid = $this->_customdata['open_costcenterid'];

        $id = optional_param('id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $context =(new \local_custom_category\lib\accesslib())::get_module_context();
        if($fid && is_siteadmin()){
            $orgname= $DB->get_field('local_costcenter','fullname',array('id'=>$costcenterid));
            $mform->addElement('static','costcentername', get_string('organization', 'local_custom_category'), $orgname);
            $mform->addElement('hidden','open_costcenterid');
        }else{
            local_costcenter_get_hierarchy_fields($mform, $this->_ajaxformdata, $this->_customdata,range(1, 1), false, 'local_custom_category', $context, $multiple = false);
        }

        $parentsql = "SELECT lcc.id, lcc.fullname FROM {local_custom_category} AS lcc WHERE 1 = 1 AND lcc.depth = 1";
        if($fid){
            $parentsql .= " AND lcc.id !=".$fid;
        }
        if(!is_siteadmin()){
            $orgcond = [];
            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $orgcond[] = " lcc.costcenterid = {$costcenterid} ";
            }
            if(!empty($orgcond)){
                $parentsql .= " AND".implode(' OR ', $orgcond);
            }else{
                $parentsql .= " AND 1 <> 1 ";
            }
        }
        $parents = $DB->get_records_sql_menu($parentsql);
        $parents = [0 => get_string('top', 'local_custom_category')] + $parents;

        $coursetype = array(
            'ajax' => 'local_costcenter/form-options-selector',
            'data-contextid' => (\local_costcenter\lib\accesslib::get_module_context())->id,
            'data-action' => 'custom_category_selector',
            'data-options' => json_encode(array('id' => $fid,'type'=>'parent_selector')),
            'class' => 'idparentselect',
            'data-parentclass' => 'open_costcenterid_select',
            'data-class' => 'idparentselect',
            'multiple' => false,
        );
        if($fid){
            $parentname = $DB->get_field('local_custom_category','fullname', array('id'=>$parentid));
            $parentname = $parentname ? $parentname : 'Top';
            $mform->addElement('static','parentname', get_string('parent','local_costcenter'),$parentname);
            $mform->addElement('hidden','parentid');
        } else {
    
            if(!is_siteadmin()){
                $mform->addElement('autocomplete', 'parentid', get_string('parent','local_costcenter'), $parents);
            } else {
                $mform->addElement('autocomplete', 'parentid', get_string('parent','local_costcenter'), $parents,$coursetype);
                $mform->setDefault('parentid', 0);
            }
        }
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name', 'local_custom_category'));
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'shortname', get_string('shortname', 'local_custom_category'), array());
        $mform->setType('shortname', PARAM_RAW);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'];
        $id = $data['id'];
        $record = $DB->get_record_sql('SELECT * FROM {local_custom_category} WHERE shortname = ? AND  id <> ?', array($shortname, $id));
        if (!empty($record)) {
            $errors['shortname'] = get_string('shortnameexists', 'local_custom_category');
        }
        if(strlen($shortname) > 150){
            $errors['shortname'] = get_string('shortnamelengthexceeds', 'local_custom_category');
        }
        return $errors;
    }

}
