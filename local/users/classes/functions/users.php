<?php

namespace local_users\functions;
require_once($CFG->dirroot.'/user/lib.php');
use html_writer;
use moodle_url;
use context_system;
use tabobject;
use user_create_user;
use context_user;
use core_user;

class users {

    private static $_users;
    private $dbHandle;


    public static function getInstance() {
        if (!self::$_users) {
            self::$_users = new users();
        }
        return self::$_users;
    }

    /**
     * @method insert_newuser
     * @todo To create new user with system role
     * @param object $data Submitted form data
     */
    public function insert_newuser($data) {
        global $DB, $USER, $CFG;
        $userdata = (object)$data;
        foreach ($data as $key => $value) {
            $userdata->$key = trim($value);
        }
        if (isset($userdata->open_departmentid)) {
            $userdata->institution = $DB->get_field('local_costcenter', 'fullname', array('id' =>
             $userdata->open_departmentid));
        }
        if (isset($userdata->open_hrmsrole)) {
            $userdata->department = $userdata->open_hrmsrole;
        }
        if (isset($userdata->open_employeeid)) {
            $userdata->idnumber = $userdata->open_employeeid;
        }
        $userdata->confirmed = 1;
        $userdata->deleted = 0;
        $userdata->mnethostid = 1;
        if (strtolower($userdata->email) != $userdata->email) {
            $userdata->email = strtolower($userdata->email);
        }
        if (isset($userdata->city)) {
            $userdata->open_location = $userdata->city;
        }
        if ($userdata->open_supervisorid) {
            $userdata->open_supervisorempid = $DB->get_field('user', 'open_employeeid', array('id' =>
             $userdata->open_supervisorid));
        }
        $userdata->password = hash_internal_user_password($userdata->password);
        $createpassword = $userdata->createpassword;
        $data = user_create_user($userdata, false);
        if ($createpassword) {
            $userdata->id = $data;
            setnew_password_and_mail($userdata);
            unset_user_preference('create_password', $userdata);
            set_user_preference('auth_forcepasswordchange', 1, $userdata);

        } else if ($form_status == 0) {
            $userdata->id = $data;
            set_user_preference('auth_forcepasswordchange', $userdata->preference_auth_forcepasswordchange, $userdata);

        }
        return $data;
    } //End of insert_newuser function.

    /**
     * [update_existinguser description]
     * @param  [object] $data
     * @return [int] success or failure.
     */
    public function update_existinguser($data) {
        global $DB, $USER, $CFG;
        $userdata = (object) $data;
        $createpassword = $userdata->createpassword;
        if (empty($userdata->password)) {
            unset($userdata->password);
        } else {
            $userdata->password = hash_internal_user_password($userdata->password);
        }
        foreach ($userdata as $key => $value) {
            $userdata->$key = trim($value);
        }
        if (isset($userdata->open_departmentid)) {
            $userdata->institution = $DB->get_field('local_costcenter', 'fullname', array('id' =>
                 $userdata->open_departmentid));
        }
        if (isset($userdata->open_hrmsrole)) {
            $userdata->department = $userdata->open_hrmsrole;
        }
        if (isset($userdata->open_employeeid)) {
            $userdata->idnumber = $userdata->open_employeeid;
        }
        $usercontext = context_user::instance($userdata->id);
        if (strtolower($userdata->email) != $userdata->email) {
            $userdata->email = strtolower($userdata->email);
        }
        if (isset($userdata->city)) {
            $userdata->open_location = $userdata->city;
        }
        if (isset($userdata->open_costcenterid)) {
            $existingcostcenter = $DB->get_field('user', 'open_costcenterid', array('id' => $userdata->id));
            if ($userdata->open_costcenterid != $existingcostcenter) {
                \core\session\manager::kill_user_sessions($userdata->id);
            }
        }
        if ($userdata) {
            if ($userdata->open_supervisorid) {
                $userdata->open_supervisorempid = $DB->get_field('user', 'open_employeeid', array('id' =>
                $userdata->open_supervisorid));
            }
            if ($userdata->imagefile) {
                $editoroptions = array(
                    'maxfiles'   => EDITOR_UNLIMITED_FILES,
                    'maxbytes'   => $CFG->maxbytes,
                    'trusttext'  => false,
                    'forcehttps' => false,
                    'context'    => $usercontext
                );
                $userdata = file_postupdate_standard_editor($userdata, 'description', $editoroptions, $usercontext,
                 'user', 'profile', 0);
            }
            $userdata->deleted = 0;
            $userdata->descriptionformat = 1;

            $result = user_update_user($userdata, false);
            $filemanagercontext = $usercontext;
            $filemanageroptions = array('maxbytes'       => $CFG->maxbytes,
                                        'subdirs'        => 0,
                                        'maxfiles'       => 1,
                                        'accepted_types' => 'web_image');
            core_user::update_picture($userdata, $filemanageroptions);
        }
        // added for updating session variable $USER if updated the current user.
        if ($userdata->id) {
            $user = $DB->get_record('user', array('id' => $userdata->id), '*', MUST_EXIST);
            if ($USER->id == $user->id) {
                // Override old $USER session variable if needed.
                foreach ((array)$user as $variable => $value) {
                    if ($variable === 'description' || $variable === 'password') {
                        // These are not set for security nad perf reasons.
                        continue;
                    }
                    $USER->$variable = $value;
                }
                // Preload custom fields.
                profile_load_custom_fields($USER);
            }
        }
        $userinfo = \core_user::get_user($userdata->id);
        if ($createpassword) {
            setnew_password_and_mail($userinfo);
            unset_user_preference('create_password', $userinfo);
            set_user_preference('auth_forcepasswordchange', 1, $userinfo);
        }
        // added for updating session variable $USER if updated the current user ends here.
        return $userdata->id;
    } //End of update_existinguser function.


