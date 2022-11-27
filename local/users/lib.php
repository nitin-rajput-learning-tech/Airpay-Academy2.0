<?php

/*
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
 * @subpackage local_users
 */

use local_users\output\team_status_lib;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot.'/user/editlib.php');
/**
 * Description: To display the form in modal on modal trigger event.
 * @param  [array] $args [the parameters required for the form]
 * @return        [modal content]
 */
function local_users_output_fragment_new_create_user($args) {
    global $CFG, $DB, $PAGE;
    $args = (object) $args;
    $context = $args->context;
    $o = '';
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => true,
        'subdirs' => false
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);
    if ($args->id > 0) {
        $heading = 'Update User';
        $collapse = false;
        $data = $DB->get_record('user', array('id' => $args->id));
        unset($data->password);
        useredit_load_preferences($data);
        $mform = new local_users\forms\create_user(null, array('editoroptions' => $editoroptions,
            'form_status' => $args->form_status, 'id' => $data->id, 'org' => $data->open_costcenterid,
            'dept' => $data->open_departmentid, 'subdept' => $data->open_subdepartment,
            'open_positionid' => $data->open_positionid, 'open_domainid' => $data->open_domainid),
            'post', '', null, true, $formdata);
        $mform->set_data($data);
    } else {
        $mform = new local_users\forms\create_user(null, array('editoroptions' => $editoroptions,
            'form_status' => $args->form_status), 'post', '', null, true, $formdata);
    }

    if (!empty($args->jsonformdata) && strlen($args->jsonformdata) > 2) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
    $formheaders = array_keys($mform->formstatus);
    $nextform = array_key_exists($args->form_status, $formheaders);
    if ($nextform === false) {
        return false;
    }
    $renderer = $PAGE->get_renderer('local_users');
    ob_start();
    $formstatus = array();
    foreach (array_values($mform->formstatus) as $k => $mformstatus) {
        $activeclass = $k == $args->form_status ? 'active' : '';
        $formstatus[] = array('name' => $mformstatus, 'activeclass' => $activeclass);
    }
    $formstatusview = new \local_users\output\form_status($formstatus);
    $o .= $renderer->render($formstatusview);
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
    return $o;
}
/**
 * Description: User fullname filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function users_filter ($mform, $query='', $searchanywhere=false, $page=0, $perpage=25) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslist = array();
    $data = data_submitted();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT id, concat(firstname,' ',lastname) as fullname FROM {user}
         WHERE id > :adminuserid AND deleted = :deleted AND suspended = :suspended AND id <> :userid  ";

    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT id, concat(firstname,' ',lastname) as fullname FROM {user}
         WHERE id > :adminuserid AND open_costcenterid = :costcenterid AND deleted = :deleted
          AND suspended = :suspended AND id <> :userid  ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT id, concat(firstname,' ',lastname) as fullname FROM {user}
         WHERE id > :adminuserid AND open_costcenterid = :costcenterid AND open_departmentid =
          :departmentid AND deleted = :deleted AND suspended = :suspended AND id <> :userid ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    if (!empty($query)) {
        if ($searchanywhere) {
            $likesql = $DB->sql_like("CONCAT(firstname, ' ',lastname)", "'%$query%'", false);
            $userslist_sql .= " AND $likesql ";
        } else {
            $likesql = $DB->sql_like("CONCAT(firstname, ' ',lastname)", "'$query%'", false);
            $userslist_sql .= " AND $likesql ";
        }
    }
    if (isset($data->users)&&!empty(($data->users))) {

        list($usersql, $userparam) = $DB->get_in_or_equal($data->users, SQL_PARAMS_NAMED);
        $userslist_sql .= " AND id $usersql ";
        $userslistparams = $userparam + $userslistparams;
    }
    if (!empty($query)||empty($mform)) {
        $userslist = $DB->get_records_sql($userslist_sql, $userslistparams, $page, $perpage);
        return $userslist;
    }
    if ((isset($data->users)&&!empty($data->users))) {
         $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams, $page, $perpage);
    }

    $options = array(
                    'ajax' => 'local_courses/form-options-selector',
                    'multiple' => true,
                    'data-action' => 'users',
                    'data-options' => json_encode(array('id' => 0)),
                    'placeholder' => get_string('users')
    );
    $select = $mform->addElement('autocomplete', 'users', '', $userslist, $options);
    $mform->setType('users', PARAM_RAW);
}
/**
 * Description: User email filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function email_filter($mform, $query='', $searchanywhere=false, $page=0, $perpage=25) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslist = array();
    $data = data_submitted();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT id, email as fullname FROM {user} WHERE id > :adminuserid AND deleted = :deleted AND
         suspended = :suspended AND id <> :userid ";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT id, email as fullname FROM {user} WHERE id >
         :adminuserid AND open_costcenterid = :costcenterid AND deleted = :deleted AND suspended = :suspended AND id <> :userid ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT id, email as fullname FROM {user} WHERE id >
         :adminuserid AND open_costcenterid = :costcenterid AND open_departmentid =
          :departmentid AND deleted = :deleted AND suspended = :suspended AND id <> :userid ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    if (!empty($query)) {
        if ($searchanywhere) {
            $likesql = $DB->sql_like('email', "'%$query%'", false);
            $userslist_sql .= " AND $likesql ";
        } else {
            $likesql = $DB->sql_like('email', "'$query%'", false);
            $userslist_sql .= " AND $likesql ";
        }
    }
    if (isset($data->email)&&!empty(($data->email))) {

        $implode = implode(',', $data->email);
        list($mailsql, $mailparam) = $DB->get_in_or_equal($data->email, SQL_PARAMS_NAMED);
        $userslist_sql .= " AND id $mailsql ";
        $userslistparams = $mailparam + $userslistparams;
    }
    if (!empty($query)||empty($mform)) {
        $userslist = $DB->get_records_sql($userslist_sql, $userslistparams, $page, $perpage);
        return $userslist;
    }
    if ((isset($data->email)&&!empty($data->email))) {

        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams, $page, $perpage);
    }
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'email',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('email')
    );
    $select = $mform->addElement ('autocomplete', 'email', '', $userslist, $options);
    $mform->setType('email', PARAM_RAW);
}
/**
 * Description: User employeeid filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 * @param  string  $query          [text inserted in filter]
 * @param  boolean $searchanywhere [description]
 * @param  integer $page           [page value]
 * @param  integer $perpage        [entities per page]
 */
