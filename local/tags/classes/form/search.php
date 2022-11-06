<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * local tags
 *
 * @package    local_tags
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tags\form;

//It must be included from a Moodle page
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir . '/formslib.php');
use moodleform;

class search extends moodleform {
    public function definition() {
        global $CFG, $DB, $USER;
        $context = (new \local_tags\lib\accesslib())::get_module_context();
        $mform    =& $this->_form;
        $taglist = array(null=>'');
        $sql = "select concat(t.id,'-', t.name) as tagid, name from {tag} t ";
        $joinsql = "JOIN {local_tags} lt ON lt.tagid = t.id";
        if(is_siteadmin()){
            $where = " WHERE 1 = 1";
        } elseif(has_capability('local/costcenter:manage_ownorganization',$context)){
            $where = " WHERE lt.open_costcenterid = :usercostcenter";
        } elseif(has_capability('local/costcenter:manage_owndepartments',$context)){
            $where = " WHERE lt.open_departmentid = :userdepartment";
        } else {
            $where = " WHERE lt.open_costcenterid = :usercostcenter AND (lt.open_departmentid = :userdepartment OR lt.open_departmentid = 0 OR lt.open_departmentid is null) ";
        }

        $order = " order by t.id desc";

        if (!is_siteadmin()) {
            $userorg['usercostcenter'] = $USER->open_costcenterid;
            $userdep['userdepartment'] = $USER->open_departmentid;
        }
        $params = array_merge($userorg, $userdep);
        $records = $DB->get_records_sql_menu($sql.$joinsql.$where.$order, $params);

        // $records = $DB->get_records_sql_menu("select concat(t.id,'-', t.name) as tagid, name from {tag} t order by t.id desc");
        if(isset($records)&&!empty($records))
            $taglist = $taglist+$records;
        $mform->addElement('autocomplete', 'query', '', $taglist);

        $mform->addElement('submit', 'go', get_string('go'));
    }
}