    /* To get rolename for logged in user */

    public function get_rolename($userid) {
        global $DB;
        return $DB->get_field_sql("SELECT r.shortname FROM {role_assignments} ra, {role} r WHERE ra.userid = :userid AND
         r.id = ra.roleid ", array('userid' => $userid), 0, 1);
    }

    /* Action icons */

    public function get_different_actions($plugin, $page, $id, $visible) {
        global $DB, $USER, $OUTPUT;
        $context = (new \local_users\lib\accesslib())::get_module_context();
        $role = $this->get_rolename($id);
        if ($id == $USER->id) {
            return html_writer::link('javascript:void(0)', '<i class="fa fa-pencil fa-fw" title=""></i>',
             array('data-action' => 'createusermodal', 'class' => 'createusermodal', 'data-value' => $id,
              'class' => '', 'onclick' => '(function(e){ require("local_users/newuser").init({selector:"createusermodal",
               context:'.$context->id.', id:'.$id.', form_status:0}) })(event)', 'style' => 'cursor:pointer' , 'title' =>
                'edit'));
        } else if (is_siteadmin($id)) {
            return '';
        } else {
            $userobject = $DB->get_record('user' , array('id' => $id));
            $fullname = fullname($userobject);
            $buttons = array();
            if ($visible) {
                $buttons[] = '<button class="btn btn_active_user">'.get_string('active', 'local_users').'</button>';
            } else {
                $buttons[] = '<button class="btn btn_inactive_user">'.get_string('inactive', 'local_users').'.</button>';
            }
            if (is_siteadmin() || has_capability('local/users:delete', $context)) {
                $buttons[] = html_writer::link('javascript:void(0)', '<i class="fa fa-trash fa-fw" aria-hidden="true"
                 title="" aria-label="Delete"></i>', array('title' => get_string('delete'), 'onclick' => '(function(e){
                  require("local_users/newuser").deleteConfirm({ action: "delete_user" ,id:'.$id.',context:
                  '.$context->id.', fullname:"'.$fullname.'"}) })(event)'));
            }
            if (is_siteadmin() || has_capability('local/users:edit', $context)) {
                $buttons[] = html_writer::link('javascript:void(0)', '<i class="fa fa-pencil fa-fw" title=""></i>',
                 array('data-action' => 'createusermodal', 'class' => 'createusermodal', 'data-value' => $id, 'class' => '',
                  'onclick' => '(function(e){ require("local_users/newuser").init({selector:"createusermodal",
                   context:'.$context->id.', id:'.$id.', form_status:0}) })(event)', 'style' => 'cursor:pointer' ,
                    'title' => get_string('edit')));
            }
            // sending parameters for visible as  1 and not visible as 0 by defalut for  OL11
            if (is_siteadmin() || has_capability('local/users:edit', $context)) {
                if ($visible) {
                    $buttons[] = html_writer::link('javascript:void(0)', '<i class="fa fa-eye fa-fw " aria-hidden="true"
                     aria-label="Hide"></i>', array('title' => get_string('disable', 'local_users'), 'onclick' =>
                      '(function(e){ require("local_users/newuser").userSuspend({ id:'.$id.',context:'.$context->id.',
                       fullname:"'.$fullname.'"}) })(event)'));
                } else {
                    $buttons[] = html_writer::link('javascript:void(0)', '<i class="fa fa-eye-slash fa-fw "
                     aria-hidden="true" title="" aria-label="Show"></i>', array('title' => get_string('enable',
                        'local_users'), 'onclick' => '(function(e){ require("local_users/newuser").
                     userSuspend({ id:'.$id.', context:'.$context->id.', fullname:"'.$fullname.'"}) })(event)'));
                }
            }
            // OL11 ends here .
            return implode('', $buttons);
        }
    }   //End of get_different_actions function.

    /**
     * @method get_costcenternames
     * @todo to get costcenter name based on role(admin, registrar)
     * @param object $user user detail
     * @param type $user
     * @return string, costcenter fullname else valid statement based on condition
     */
    public function get_costcenternames($user) {
        global $DB;
        $role = $this->get_rolename($user->id);
        
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        if (is_siteadmin($user->id) || has_capability('local/costcenter:manage_multiorganizations', $categorycontext)) {
            return get_string('all');
        }
        $table = 'local_costcenter_permissions';
        $field = 'userid';
        if ( $role != 'manager') {
            $table = 'user';
            $field = 'id';
        }
        $costcenters = $DB->get_records_sql("SELECT * FROM {{$table}} WHERE {$field} = {$user->id}");
        $scl = array();
        if ($costcenters) {
            foreach ($costcenters as $costcenter) {
                $scl[] = $DB->get_field('local_costcenter', 'fullname', array('id' => $costcenter->open_costcenterid));
            }
            return implode(', ', $scl);
        }
        return get_string('not_assigned', 'local_users');
    }


    /**
     * @method get_usercount
     * @todo To get total number of cobaltusers
     * @param string $extraselect used to add extra condition to get userlist
     * @param array $extraparams it holds values
     * @return int user count
     */
    public function get_usercount($extraselect = '', array $extraparams = null) {
        return 10;
    }   //End of get_usercount function.

    /**
     * @method get_users_listing
     * @todo to get user list of costcenter based on condition
     * @param string $sort fieldname
     * @param string $dir specify the order to sort
     * @param int $page page number
     * @param int $recordsperpage records perpage
     * @param string $extraselect extra condition to select user
     * @param array $extraparams
     * @return array of objects , list of users
     */
    public function get_users_listing($sort = 'lastaccess', $dir = 'ASC', $page = 0, $recordsperpage = 0,
     $extraselect = '', array $extraparams = null, $extracontext = null) {
        global $DB, $CFG, $USER;
        $extraselect;

        $select = "u.deleted <> 1 AND u.id <> :guestid";
        $params = array('guestid' => $CFG->siteguest);

        if ($extraselect) {
            $select .= " AND $extraselect";
            $params = $params + (array) $extraparams;
        }

        // If a context is specified, get extra user fields that the current user
        // is supposed to see.
        $extrafields = '';
        if ($extracontext) {
            $extrafields = get_extra_user_fields_sql($extracontext, '', '', array('id', 'username',
             'email', 'firstname', 'lastname', 'city', 'country',
                'lastaccess', 'confirmed', 'mnethostid'));
        }
        /*
         * ###Bugreport#183-Filters
         * (Resolved) Added $select parameters for conditions
         */
        // warning: will return UNCONFIRMED USERS
        return $DB->get_records_sql("SELECT u.*
                   FROM {user} as u $join WHERE $select GROUP BY id ORDER BY $sort $dir LIMIT $page, $recordsperpage", $params);
    }

}//End of users class.