function employeeid_filter ($mform, $query='', $searchanywhere=false, $page=0, $perpage=25) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslist = array();
    $data = data_submitted();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT id, open_employeeid as fullname FROM {user} WHERE id >
         :adminuserid AND deleted = :deleted AND suspended = :suspended AND id <> :userid";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT id, open_employeeid as fullname FROM {user} WHERE id > :adminuserid
         AND open_costcenterid = :costcenterid AND deleted = :deleted AND suspended = :suspended
          AND id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT id, open_employeeid as fullname FROM {user} WHERE id > :adminuserid
         AND open_costcenterid = :costcenterid AND open_departmentid = :departmentid AND
          deleted = :deleted AND suspended = :suspended AND id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    if (!empty($query)) {
        if ($searchanywhere) {
            $likesql = $DB->sql_like('open_employeeid', "'%$query%'", false);
            $userslist_sql .= " AND $likesql ";
        } else {
            $likesql = $DB->sql_like('open_employeeid', "'$query%'", false);
            $userslist_sql .= " AND $likesql ";
        }
    }
    if (isset($data->idnumber)&&!empty(($data->idnumber))) {
        list($idsql, $idparam) = $DB->get_in_or_equal($data->idnumber, SQL_PARAMS_NAMED);
        $userslist_sql .= " AND id $idsql ";
        $userslistparams = $idparam + $userslistparams;
    }
    if (!empty($query)||empty($mform)) {
        $userslist = $DB->get_records_sql($userslist_sql, $userslistparams, $page, $perpage);
        return $userslist;
    }
    if ((isset($data->idnumber)&&!empty($data->idnumber))) {
        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    }
    $options = array(
        'ajax' => 'local_courses/form-options-selector',
        'multiple' => true,
        'data-action' => 'employeeid',
        'data-options' => json_encode(array('id' => 0)),
        'placeholder' => get_string('idnumber', 'local_users')
    );
    $select = $mform->addElement('autocomplete', 'idnumber', '', $userslist, $options);
    $mform->setType('idnumber', PARAM_RAW);
}
/**
 * Description: User designation filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function designation_filter($mform) {
    global $DB, $USER;
    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT id, open_designation FROM {user} WHERE id > :adminuserid
         AND deleted = :deleted AND suspended = :suspended AND id <> :userid";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT id, open_designation FROM {user} WHERE id > :adminuserid
         AND open_costcenterid = :costcenterid AND deleted = :deleted AND suspended =
          :suspended AND id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT id, open_designation FROM {user} WHERE id > :adminuserid
         AND open_costcenterid = :costcenterid AND open_departmentid = :departmentid AND
          deleted = deleted AND suspended = :suspended AND id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'designation', '',
     $userslist, array('placeholder' => get_string('designation', 'local_users')));
    $mform->setType('idnumber', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * Description: User location filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function location_filter($mform) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT u.city, u.city AS name FROM {user} AS u WHERE
         u.id > :adminuserid AND u.deleted = :deleted AND u.suspended = :suspended AND u.id <> :userid ";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT u.city, u.city AS name FROM {user} AS u WHERE u.id > :adminuserid
         AND u.open_costcenterid = :costcenterid AND u.deleted = :deleted AND u.suspended = :suspended
          AND u.id <> :userid ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT u.city, u.city AS name FROM {user} AS u WHERE u.id >
         :adminuserid AND u.open_costcenterid = :costcenterid AND u.open_departmentid =
          :departmentid AND u.deleted = :deleted AND u.suspended = :suspended AND u.id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    $userslist_sql .= " AND u.city != '' AND u.city IS NOT NULL GROUP BY u.city ";
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'location', '', $userslist, array('placeholder' =>
     get_string('location', 'local_users')));
    $mform->setType('idnumber', PARAM_RAW);
    $select->setMultiple(true);
}

/**
 * Description: User hrmsrole filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function hrmsrole_filter($mform) {
    global $DB, $USER;
    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole as name FROM {user} AS u WHERE u.id >
         :adminuserid AND u.deleted = :deleted AND u.suspended = :suspended AND u.id <> :userid ";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole as name FROM
         {user} AS u WHERE u.id > :adminuserid AND u.open_costcenterid = :costcenterid
          AND u.deleted = :deleted AND u.suspended = :suspended ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole as name FROM {user} AS u WHERE u.id >
         :adminuserid AND u.open_costcenterid = :costcenterid AND u.open_departmentid = :departmentid
          AND u.deleted = :deleted AND u.suspended = :suspended ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    $userslist_sql .= " AND u.open_hrmsrole != '' AND u.open_hrmsrole IS NOT NULL GROUP BY u.open_hrmsrole ";
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'hrmsrole', '', $userslist, array('placeholder' =>
     get_string('open_hrmsrole', 'local_users')));
    $mform->setType('hrmsrole', PARAM_RAW);
    $select->setMultiple(true);
}

/**
 * Description: User band filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function band_filter($mform) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT id, open_band FROM {user} WHERE id > :adminuserid AND deleted = :deleted
         AND suspended = :suspended AND id <> :userid";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT id, open_band FROM {user} WHERE id > :adminuserid AND open_costcenterid =
         :costcenterid AND deleted = :deleted AND suspended = :suspended AND id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT id, open_band FROM {user} WHERE id > :adminuserid AND open_costcenterid =
         :costcenterid AND open_departmentid = :departmentid AND deleted = :deleted AND suspended = :suspended
          AND id <> :userid ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'band', '', $userslist, array('placeholder' => get_string('band', 'local_users')));
    $mform->setType('idnumber', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * Description: User name filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function username_filter($mform) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $userslist_sql = "SELECT id, username FROM {user} WHERE id > :adminuserid AND deleted = :deleted
         AND suspended = :suspended AND id <> :userid";
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $userslist_sql = "SELECT id, username FROM {user} WHERE id > :adminuserid AND open_costcenterid =
         :costcenterid AND deleted = :deleted AND suspended = :suspended AND id <> :userid";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $userslist_sql = "SELECT id, username FROM {user} WHERE id > :adminuserid AND open_costcenterid =
         :costcenterid AND open_departmentid = :departmentid AND deleted = :deleted AND suspended =
          :suspended AND id <> :userid ";
        $userslistparams['costcenterid'] = $USER->open_costcenterid;
        $userslistparams['departmentid'] = $USER->open_departmentid;
    }
    $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
    $select = $mform->addElement('autocomplete', 'username', '', $userslist, array('placeholder' => get_string('username')));
    $mform->setType('username', PARAM_RAW);
    $select->setMultiple(true);
}
/**
 * Description: User custom filter code
 * @param  [mform object]  $mform          [the form object where the form is initiated]
 */
