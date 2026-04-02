<?php

namespace local_users\functions;
require_once($CFG->dirroot . '/local/costcenter/lib.php');

class userlibfunctions {
    /* find department list
    @param need to pass costcenter value*/
    public function find_departments_list($costcenter) {

        global $DB;
        if ($costcenter) {
            $sql = "select id,fullname from {local_costcenter} ";

            $costcenters = explode(',', $costcenter);
            list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($costcenters,
             SQL_PARAMS_NAMED, 'parentid');
            $sql .= " where visible =1 AND parentid $relatedparentidsql";

            $sub_dep = $DB->get_records_sql($sql, $relatedparentidparams);

            return $sub_dep;
        } else {
            return $costcenter;
        }

    }
    /* find sub department list
    @param need to pass department value*/
    public function find_subdepartments_list($department) {
        global $DB;
        $sql = "select id,fullname from {local_costcenter} ";

        $departments = explode(',', $department);
        list($relatedparentidsql, $relatedparentidparams) = $DB->get_in_or_equal($departments, SQL_PARAMS_NAMED, 'parentid');
        $sql .= " where parentid $relatedparentidsql";

        $sub_dep = $DB->get_records_sql($sql, $relatedparentidparams);

        return $sub_dep;
    }

    /* find supervisors list
    @param need to pass supervisor and userid optional value*/
    public function find_supervisor_list($user) {
        global $DB, $USER;
        if($user){
            if(!is_object($user)){
                $user = \core_user::get_user($user);
                $userpath = array_filter(explode('/',$user->open_path));
                $depth = $USER->useraccess['currentroleinfo']['depth'];
                if(is_siteadmin()){
                    $depth = 1;//getting first level id value
                }
                $pathlike = '/'.implode('/', array_slice($userpath, 0, $depth)).'%';
            }
            $sql = "SELECT u.id, concat(u.firstname,' ',u.lastname) as username from {user} as u where u.suspended
    	         = :suspended AND u.deleted = :deleted AND u.open_path LIKE '{$pathlike}'  AND u.id > 2 ";
            if ($user) {
                $sql .= " AND u.id != :userid";
            }
            $supervisors = $DB->get_records_sql($sql, array('suspended' => 0, 'deleted' => 0,
             'userid' => $user->id));
            return $supervisors;
        }
    }

    /* find department supervisors list
    @param need to pass supervisor and userid optional value*/
    public function find_dept_supervisor_list($supervisor, $userid=0) {
        if ($supervisor) {
            global $DB;
            $sql = "SELECT u.id,Concat(u.firstname,' ',u.lastname) as username from {user} as u where u.suspended!=1
             AND u.deleted!=1 AND CONCAT('/',u.open_path,'/') = CONCAT('%/',$supervisor,'/%') AND u.id!= 1 AND u.id!=2";
            if ($userid) {
                $sql .= " AND u.id != $userid AND u.id IN (SELECT open_supervisorid FROM {user} WHERE id = {$userid})";
            }
            $sub_dep = $DB->get_records_sql($sql);
            return $sub_dep;
        }

    }
    /* find positions list
    @param need to pass costcenter value*/
    public function find_positions_list($costcenter, $domain) {

        global $DB;
        $corecomponent = new \core_component();
        $positionpluginexists = $corecomponent::get_plugin_directory('local', 'positions');
        if ($positionpluginexists) {
            $sql = "select id,name from {local_positions} ";
            if ($costcenter) {
                $sql .= " where costcenter = $costcenter";
            }
            if ($domain) {
                $sql .= " AND domain = $domain";
            }
            $positions = array(get_string('select_position', 'local_users')) + $DB->get_records_sql_menu($sql);
            return $positions;
        }
    }
    /* find domains list
    @param need to pass costcenter value*/
    public static function find_domains_list($costcenter) {

        global $DB;
        $corecomponent = new \core_component();
        $pluginexists = $corecomponent::get_plugin_directory('local', 'domains');
        if ($pluginexists) {
            $sql = "select id,name from {local_domains} ";

            if ($costcenter) {
                $sql .= " where costcenter = $costcenter";
            }
            $domains = array(get_string('select_domain', 'local_users')) + $DB->get_records_sql_menu($sql);
            return $domains;
        }
    }

} //End of userlibfunctions.
