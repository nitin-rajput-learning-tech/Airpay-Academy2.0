<?php
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/local/lib.php');
class local_assignroles_external extends external_api {

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_assignrole_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'roleid' => new external_value(PARAM_INT, 'The role id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of role name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return assignrole form submits
     */
    public static function submit_assignrole_form($contextid, $roleid,$jsonformdata){
        global $PAGE,$CFG, $USER,$DB;

        require_once($CFG->dirroot . '/local/assignroles/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_assignrole_form_parameters(),
                                    ['contextid' => $contextid, 'roleid'=>$roleid,'jsonformdata' => $jsonformdata]);
        // $context = $params['contextid'];
        $context = (new \local_assignroles\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
         $mform = new local_assignroles\form\assignrole(null, array('roleid'=>$roleid), 'post', '', null, true, $data);
        $roles  = new local_assignroles\local\assignrole();
        $valdata = $mform->get_data();
       
        if($valdata){
            $categorysql = $DB->get_record('local_costcenter', array('id' => $data['open_costcenterid']), $fields = 'category', $strictness = IGNORE_MISSING);
            $categoryid = $categorysql->category;
            $categorycontext = context_coursecat::instance($categoryid);
            $roles->rolesassign($valdata->users,$valdata->roleid, $categorycontext->id);
            
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_assignrole_form_returns() {
        return new external_value(PARAM_INT, 'role id');
    }
    /**
     * Describes the parameters for local_unassign_role webservice.
     * @return external_function_parameters
     */
    public static function local_unassign_role_parameters(){
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for role unassigning'),
                'roleid' => new external_value(PARAM_INT, 'The role id for role unassigning'),
                // 'open_costcenterid' => new external_value(PARAM_INT, 'The costcenter id for role unassigning'),
                'userid' => new external_value(PARAM_RAW, 'The user id for unassigning role'),

            )
        );
    }
    /**
     * local_unassign_role for unassigniung user from role
     *
     * @param [int] $contextid
     * @param [int] $roleid
     * @param [int] $userid
     * @return param bool for status
     */
    public static function local_unassign_role($contextid, $roleid, $userid){
        global $CFG;
        $params = self::validate_parameters(self::local_unassign_role_parameters(),
                                    ['contextid' => $contextid, 'roleid'=>$roleid,'userid' => $userid]);
        require_once($CFG->dirroot . '/lib/accesslib.php');
        $categorycontext = (new \local_assignroles\lib\accesslib())::get_module_context();
        try{
            role_unassign($roleid, $userid, $contextid, '');
            return true;
        }catch(Exception $e){
            throw new moodle_exception('Error in unassigning role '. $e);
            return false;
        }


    }
    /**
     * Describes the return for local_unassign_role webservice.
     * @return external_function_return
     */
    public static function local_unassign_role_returns(){
        return new external_value(PARAM_BOOL, 'status');
    }
    /**
     * Describes the parameters for assignrole_form_option_selector webservice.
     * @return external_function_parameters
     */
    public static function assignrole_form_option_selector_parameters(){
        $query = new external_value(PARAM_RAW, 'Query string');
        $action = new external_value(PARAM_RAW, 'Action for the classroom form selector');
        $options = new external_value(PARAM_RAW, 'Action for the classroom form selector');
        $searchanywhere = new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning');
        $page = new external_value(PARAM_INT, 'Page number');
        $perpage = new external_value(PARAM_INT, 'Number per page');
        return new external_function_parameters(array(
            'query' => $query,
            'action' => $action,
            'options' => $options,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage,

        ));
    }
    /**
     * assignrole_form_option_selector for autocomplete fields
     *
     * @param [char] $query
     * @param [char] $action
     * @param [text] $options(json)
     * @param [bool] $searchanywhere
     * @param [int] $page
     * @param [int] $perpage
     * @return [Json] data for auto complete
     */
    public static function assignrole_form_option_selector($query, $action, $options, $searchanywhere, $page, $perpage){
        $params = self::validate_parameters(self::assignrole_form_option_selector_parameters(), array(
            'query' => $query,
            'action' => $action,
            'options' => $options,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage
        ));
        global $DB,$USER;
        $query = $params['query'];
        $action = $params['action'];
        $options = $params['options'];
        $searchanywhere=$params['searchanywhere'];
        $page=$params['page'];
        $perpage=$params['perpage'];
        if (!empty($options)) {
            $formoptions = json_decode($options);
        }
        if ($action) {
            switch($action){
                case 'role_ids':
                
                     $sql = "SELECT r.id, r.name as fullname
                            FROM {role} r
                            JOIN {role_context_levels} rcl ON (rcl.contextlevel =:contextlevel AND r.id = rcl.roleid)
                         WHERE r.name !='' AND (r.archetype !='manager' ";


                    $params = array('contextlevel' => CONTEXT_COURSECAT);
                    if(!empty($query)){ 
                        if ($searchanywhere) {
                            $sql .=" AND r.name LIKE :query ";
                            $params['query'] = "%$query%";
                        } else {
                            $sql .=" AND r.name LIKE :query ";
                            $params['query'] = "$query%";
                        }
                    }

                    if($formoptions->costcenterid){

                        $hierarchysql = "SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.id=:costcenterid ";

                        $hierarchypath = $DB->get_field_sql($hierarchysql,array('costcenterid'=>$formoptions->costcenterid));

                        $hierarchydepth = count(array_filter(explode('/',$hierarchypath)));

                        // if($hierarchydepth == 5){

                        //     $sql .=" OR r.shortname LIKE 'tbm'";
                        // }
                        // if($hierarchydepth == 4){
                        //     $sql .=" OR r.shortname LIKE 'cah'";
                        // }
                        // if($hierarchydepth == 3){
                        //    $sql .=" OR r.shortname LIKE 'cuh'";
                        // }
                        // if($hierarchydepth == 2){
                        //     $sql .=" OR r.shortname LIKE 'ch'";
                        // }
                        // if($hierarchydepth == 1){
                            $sql .=" OR r.shortname LIKE 'administrator'";
                        // }
                    }

                    $sql .=" )";

                    $sql .=" ORDER BY r.sortorder ASC";

                    $return = $DB->get_records_sql($sql, $params);


                    break;

                case 'role_costcenterusers':

                    if($formoptions->roleid){


                        $sql = "SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.id=:costcenterid ";

                        $costcenterpath = $DB->get_field_sql($sql,array('costcenterid'=>$formoptions->costcenterid));
                        $context = (new \local_assignroles\lib\accesslib())::get_module_context($costcenterpath);


                        $condition = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');

                        if(is_siteadmin()){
                          $userssql =  "SELECT u.id, concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname
                            FROM {user} AS u
                            WHERE u.id > 2 AND u.deleted = 0 AND u.suspended = 0 AND u.id <> :loginuser   AND u.id NOT IN (SELECT userid FROM {role_assignments} WHERE contextid=:context AND roleid=:roleid) ";
                        }else{

                            $userssql =  "SELECT u.id, concat(u.firstname,' ',u.lastname,' ','(',u.idnumber,')') as fullname
                            FROM {user} AS u
                            WHERE u.id > 2 AND u.deleted = 0 AND u.suspended = 0 AND u.id NOT IN (SELECT userid FROM {role_assignments} WHERE contextid=:context AND roleid=:roleid)  $condition ";

                        }

                        if($formoptions->hierarchyid){

                            $hierarchysql = "SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.id=:hierarchyid ";

                            $hierarchypath = $DB->get_field_sql($hierarchysql,array('hierarchyid'=>$formoptions->hierarchyid));

                            $userpath = array_filter(explode('/',$hierarchypath));
                        }else{

                            $userpath = array_filter(explode('/',$costcenterpath));
                        }


                        $depth = $USER->useraccess['currentroleinfo']['depth'];
                        if(count((array)$USER->useraccess['currentroleinfo']['contextinfo']) > 1){
                            $depth--;
                        }
                        if(is_siteadmin()){
                            $depth = 1;//getting first level id value
                        }
                        $pathlike = '/'.implode('/', array_slice($userpath, 0, $depth)).'%';

                        $userssql .=" AND u.open_path LIKE '{$pathlike}'";


                        $params = array('loginuser' =>$USER->id, 'context' => $context->id, 'roleid' => $formoptions->roleid);
                        if(!empty($query)){
                            if ($searchanywhere) {
                                $userssql .=" AND CONCAT(u.firstname,' ',u.lastname) LIKE :query ";
                                $params['query'] = "%$query%";
                            } else {
                                $userssql .=" AND CONCAT(u.firstname,' ',u.lastname) LIKE :query ";
                                $params['query'] = "$query%";
                            }
                        }
                        $return = $DB->get_records_sql($userssql, $params, $page, $perpage);
                    }


                    break;

                case 'role_users':
                
                    $context = (new \local_assignroles\lib\accesslib())::get_module_context();

                    if(is_siteadmin()){
                      $userssql =  "SELECT u.id, concat(u.firstname,' ',u.lastname) as fullname 
                        FROM {user} AS u 
                        WHERE u.id > 2 AND u.deleted = 0 AND u.suspended = 0 AND u.id <> :loginuser AND CONCAT(u.open_path,'/') LIKE :organisationpathlike  AND u.id NOT IN (SELECT userid FROM {role_assignments} WHERE roleid=:roleid)";
                    }else{

                        $userssql =  "SELECT u.id, concat(u.firstname,' ',u.lastname) as fullname 
                        FROM {user} AS u 
                        WHERE u.id > 2 AND u.deleted = 0 AND u.suspended = 0  AND CONCAT(u.open_path,'/') LIKE :organisationpathlike AND u.id NOT IN (SELECT userid FROM {role_assignments} WHERE contextid=:context AND roleid=:roleid)";

                    }
                    
                    $params = array('loginuser' =>$USER->id, 'context' => $context->id, 'roleid' => $formoptions->roleid, 'organisationpathlike' => '%/'.$formoptions->organisationid.'/%');
                    if(!empty($query)){ 
                        if ($searchanywhere) {
                            $userssql .=" AND CONCAT(u.firstname,' ',u.lastname) LIKE :query ";
                            $params['query'] = "%$query%";
                        } else {
                            $userssql .=" AND CONCAT(u.firstname,' ',u.lastname) LIKE :query ";
                            $params['query'] = "$query%";
                        }
                    }
                    if(!(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $context))){
                        $userssql .= " AND u.open_costcenterid=:logincostcenter";
                        $params['logincostcenter'] = $USER->open_costcenterid;
                    }
                    $return = $DB->get_records_sql($userssql, $params, $page, $perpage);

                    break;

                case 'costcenter_organisation_selector':

                    $fields = array("fullname"/*, "shortname"*/);
                    $sqlparams['parentid'] = 0;
                    $likesql = array();
                    $i = 0;
                    $concatsql ="";
                    foreach ($fields as $field) {
                        $i++;
                        $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                        $sqlparams["queryparam$i"] = "%$query%";
                    }
                    $sqlfields = implode(" OR ", $likesql);
                    $concatsql .= " AND ($sqlfields) ";
                    $fields      = 'SELECT id, fullname';
                    $accountssql = " FROM {local_costcenter}
                                     WHERE 1=1 $concatsql AND parentid = :parentid ";
                    if ($formoptions->id == 0) {
                        $accountssql .= ' AND visible = 1';
                    }
                    if (!(is_siteadmin())) {
                        $costcenterid = $DB->get_field('user', 'open_costcenterid', array("id" => $USER->id), $strictness = IGNORE_MISSING);
                        $accountssql .= " AND id = $costcenterid";
                    }                
                    $accounts = $DB->get_records_sql($fields . $accountssql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                    
                    if ($accounts) {
                        $totalaccounts = count($accounts);
                        $moreaccounts = $totalaccounts > $perpage;

                        if ($moreaccounts) {
                            // We need to discard the last record.
                            array_pop($accounts);
                        }
                    }
                    $return = $accounts;

                    break;
            }
            return json_encode($return);
        }

    }
    /**
     * Describes the return value of assignrole_form_option_selector webservice.
     * @return external_function_returns
     */
    public static function assignrole_form_option_selector_returns(){
        return new external_value(PARAM_RAW, 'data');
    }

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_assigncostcenterrole_form_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'costcenterid' => new external_value(PARAM_INT, 'The costcenter id for the evaluation'),
                'formtype' => new external_value(PARAM_RAW, 'The formtype for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }
    /**
     * form submission of role name and returns instance of this object
     *
     * @param int $contextid
     * @param [string] $jsonformdata
     * @return assignrole form submits
     */
    public static function submit_assigncostcenterrole_form($contextid, $costcenterid, $formtype,$jsonformdata){
        global $PAGE,$CFG, $USER,$DB;

        require_once($CFG->dirroot . '/local/assignroles/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_assigncostcenterrole_form_parameters(),
                                    ['contextid' => $contextid, 'costcenterid'=>$costcenterid, 'formtype'=>$formtype,'jsonformdata' => $jsonformdata]);
        // $context = $params['contextid'];
        $context = (new \local_assignroles\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        // throw new moodle_exception('Error in creation');
        // die;
        $data = array();

        parse_str($serialiseddata, $data);
        $warnings = array();
         $mform = new local_assignroles\form\assigncostcenterrole(null, array('costcenterid'=>$costcenterid,'formtype' => $formtype), 'post', '', null, true, $data);
        $roles  = new local_assignroles\local\assignrole();
        $valdata = $mform->get_data();

        if($valdata){
            $categorysql = $DB->get_record('local_costcenter', array('id' => $costcenterid), $fields = 'category', $strictness = IGNORE_MISSING);
            $categoryid = $categorysql->category;
            $categorycontext = context_coursecat::instance($categoryid);
            $roles->rolesassign($valdata->users,$valdata->roleid, $categorycontext->id);
            
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in creation');
        }
    }
    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_assigncostcenterrole_form_returns() {
        return new external_value(PARAM_INT, 'role id');
    }
}