function custom_filter($mform) {
    global $DB, $USER;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $filterv = $DB->get_field('local_filters', 'filters', array('plugins' => 'users'));
    $filterv = explode(',', $filterv);
    foreach ($filterv as $fieldvalue) {
        $userslistparams = array('adminuserid' => 2, 'deleted' => 0, 'suspended' => 0, 'userid' => $USER->id);
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
            $userslist_sql = "SELECT id, $fieldvalue FROM {user} WHERE id > :adminuserid AND deleted =
             :deleted AND suspended = :suspended AND id <> :userid ";
        } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
            $userslist_sql = "SELECT id, $fieldvalue FROM {user} WHERE id > :adminuserid AND
             open_costcenterid = :costcenterid AND deleted = :deleted AND suspended = :suspended AND
              id <> :userid ";
            $userslistparams['costcenterid'] = $USER->open_costcenterid;
        } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
            $userslist = $DB->get_records_sql_menu("SELECT id, $fieldvalue FROM {user} WHERE id >
             :adminuserid AND open_costcenterid = :costcenterid AND open_departmentid = :departmentid
              AND deleted = :deleted AND suspended = :suspended AND id <> :userid ");
            $userslistparams['costcenterid'] = $USER->open_costcenterid;
            $userslistparams['departmentid'] = $USER->open_departmentid;
        }
        $userslist = $DB->get_records_sql_menu($userslist_sql, $userslistparams);
        $select = $mform->addElement('autocomplete', $fieldvalue, '', $userslist, array('placeholder'
         => get_string($fieldvalue, 'local_users')));
        $mform->setType($fieldvalue, PARAM_RAW);
        $select->setMultiple(true);
    }
}
// OL-1042 Add Target Audience to Classrooms//
/**
 * [globaltargetaudience_elementlist description]
 * @param  [type] $mform       [description]
 * @param  [type] $elementlist [description]
 * @return [type]              [description]
 */
function globaltargetaudience_elementlist($mform, $elementlist) {
    global $CFG, $DB, $USER;


    $context = (new \local_users\lib\accesslib())::get_module_context();
    $params = array();
    $params['deleted'] = 0;
    $params['suspended'] = 0;
    if ($mform->modulecostcenter == 0 && (is_siteadmin()||has_capability('local/costcenter:manage_multiorganizations', $context))) {
        $main_sql = "";
    } else if (has_capability('local/costcenter:manage_ownorganization', $context)) {
         $costcenterid = $mform->modulecostcenter ? $mform->modulecostcenter : $USER->open_costcenterid;
        $main_sql = " AND u.suspended = :suspended AND u.deleted =:deleted  AND u.open_costcenterid = :costcenterid ";
        $params['costcenterid'] = $costcenterid;
    } else if (has_capability('local/costcenter:manage_owndepartments', $context)) {
        $main_sql = " AND u.suspended = :suspended AND u.deleted = :deleted  AND u.open_costcenterid = :costcenterid
         AND u.open_departmentid = :departmentid ";
        $params['costcenterid'] = $USER->open_costcenterid;
        $params['departmentid'] = $USER->open_departmentid;
    }
    $dbman = $DB->get_manager();
    if (in_array('group', $elementlist)) {
        $groupslist[null] = get_string('all');
        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context) ) {
            if ($dbman->table_exists('local_groups')) {
                $groupslist += $DB->get_records_sql_menu("SELECT c.id, c.name FROM {local_groups} g, {cohort}
                 c  WHERE c.visible = :visible AND c.id = g.cohortid ", array('visible' => 1));
            }
        } else if (has_capability('local/costcenter:manage_ownorganization', $context)) {
            $groupslist += $DB->get_records_sql_menu("SELECT c.id, c.name FROM {local_groups} g, {cohort} c
              WHERE c.visible = :visible AND c.id = g.cohortid AND g.costcenterid = :costcenterid ",
               array('costcenterid' => $USER->open_costcenterid, 'visible' => 1));
        } else if (has_capability('local/costcenter:manage_owndepartments', $context)) {
            $groupslist += $DB->get_records_sql_menu("SELECT c.id, c.name FROM {local_groups} g,
             {cohort} c WHERE c.visible = 1 AND c.id = g.cohortid AND g.costcenterid = :costcenterid AND
               g.departmentid = :departmentid ", array('costcenterid' => $USER->open_costcenterid,
                'departmentid' => $USER->open_departmentid, 'visible' => 1));
        }
        $selectgroup = $mform->addElement('autocomplete', 'open_group', get_string('open_group', 'local_users')
            , $groupslist);
        $mform->setType('open_group', PARAM_RAW);
        $mform->addHelpButton('open_group', 'groups', 'local_users');
        $selectgroup->setMultiple(true);
    }
    if (in_array('hrmsrole', $elementlist)) {
        $hrmsrole_details[null] = get_string('all');
        $hrmsrole_sql = "SELECT u.open_hrmsrole, u.open_hrmsrole AS hrmsrolevalue FROM {user} AS u WHERE u.id
         > 2 $main_sql AND u.open_hrmsrole IS NOT NULL GROUP BY u.open_hrmsrole";
        $hrmsrole_details += $DB->get_records_sql_menu($hrmsrole_sql, $params);
        $selecthrmsrole = $mform->addElement('autocomplete', 'open_hrmsrole',  get_string('hrmrole', 'local_users'),
         $hrmsrole_details);
        $mform->setType('open_hrmsrole', PARAM_RAW);
        $mform->addHelpButton('open_hrmsrole', 'role', 'local_users');
        $selecthrmsrole->setMultiple(true);
    }
    if (in_array('designation', $elementlist)) {
        $designation_details[null] = get_string('all');
        $designation_sql = "SELECT u.open_designation,u.open_designation AS designationvalue FROM {user} AS
         u WHERE u.id > 2 $main_sql AND u.open_designation IS NOT NULL GROUP BY u.open_designation";
        $designation_details += $DB->get_records_sql_menu($designation_sql, $params);
        $selectdesignation = $mform->addElement('autocomplete', 'open_designation',
         get_string('open_designation', 'local_users'), $designation_details);
        $mform->setType('open_designation', PARAM_RAW);
        $mform->addHelpButton('open_designation', 'designation', 'local_users');
        $selectdesignation->setMultiple(true);
    }
    if (in_array('location', $elementlist)) {
        $location_details[null] = get_string('all');
        $location_sql = "SELECT u.city, u.city AS locationvalue FROM {user} AS u WHERE u.id > 2 $main_sql AND
         u.city IS NOT NULL GROUP BY u.city    ";
        $location_details += $DB->get_records_sql_menu($location_sql, $params);
        $selectlocation = $mform->addElement('autocomplete', 'open_location', get_string('open_location',
         'local_users'), $location_details);
        $mform->setType('open_location', PARAM_RAW);
         $mform->addHelpButton('open_location', 'location', 'local_users');
        $selectlocation->setMultiple(true);
    }
    if (in_array('branch', $elementlist)) {
        $branch_details[null] = get_string('all');
        $branch_sql = "SELECT u.open_branch,u.open_branch AS branchvalue FROM {user} AS u WHERE u.id > 2
         $main_sql AND u.open_branch IS NOT NULL GROUP BY u.open_branch";
        $branch_details += $DB->get_records_sql_menu($branch_sql, $params);
        $selectbranch = $mform->addElement('autocomplete', 'open_branch', get_string('open_branch',
         'local_users'), $branch_details);
        $mform->setType('open_branch', PARAM_RAW);
        $selectbranch->setMultiple(true);
    }
    if (in_array('band', $elementlist)) {
        $band_details[null] = get_string('all');
        $band_sql = "SELECT u.open_band,u.open_band AS bandvalue FROM {user} AS u WHERE u.id > 2 $main_sql AND
         u.open_band IS NOT NULL GROUP BY u.open_band";
        $band_details += $DB->get_records_sql_menu($band_sql, $params);
        $selectband = $mform->addElement('autocomplete', 'open_band', get_string('open_band', 'local_users'), $band_details);
        $mform->setType('open_band', PARAM_RAW);
        $selectband->setMultiple(true);
    }
}


/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_users_leftmenunode() {
    global $USER, $DB;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $usersnode = '';
    $key = '';
    if (has_capability('local/users:manage', $systemcontext) || has_capability('local/users:view',
      $systemcontext) || is_siteadmin()) {
        $usersnode .= html_writer::start_tag('li', array('id' => 'id_leftmenu_users', 'class' => 'pull-left user_nav_div users'));
            $users_url = new moodle_url('/local/users/index.php');
            $users = html_writer::link($users_url, '<i class="fa fa-user-plus" aria-hidden="true"></i>
                <span class="user_navigation_link_text">'.get_string('manage_users', 'local_users').'</span>',
                 array('class' => 'user_navigation_link'));
            $usersnode .= $users;
        $usersnode .= html_writer::end_tag('li');
        $key = array('2' => $usersnode);
    }
    return $key;
}

function local_users_quicklink_node() {
    global $DB, $PAGE, $USER, $CFG, $OUTPUT;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $local_users = '';
    if (is_siteadmin() || has_capability('local/users:view', $systemcontext)) {
        $sql = "SELECT count(id) FROM {user} WHERE id > 2  AND deleted = :deleted ";
        $suspendsql = " AND suspended = :suspended ";

        $params = array();
        $params['deleted'] = 0;

        $activeparams = array();
        $activeparams['suspended'] = 0;
        $activeparams['deleted'] = 0;

        $inactiveparams = array();
        $inactiveparams['suspended'] = 1;
        $inactiveparams['deleted'] = 0;

        if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
            $sql .= "";
        } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
            //costcenterid concating
            $sql .= " AND open_costcenterid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid;
            $activeparams['costcenterid'] = $USER->open_costcenterid;
            $inactiveparams['costcenterid'] = $USER->open_costcenterid;
        } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
            //costcenterid concating
            $sql .= " AND open_costcenterid = :costcenterid ";
            $params['costcenterid'] = $USER->open_costcenterid;
            $activeparams['costcenterid'] = $USER->open_costcenterid;
            $inactiveparams['costcenterid'] = $USER->open_costcenterid;

            //departmentid concating
            $sql .= " AND open_departmentid = :departmentid ";
            $params['departmentid'] = $USER->open_departmentid;
            $activeparams['departmentid'] = $USER->open_departmentid;
            $inactiveparams['departmentid'] = $USER->open_departmentid;
        }

        $count_activeusers = $DB->count_records_sql($sql.$suspendsql, $activeparams);
        $count_inactiveusers = $DB->count_records_sql($sql.$suspendsql, $inactiveparams);
        $count_users = $DB->count_records_sql($sql, $params);

        $percent = round(($count_activeusers / $count_users) * 100);

        $percent = (int)$percent;

        //local users count content
        $local_users = $PAGE->requires->js_call_amd('local_users/newuser', 'load', array());

        $countinformation = array();

        $displayline = false;
        $hascapablity = false;

        if (has_capability('local/users:create', $systemcontext) || is_siteadmin()) {
            $displayline = true;
            $hascapablity = true;
            $countinformation['create_element'] = html_writer::link('javascript:void(0)', get_string('create'),
             array('class' => 'quick_nav_link goto_local_users course_extended_menu_itemlink', 'data-action' =>
              'createusermodal', 'title' => get_string('createuser', 'local_users'), 'data-action' =>
               'createusermodal',  'onclick' => '(function(e){ require
                ("local_users/newuser").init({selector:"createusermodal", context:1, userid:'.$USER->id.',
                form_status:0}) })(event)'));
        }
        $countinformation['node_header_string'] = get_string('manage_br_users', 'local_users');
        $countinformation['pluginname'] = 'users';
        $countinformation['plugin_icon_class'] = 'fa fa-user-plus';
        $countinformation['contextid'] = $systemcontext->id;
        $countinformation['userid'] = $USER->id;
        $countinformation['create'] = $hascapablity;
        $countinformation['viewlink_url'] = $CFG->wwwroot.'/local/users/index.php';
        $countinformation['view'] = true;
        $countinformation['displaystats'] = true;
        $countinformation['percentage'] = $percent;
        $countinformation['count_total'] = $count_users;
        $countinformation['count_inactive'] = $count_inactiveusers;
        $countinformation['inactive_string'] = get_string('inactive_string', 'block_quick_navigation');
        $countinformation['count_active'] = $count_activeusers;
        if ($count_activeusers >= 0) {
            $countinformation['count_activelink_url'] = $CFG->wwwroot.'/local/users/index.php?status=active';
        }
        if ($count_inactiveusers >= 0) {
             $countinformation['count_inactivelink_url'] = $CFG->wwwroot.'/local/users/index.php?status=inactive';
        }
        $countinformation['space_count'] = 'two';
        $local_users .= $OUTPUT->render_from_template('block_quick_navigation/quicklink_node', $countinformation);
    }
    return array('1' => $local_users);
}


/*
* Author Sarath
* return count of users under selected costcenter
* @return  [type] int count of users
*/
function costcenterwise_users_count($costcenter, $department = false, $subdepartment=false) {
    global $USER, $DB, $CFG;
        $params = array();
        $params['costcenter'] = $costcenter;
        $countusersql = "SELECT count(id) FROM {user} WHERE open_costcenterid = :costcenter AND deleted = 0";
    if ($department) {
            $countusersql .= " AND open_departmentid = :department ";
            $params['department'] = $department;
    }
    if ($subdepartment) {
            $countusersql .= " AND open_subdepartment = :subdepartment ";
            $params['subdepartment'] = $subdepartment;
    }
        $activesql = " AND suspended = 0 ";
        $inactivesql = " AND suspended = 1 ";
        $countusers = $DB->count_records_sql($countusersql, $params);
        $activeusers = $DB->count_records_sql($countusersql.$activesql, $params);
        $inactiveusers = $DB->count_records_sql($countusersql.$inactivesql, $params);
    if ($countusers >= 0) {
        if ($costcenter) {
                $viewlink_url = $CFG->wwwroot.'/local/users/index.php?costcenterid='. $costcenter;
        }
        if ($department) {
                $viewlink_url = $CFG->wwwroot.'/local/users/index.php?departmentid='. $department;
        }
        if ($subdepartment) {
                $viewlink_url = $CFG->wwwroot.'/local/users/index.php?subdepartmentid='. $subdepartment;
        }
    }

    if ($activeusers >= 0) {
        if ($costcenter) {
                $count_activelink_url = $CFG->wwwroot.'/local/users/index.php?status=active&costcenterid='.$costcenter;
        }
        if ($department) {
                $count_activelink_url = $CFG->wwwroot.'/local/users/index.php?status=active&departmentid='.$department;
        }
        if ($subdepartment) {
                $count_activelink_url = $CFG->wwwroot.'/local/users/index.php?status=active&subdepartmentid='.$subdepartment;
        }
    }
    if ($inactiveusers >= 0) {
        if ($costcenter) {
                $count_inactivelink_url = $CFG->wwwroot.'/local/users/index.php?status=inactive&costcenterid='.$costcenter;
        }
        if ($department) {
                $count_inactivelink_url = $CFG->wwwroot.'/local/users/index.php?status=inactive&departmentid='.$department;
        }
        if ($subdepartment) {
                $count_inactivelink_url = $CFG->wwwroot.'/local/users/index.php?status=inactive&subdepartmentid='.$subdepartment;
        }
    }
    return array('totalusers' => $countusers, 'activeusercount' => $activeusers, 'inactiveusercount' =>
     $inactiveusers, 'viewlink_url' => $viewlink_url, 'count_activelink_url' => $count_activelink_url,
      'count_inactivelink_url' => $count_inactivelink_url);
}


/*
* Author Sarath
* return count of users under selected costcenter
* @return  [type] int count of users
*/
function manage_users_count($stable, $filterdata) {
    global $DB, $PAGE, $USER, $CFG, $OUTPUT;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
     $statustype = $stable->status;
     $totalcostcentercount = $stable->costcenterid;
     $totaldepartmentcount = $stable->departmentid;
     $totalsubdepartmentcount = $stable->subdepartmentid;
    $countsql = "SELECT  count(u.id) ";
    $selectsql = "SELECT  u.* ,lc.fullname AS costcentername ,(SELECT fullname FROM {local_costcenter}
     WHERE id=u.open_departmentid) AS departmentname ";
    $formsql = " FROM {user} AS u
         JOIN {local_costcenter} AS lc ON lc.id=u.open_costcenterid
         WHERE u.id > 2 AND u.deleted = 0 ";
    $params = array();
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $formsql .= "";
    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $formsql .= " AND open_costcenterid = :costcenterid ";
        $params['costcenterid'] = $USER->open_costcenterid;
    } else if (!is_siteadmin() && has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $formsql .= " AND open_costcenterid = :costcenterid AND open_departmentid = :departmentid ";
        $params['costcenterid'] = $USER->open_costcenterid;
        $params['departmentid'] = $USER->open_departmentid;
    }
    if (isset($filterdata->search_query) && trim($filterdata->search_query) != '') {
        $formsql .= " AND (u.username LIKE :search1 OR concat(u.firstname,' ',u.lastname)
         LIKE :search2 OR u.email LIKE :search3 OR u.open_employeeid LIKE :search4 )";
        $params['search1'] = '%'.trim($filterdata->search_query).'%';
        $params['search2'] = '%'.trim($filterdata->search_query).'%';
        $params['search3'] = '%'.trim($filterdata->search_query).'%';
        $params['search4'] = '%'.trim($filterdata->search_query).'%';
    }
    if (!empty($filterdata->idnumber)) {
        $idnumbers = explode(',', $filterdata->idnumber);
        list($relatedidnumbersql, $relatedidnumberparams) = $DB->get_in_or_equal($idnumbers, SQL_PARAMS_NAMED, 'idnumber');
        $params = array_merge($params, $relatedidnumberparams);
        $formsql .= " AND u.id $relatedidnumbersql";
    }

    if (!empty($filterdata->email)) {
        $emails = explode(',', $filterdata->email);
        list($relatedemailsql, $relatedemailparams) = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED, 'email');
        $params = array_merge($params, $relatedemailparams);
        $formsql .= " AND u.id $relatedemailsql";
    }
    if (!empty($filterdata->organizations)) {
        $organizations = explode(',', $filterdata->organizations);
        list($relatedeorganizationssql, $relatedorganizationsparams) = $DB->get_in_or_equal($organizations,
         SQL_PARAMS_NAMED, 'organizations');
        $params = array_merge($params, $relatedorganizationsparams);
        $formsql .= " AND u.open_costcenterid $relatedeorganizationssql";
    }
    if (!empty($filterdata->departments)) {
        $departments = explode(',', $filterdata->departments);
        list($relatededepartmentssql, $relateddepartmentsparams) = $DB->get_in_or_equal($departments,
         SQL_PARAMS_NAMED, 'departments');
        $params = array_merge($params, $relateddepartmentsparams);
        $formsql .= " AND u.open_departmentid $relatededepartmentssql";
    }
    if (!empty($filterdata->subdepartment)) {
        $subdepartment = explode(',', $filterdata->subdepartment);
        list($relatedesubdepartmentsql, $relatedsubdepartmentparams) = $DB->get_in_or_equal($subdepartment,
         SQL_PARAMS_NAMED, 'subdepartment');
        $params = array_merge($params, $relatedsubdepartmentparams);
        $formsql .= " AND u.open_subdepartment $relatedesubdepartmentsql";
    }
    if (!empty($filterdata->location)) {
        $locations = explode(',', $filterdata->location);
        list($locationsql, $locationparams) = $DB->get_in_or_equal($locations, SQL_PARAMS_NAMED, 'location');
        $params = array_merge($params, $locationparams);
        $formsql .= " AND u.open_location {$locationsql} ";
    }

    if (!empty($filterdata->hrmsrole)) {
        $hrmsroles = explode(',', $filterdata->hrmsrole);
        list($hrmsrolesql, $hrmsroleparams) = $DB->get_in_or_equal($hrmsroles, SQL_PARAMS_NAMED, 'hrmsrole');
        $params = array_merge($params, $hrmsroleparams);
        $formsql .= " AND u.open_hrmsrole {$hrmsrolesql} ";
    }
    if (!empty($filterdata->status)) {
        $status = explode(',', $filterdata->status);
        if (!(in_array('active', $status) && in_array('inactive', $status))) {
            if (in_array('active' , $status)) {
                $formsql .= " AND u.suspended = 0";
            } else if (in_array('inactive' , $status)) {
                $formsql .= " AND u.suspended = 1";
            }
        }
    }
    $ordersql = " ORDER BY u.id DESC ";
    $totalusers = $DB->count_records_sql($countsql. $formsql/*.$ordersql*/, $params);

    $activesql = " AND u.suspended = :suspended ";
    $params['suspended'] = 0;
    $activeusers = $DB->count_records_sql($countsql.$formsql.$activesql/*.$ordersql*/, $params);

    $params['suspended'] = 1;
    $inactiveusers = $DB->count_records_sql($countsql.$formsql.$activesql/*.$ordersql*/, $params);
    $users = $DB->get_records_sql($selectsql.$formsql.$ordersql, $params, $stable->start, $stable->length);
        return array('totalusers' => $totalusers, 'activeusercount' => $activeusers,
         'inactiveusercount' => $inactiveusers, 'users' => $users);
}

/*
* Author Sarath
* return count of users under selected costcenter
* @return  [type] int count of users
*/
function manage_users_content($stable, $users/*,$filterdata*/) {
    global $DB, $PAGE, $USER, $CFG, $OUTPUT;

    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $userslist = $users['users'];
    $data = array();

    foreach ($userslist as $user) {

        $list = array();
        $line = array();
        $user_picture = new user_picture($user, array('size' => 60, 'class' => 'userpic', 'link' => false));
        $user_picture = $user_picture->get_url($PAGE);
        $userpic = $user_picture->out();
        $list['userpic'] = $userpic;

        $list['empid'] = ($user->open_employeeid) ? $user->open_employeeid : '--';
        $useremail = $user->email;
        if (strlen($useremail) > 24) {
            $useremail = substr($useremail, 0, 24).'...';
        }
        $list['email'] = !empty($useremail) ? $useremail : 'N/A';
        $organization = $user->costcentername;
        $dept = $user->departmentname;
        if (!$dept) {
            $dept = 'N/A';
        }

        $sql = "SELECT u.id as idnumber_value, u.open_department, c.fullname AS departmentname
                    from {user} as u
                        JOIN {local_costcenter} AS c ON c.id = u.open_department";
        $orgstring = strlen($organization) > 24 ? substr($organization, 0, 24)."..." : $organization;
        $list['org'] = $organization;
        $list['orgstring'] = $orgstring;
        $deptstring = strlen($dept) > 24 ? substr($dept, 0, 24)."..." : $dept;
        $designation = $user->open_designation;
        $designationstring = strlen($user->open_designation) > 14 ? substr($user->open_designation, 0, 14).
        "..." : $user->open_designation;

        $list['deptstring'] = $deptstring;
        $list['dept'] = $dept;
        $list['group'] = $user->open_group ? $user->open_group : 'N/A';
        $list['level'] = $user->open_level ? $user->open_level : 'N/A';
        $list['phno'] = ($user->phone1) ? $user->phone1 : '--';
        $list['designation'] = $designation;
        $list['designationstring'] = ($designationstring) ? $designationstring : '--';
        if (!empty($user->open_supervisorid)) {
            $supervisior = $DB->get_field_sql("SELECT CONCAT(firstname,' ',lastname) AS fullname
                 FROM {user} WHERE id = :supervisiorid", array('supervisiorid' => $user->open_supervisorid));
            $supervisiorstring = strlen($supervisior) > 12 ? substr($supervisior, 0, 12)."..." : $supervisior;
            $list['supervisor'] = $supervisior;
            $list['supervisiorstring'] = $supervisiorstring;
        } else {
            $list['supervisiorstring'] = '--';
        }
        $list['lastaccess'] = ($user->lastaccess) ? format_time(time() - $user->lastaccess) : get_string('never');
        $list['userid'] = $user->id;
        $list['fullname'] = fullname($user);
        if (has_capability('local/users:manage', (new \local_users\lib\accesslib())::get_module_context()) || is_siteadmin()) {
            $list['visible'] = $user->suspended;
        }
        if (is_siteadmin() || has_capability('local/users:edit', (new \local_users\lib\accesslib())::get_module_context())) {
                $list['editcap'] = 1;
        } else {
                $list['editcap'] = 0;
        }
        if (is_siteadmin() || has_capability('local/users:delete',(new \local_users\lib\accesslib())::get_module_context())) {
                $list['delcap'] = 1;
        } else {
                $list['delcap'] = 0;
        }
            $data[] = $list;
    }
    return $data;
}

/*
* Author Sarath
* return filterform
*/
function users_filters_form($filterparams) {
    global $CFG;

    require_once($CFG->dirroot . '/local/courses/filters_form.php');

    $systemcontext=(new \local_users\lib\accesslib())::get_module_context();
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $mform = new filters_form(null, array('filterlist' => array('organizations', 'departments',
            'subdepartment', 'email', 'employeeid', 'status', 'location', 'hrmsrole'), 'courseid' => 1,
             'enrolid' => 0, 'plugins' => array('users', 'costcenter'), 'filterparams' => $filterparams));
    } else if (has_capability('local/costcenter:manage_ownorganization', $systemcontext)) {
        $mform = new filters_form(null, array('filterlist' => array('departments', 'subdepartment',
            'email', 'employeeid', 'status', 'location', 'hrmsrole'), 'courseid' => 1, 'enrolid' => 0,
        'plugins' => array('users', 'costcenter'), 'filterparams' => $filterparams));
    } else if (has_capability('local/costcenter:manage_owndepartments', $systemcontext)) {
        $mform = new filters_form(null, array('filterlist' => array('subdepartment', 'email', 'employeeid',
         'status', 'location', 'hrmsrole'), 'courseid' => 1, 'enrolid' => 0, 'plugins' => array('users',
         'costcenter'), 'filterparams' => $filterparams));
    } else {
        $mform = new filters_form(null, array('filterlist' => array('email', 'employeeid', 'status', 'location',
         'hrmsrole'), 'courseid' => 1, 'enrolid' => 0, 'plugins' => array('users', 'costcenter'), 'filterparams'
          => $filterparams));
    }
    return $mform;
}

/*
* Author Sarath
* return count  of sync errors
* @return  [type] int count of sync errors
*/
function manage_syncerrors_count($stable, $filterdata) {
    global $DB, $USER;

    $systemcontext =(new \local_users\lib\accesslib())::get_module_context();
    $params = array();
    $countsql = " SELECT count(id) ";
    $selectsql = "SELECT * ";
    $fromsql = " FROM {local_syncerrors} ls where 1=1";
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $fromsql .= " ";
    } else {
        $fromsql .= " AND modified_by = :modified_by ";
        $params['modified_by'] = $USER->id;
    }
    $count = $DB->count_records_sql($countsql.$fromsql, $params);
    $fromsql .= " ORDER BY id DESC ";

    $syncerrors = $DB->get_records_sql($selectsql.$fromsql, $params, $stable->start, $stable->length);

    return array('count' => $count, 'syncerrors' => $syncerrors);
}


/*
* Author Sarath
* return data of sync errors
* @return  [type] char data of sync errors
*/
function manage_syncerrors_content($stable, $filterdata) {
    global $DB;
    $data = array();
    $totalerrors = manage_syncerrors_count($stable, $filterdata);
    $syncerrors = $totalerrors['syncerrors'];
    foreach ($syncerrors as $syncerror) {
        $list = array();
        $list['idnumber'] = $syncerror->idnumber ? $syncerror->idnumber : '-';
        $list['email'] = $syncerror->email ? $syncerror->email : '-';
        $str = $syncerror->mandatory_fields;
        $exp = explode(',', $str);
        $exp = implode('<br><br>', $exp);
        $list['mandatoryfields'] = $exp;
        $err = $syncerror->error;
        $exp1 = explode(',', $err);
        $expe = implode('<br><br>', $exp1);
        $list['errors'] = $expe;
        $date = $syncerror->date_created;
        $list['modifiedby'] = fullname($DB->get_record('user', array('id' => $syncerror->modified_by)));;
        $list['createddate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i", $date);

        $data[] = $list;
    }
    return $data;
}

    /*
* Author Sarath
* return count  of sync statistics
* @return  [type] int count of sync statistics
*/
function manage_syncstatistics_count($stable, $filterdata) {
    global $DB, $USER;
    $systemcontext = (new \local_users\lib\accesslib())::get_module_context();
    $params = array();
    $countsql = " SELECT count(id) ";
    $selectsql = "SELECT * ";
    $fromsql = " FROM {local_userssyncdata} ls where 1=1";
    if (is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)) {
        $fromsql .= " ";
    } else {
        // $fromsql .= " AND usercreated = :modifiedby ";
        // $params['modifiedby'] = $USER->id;
        $fromsql .= " AND costcenterid = :orgid ";
        $params['orgid'] = $USER->open_costcenterid;
    }

    $count = $DB->count_records_sql($countsql.$fromsql, $params);
    $fromsql .= " ORDER BY id DESC";

    $syncstatstics = $DB->get_records_sql($selectsql.$fromsql, $params, $stable->start, $stable->length);
    return array('count' => $count, 'syncstatstics' => $syncstatstics);
}


/*
* Author Sarath
* return data of sync statistics
* @return  [type] char data of sync statistics
*/
function manage_syncstatistics_content($stable, $filterdata) {
    global $DB;
    $data = array();
    $totalerrorsstatstics = manage_syncstatistics_count($stable, $filterdata);
    $syncstatstics = $totalerrorsstatstics['syncstatstics'];
    foreach ($syncstatstics as $syncstatstic) {
        $list = array();
        $list['newuserscount'] = $syncstatstic->newuserscount;
        $list['updateduserscount'] = $syncstatstic->updateduserscount;
        $list['errorscount'] = $syncstatstic->errorscount;
        $list['warningscount'] = $syncstatstic->warningscount;
        $list['supervisorwarningscount'] = $syncstatstic->supervisorwarningscount;
        $usercreated = $DB->get_record('user', array('id' => $syncstatstic->usercreated));
        $list['usercreated'] = $usercreated->firstname. ' '. $usercreated->lastname;
        $list['createddate'] = \local_costcenter\lib::get_userdate("d/m/Y", $syncstatstic->timecreated);
        $list['modifieddate'] = \local_costcenter\lib::get_userdate("d/m/Y", $syncstatstic->timemodified);
        $list['checkbox'] = $syncstatstic->id;
        $data[] = $list;
    }
    return $data;
}

/*
* Author sarath
* @return true for reports under category
*/
function learnerscript_users_list() {
    return get_string('users', 'local_users');
}

function send_logins_user($user) {
    // removal of code if triggered by any chance. should never happen..
    // global $DB, $CFG;
    // $from_user = $DB->get_record('user', ['id'=>2]);
    // $subject = get_string('logininfo', 'local_users');
    // $user->siteurl = $CFG->wwwroot;
    // $body = $emailbody = get_string('logininfobody', 'local_users', $user);
    // email_to_user($user, $from_user, $subject, $body, $emailbody);
}
function local_users_before_http_headers() {
    global $PAGE, $CFG;
    require_once ($CFG->libdir.'/accesslib.php');
    if ( !is_siteadmin()) {
        $PAGE->add_body_class('usersclass');
    }
}
