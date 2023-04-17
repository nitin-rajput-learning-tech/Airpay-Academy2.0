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
 * local local_costcenter
 *
 * @package    local_costcenter
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
class local_costcenter_external extends external_api
{

    /**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_costcenterform_form_parameters()
    {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the evaluation'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),

            )
        );
    }

    /**
     * form submission of costcenter name and returns instance of this object
     *
     * @param int $contextid 
     * @param [string] $jsonformdata 
     * @return costcenter form submits
     */
    public static function submit_costcenterform_form($contextid, $jsonformdata)
    {
        global $PAGE, $CFG;
        require_once($CFG->dirroot . '/local/costcenter/lib.php');
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::submit_costcenterform_form_parameters(),
            ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]
        );
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        self::validate_context($context);

        $data = array();

        if (!empty($params['jsonformdata'])) {

            $serialiseddata = json_decode($params['jsonformdata']);
            if(is_object($serialiseddata)){
                $serialiseddata = serialize($serialiseddata);
            }
            parse_str($serialiseddata, $data);
        }

        $warnings = array();
        // $mform = new local_costcenter\form\costcenterform(null, array(), 'post', '', null, true, $data);
        $mform = new local_costcenter\form\organization_form(null, array('formtype' => $data['formtype']), 'post', '', null, true, $data);

        $valdata = $mform->get_data();

        if ($valdata) {
            if ($valdata->id > 0) {
                $costcenterupdate = costcenter_edit_instance($valdata->id, $valdata);
            } else {
                $costcenterinsert = costcenter_insert_instance($valdata);
            }
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
    public static function submit_costcenterform_form_returns()
    {
        return new external_value(PARAM_INT, 'costcenter id');
    }
    /**
     * [costcenter_status_confirm_parameters description]
     * @return [external function param] [parameters for the costcenter status update]
     */
    public static function costcenter_status_confirm_parameters()
    {
        return new external_function_parameters(
            array(
                'action' => new external_value(PARAM_ACTION, 'Action of the event', false),
                'id' => new external_value(PARAM_INT, 'ID of the record', 0),
                'confirm' => new external_value(PARAM_INT, 'confirm', true),
                'actionstatus' => new external_value(PARAM_RAW, 'actionstatus', false),
                'actionstatusmsg' => new external_value(PARAM_RAW, 'actionstatusmsg', false),
            )
        );
    }
    /**
     * [costcenter_status_confirm description]
     * @param  [type] $action  [description]
     * @param  [int] $id      [id of the costcenter]
     * @param  [int] $confirm [confirmation key]
     * @return [boolean]          [true if success]
     */
    public static function costcenter_status_confirm($action, $id, $confirm)
    {
        global $DB;
        if ($id) {

            $costcenter = $DB->get_record('local_costcenter', array('id' => $id), 'id, visible');
            $costcenter->visible = ($costcenter->visible == 1) ? 0 : 1;
            $costcenter->timemodified = time();
            $return = $DB->update_record('local_costcenter', $costcenter);
            if ($return) {
                if ($costcenter->visible == 0) {
                    $sql = " SELECT * FROM {user} where concat('/',open_path,'/') LIKE :costcenterid AND suspended = 0 ";
                    $uparams['costcenterid'] = '%' . $id . '%';
                    $costcenterusers = $DB->get_records_sql($sql, $uparams);
                } else {
                    $sql = " SELECT * FROM {user} where concat('/',open_path,'/') LIKE :costcenterid AND open_orgactive = 1 ";
                    $uparams['costcenterid'] = '%' . $id . '%';
                    $costcenterusers = $DB->get_records_sql($sql, $uparams);
                }
                foreach ($costcenterusers as $user) {
                    if ($costcenter->visible == 0) {
                        $user->suspended = 1;
                        $user->open_orgactive = 1;
                    } else {
                        $user->suspended = 0;
                        $user->open_orgactive = 0;
                    }
                    $update = $DB->update_record('user', $user);
                }
            }
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }
    /**
     * [costcenter_status_confirm_returns description]
     * @return [external value] [boolean]
     */
    public static function costcenter_status_confirm_returns()
    {
        return new external_value(PARAM_BOOL, 'return');
    }
    /**
     * [costcenter_delete_costcenter_parameters description]
     * @return [external value] [params for deleting costcenter]
     */
    public static function costcenter_delete_costcenter_parameters()
    {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'userid', 0)
            )
        );
    }
    /**
     * [costcenter_delete_costcenter description]
     * @param  [int] $id id of costcenter to be deleted 
     * @return [boolean]     [true for success]
     */
    public static function costcenter_delete_costcenter($id)
    {
        global $DB;
        if ($id) {
            $costcentercategory = $DB->get_field_sql("SELECT lc.category 
                FROM {local_costcenter} AS lc 
                JOIN {course_categories} AS cc ON cc.id = lc.category 
                WHERE lc.id = {$id} ");
            if ($costcentercategory) {
                // require_once($CFG->libdir.'/coursecatlib.php');
                // $category = coursecat::get($costcentercategory);
                // $category->delete_full(false);
                $dataobject = new stdClass();
                $dataobject->id = $costcentercategory;
                $dataobject->idnumber = uniqid();
                $DB->update_record('course_categories', $dataobject);
            }
            $costcenterdelete = $DB->delete_records('local_costcenter', array('id' => $id));
            $costcenterdelete .= $DB->delete_records('local_costcenter_permissions', array('costcenterid' => $id));
            $costcenterdelete = $DB->delete_records('course_categories', array('id' => $costcentercategory));
            return true;
        } else {
            throw new moodle_exception('Error in deleting');
            return false;
        }
        // if($id){
        // 	$costcenterdelete = $DB->delete_records('local_costcenter', array('id' => $id));
        //       	$costcenterdelete .= $DB->delete_records('local_costcenter_permissions', array('costcenterid' => $id));
        // 	return true;
        // }else {
        // 	throw new moodle_exception('Error in deleting');
        // 	return false;
        // }
    }
    /**
     * [costcenter_delete_costcenter_returns description]
     * @return [external value] [boolean]
     */
    public static function costcenter_delete_costcenter_returns()
    {
        return new external_value(PARAM_BOOL, 'return');
    }

    /**
     * Describes the parameters for departmentlist webservice.
     * @return external_function_parameters
     */
    public static function departmentlist_parameters()
    {
        return new external_function_parameters(
            array(
                'orgid' => new external_value(PARAM_INT, 'The id for the costcenter / organization')
            )
        );
    }

    /**
     * departments list
     *
     * @param int $orgid id for the organization
     * @return array 
     */
    public static function departmentlist($orgid)
    {
        global $DB, $CFG, $USER;
        $orglib = new local_costcenter\functions\userlibfunctions();
        $departmentlist = $orglib->find_departments_list($orgid);
        $return = array(
            'departments' => json_encode($departmentlist)
        );
        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function departmentlist_returns()
    {
        return new external_function_parameters(
            array(
                'departments' => new external_value(PARAM_RAW, 'Departmentlist ')
            )
        );
    }


    /**
     * 
     * @return external_function_parameters
     */
    public static function departmentview_parameters()
    {
        return new external_function_parameters(
            array(
                'jsonformdata' => new external_value(PARAM_RAW, 'The data of licence settings form, encoded as a json array')
            )
        );
    }

    /**
     *
     *
     * @param int $orgid id for the organization
     * @return array 
     */
    public static function departmentview($jsonformdata)
    {
        global $PAGE;

        $params = self::validate_parameters(
            self::departmentview_parameters(),
            ['jsonformdata' => $jsonformdata]
        );

        $data = array();

        if (!empty($params['jsonformdata'])) {

            $serialiseddata = json_decode($params['jsonformdata']);
            if(is_object($serialiseddata)){
                $serialiseddata = serialize($serialiseddata);
            }
            parse_str($serialiseddata, $data);
        }

        $categorycontext = (new \local_costcenter\lib\accesslib())::get_module_context();

        $PAGE->set_context($categorycontext);
        $mform = new \local_costcenter\functions\costcenter(null, array(), 'post', '', null, true, $data);
        $validateddata = $mform->get_data();
        $formdata = data_submitted();
        if ($validateddata) {
            set_config('serialkey', $validateddata->fullname, 'local_costcenter');
            $licencekeyhash = md5($validateddata->fullname);
            set_config('lms_serialkey', $licencekeyhash, 'local_costcenter');

            $return = array(
                'status' => 'success',
                'fullname' => $validateddata->fullname
            );
            return $return;
        } else {
            throw new moodle_exception('Error in creation');
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function departmentview_returns()
    {
        return new external_function_parameters(
            array(
                'status' => new external_value(PARAM_RAW, 'success/fail'),
                'fullname' => new external_value(PARAM_RAW, ' fullname ')
            )
        );
    }
    public static function subdepartmentlist_parameters()
    {
        return new external_function_parameters(
            array(
                'parentid' => new external_value(PARAM_INT, 'The id for the parent'),
                'parenttype' => new external_value(PARAM_TEXT, 'The type of the parent')
            )
        );
    }
    public static function subdepartmentlist($parentid, $parenttype)
    {
        global $DB, $CFG, $USER;
        $params = self::validate_parameters(
            self::subdepartmentlist_parameters(),
            ['parentid' => $parentid, 'parenttype' => $parenttype]
        );
        if (is_array($parentid)) {
            $parentid = implode(',', $parentid);
        }
        if ($parenttype == 'organization') {
            $subdeptsql = "SELECT lc.id, lc.fullname FROM {local_costcenter} AS lc
                JOIN {local_costcenter} AS llc ON llc.id=lc.parentid
                WHERE llc.id IN (:id) ";
        } else if ($parenttype == 'department') {
            $subdeptsql = "SELECT lc.id, lc.fullname FROM {local_costcenter} AS lc
                WHERE lc.parentid IN (:id) ";
        }
        $params = array('id' => $parentid);
        $subdepartmentlist = $DB->get_records_sql_menu($subdeptsql, $params);
        $return = array(
            'subdepartments' => json_encode($subdepartmentlist)
        );
        return $return;
    }
    public static function subdepartmentlist_returns()
    {
        return new external_function_parameters(
            array(
                'subdepartments' => new external_value(PARAM_RAW, 'Departmentlist')
            )
        );
    }
    public static function form_option_selector_parameters()
    {
        $query = new external_value(PARAM_RAW, 'Query string');
        $action = new external_value(PARAM_RAW, 'Action for the costcenter form selector');
        $options = new external_value(PARAM_RAW, 'Action for the kpichallenge form selector');
        $searchanywhere = new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning');
        $page = new external_value(PARAM_INT, 'Page number');
        $perpage = new external_value(PARAM_INT, 'Number per page');
        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'action' => $action,
            'options' => $options,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage,
        ));
    }
    public static function form_option_selector($query, $context, $action, $options, $searchanywhere, $page, $perpage)
    {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::form_option_selector_parameters(), array(
            'query' => $query,
            'context' => $context,
            'action' => $action,
            'options' => $options,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage
        ));
        $query = $params['query'];
        $action = $params['action'];
        $context = self::get_context_from_params($params['context']);
        $options = $params['options'];

        $searchanywhere = $params['searchanywhere'];
        $page = $params['page'];
        $perpage = $params['perpage'];

        if (!empty($options)) {
            $formoptions = json_decode($options);
        }
        self::validate_context($context);
        $allobject = new \stdClass();
        $allobject->id = 0;
        $allobject->fullname = 'All';
        $allobjectarr = array(0 => $allobject);
        if ($action) {

            $return = array();
            switch ($action) {
                case 'costcenter_organisation_selector':
                    $fields = array("fullname"/*, "shortname"*/);
                    $sqlparams['parentid'] = 0;
                    $likesql = array();
                    $i = 0;
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

                    $accounts = $DB->get_records_sql($fields . $accountssql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                    if ($accounts) {
                        $totalaccounts = count($accounts);
                        $moreaccounts = $totalaccounts > $perpage;

                        if ($moreaccounts) {
                            // We need to discard the last record.
                            array_pop($accounts);
                        }
                    }
                    $return = array_values(json_decode(json_encode(($accounts)), true));
                    break;
                case 'costcenter_department_selector':
                    if ((is_array($formoptions->parentid) && !empty($formoptions->parentid)) ||
                        (!is_array($formoptions->parentid) && $formoptions->parentid > 0)
                    ) {
                        $fields = array("fullname"/*, "shortname"*/);

                        $likesql = array();
                        $i = 0;
                        foreach ($fields as $field) {
                            $i++;
                            $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                            $sqlparams["queryparam$i"] = "%$query%";
                        }
                        $sqlfields = implode(" OR ", $likesql);
                        $concatsql .= " AND ($sqlfields) ";

                        //$sqlparams['parentid'] = $formoptions->parentid;
                        list($organisationidssql, $organisationparams) = $DB->get_in_or_equal($formoptions->parentid, SQL_PARAMS_NAMED, 'organisationid');

                        $fields      = 'SELECT id, fullname';
                        $lobssql = " FROM {local_costcenter}
                                         WHERE 1=1 $concatsql AND parentid $organisationidssql ";
                        if ($formoptions->id == 0) {
                            $lobssql .= ' AND visible = 1';
                        }
                        $sqlparams = array_merge($sqlparams, $organisationparams);
                        $departments = $allobjectarr + $DB->get_records_sql($fields . $lobssql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                        // if ($departments) {
                        //     $totaldepartments = count($departments);
                        //     $moredepartments = $totaldepartments > $perpage;

                        //     if ($morelobs) {
                        //         // We need to discard the last record.
                        //         array_pop($departments);
                        //     }
                        // }
                        $return = array_values($departments);
                    } else {
                        $return = $allobjectarr;
                    }
                    break;
                case 'costcenter_subdepartment_selector':
                    if ((is_array($formoptions->parentid) && !empty($formoptions->parentid)) ||
                        (!is_array($formoptions->parentid) && $formoptions->parentid > 0)
                    ) {
                        $fields = array("fullname"/*, "shortname"*/);

                        $likesql = array();
                        $i = 0;
                        foreach ($fields as $field) {
                            $i++;
                            $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                            $sqlparams["queryparam$i"] = "%$query%";
                        }
                        $sqlfields = implode(" OR ", $likesql);
                        $concatsql .= " AND ($sqlfields) ";

                        //$sqlparams['parentid'] = $formoptions->parentid;

                        if (count($formoptions->parentid) == 1 && array_values($formoptions->parentid)[0] != 0) {

                            list($parentidsql, $parentparams) = $DB->get_in_or_equal($formoptions->parentid, SQL_PARAMS_NAMED, 'organisationid');

                            $fields      = 'SELECT id, fullname';
                            $subdepartmentsql = " FROM {local_costcenter}
                                             WHERE 1=1 $concatsql AND parentid $parentidsql ";
                            if ($formoptions->id == 0) {
                                $subdepartmentsql .= ' AND visible = 1';
                            }
                            $sqlparams = array_merge($sqlparams, $parentparams);

                            $subdepartments = $allobjectarr + $DB->get_records_sql($fields . $subdepartmentsql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                            if ($departments) {
                                $totalsubdepartments = count($subdepartments);
                                $moresubdepartments = $totalsubdepartments > $perpage;

                                if ($moresubdepartments) {
                                    // We need to discard the last record.
                                    array_pop($subdepartments);
                                }
                            }
                            $return = array_values(json_decode(json_encode(($subdepartments)), true));
                        } else {
                            $return = array_values(json_decode(json_encode(($allobjectarr)), true));
                        }
                    } else {
                        $return = array_values(json_decode(json_encode(($allobjectarr)), true));
                    }
                    break;
                case 'costcenter_category_selector':
                    if (
                        (int)$formoptions->organisationid  ||
                        (int)$formoptions->departmentid ||
                        (int)$formoptions->subdepartment
                    ) {
                        $parentid_array = array();
                        if (!empty($formoptions->subdepartment)) {
                            if (is_array($formoptions->subdepartment)) {
                                if (count($formoptions->subdepartment) == 1) {
                                    $subdepartment = $formoptions->subdepartment[0];
                                } else {
                                    $subdepartment = 0; //all condition application for multiple selections
                                }
                            } else {
                                $subdepartment = $formoptions->departmentid;
                            }
                            if ((int)$subdepartment > 0) {
                                $parentid = $subdepartment;
                            }
                        }
                        // var_dump($parentid);
                        if (!empty($formoptions->departmentid)  && empty($parentid)) {
                            if (is_array($formoptions->departmentid)) {
                                if (count($formoptions->departmentid) == 1) {
                                    $departmentid = $formoptions->departmentid[0];
                                } else {
                                    $departmentid = 0; //add condition application for multiple selection.
                                }
                            } else {
                                $departmentid = $formoptions->departmentid;
                            }
                            if ((int)$departmentid > 0) {
                                $parentid = $departmentid;
                            }
                        }
                        if (!empty($formoptions->organisationid) && empty($parentid)) {
                            if ((int)$formoptions->organisationid > 0) {
                                $parentid = $formoptions->organisationid;
                            }
                        }

                        $parentcategory = $DB->get_field('local_costcenter', 'category', array('id' => $parentid));
                        $fields = array("name"/*, "shortname"*/);

                        $likesql = array();
                        $i = 0;
                        if ($query != '') {
                            foreach ($fields as $field) {
                                $i++;
                                $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                                $sqlparams["queryparam$i"] = "%$query%";
                            }
                            $sqlfields = implode(" OR ", $likesql);
                            $concatsql = " AND ($sqlfields) ";
                        } else {
                            $sqlparams = [];
                            $concatsql = " ";
                        }
                        //$sqlparams['parentid'] = $formoptions->parentid;

                        $fields      = 'SELECT id, path AS fullname';
                        $categoriessql = " FROM {local_custom_category}
                                         WHERE 1=1 $concatsql 
                                         AND (path like '%/{$parentcategory}/%' OR id = $parentcategory) ";
                        // if ($formoptions->id == 0) {
                        //     $categoriessql .= ' AND visible = 1';
                        // }
                        // var_dump($fields.$categoriessql);
                        // var_dump($sqlparams);
                        $categories = $DB->get_records_sql_menu($fields . $categoriessql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                        if ($categories) {
                            $totalcategories = count($categories);
                            $morecategories = $totalcategories > $perpage;

                            if ($morecategories) {
                                // We need to discard the last record.
                                array_pop($categories);
                            }
                        }
                        foreach ($categories as $key => $categorywise) {
                            $explodepaths = explode('/', $categorywise);

                            $countcat = count($explodepaths);
                            if ($countcat > 0) {
                                $catpathnames = array();
                                for ($i = 0; $i < $countcat; $i++) {
                                    if ($i != 0) {
                                        $catpathnames[$i] = $DB->get_field('course_categories', 'name', array('id' => $explodepaths[$i]));
                                    }
                                }
                                if (count($catpathnames) > 1) {
                                    $return[] = array('id' => $key, 'fullname' => implode(' / ', $catpathnames));
                                } else {
                                    $return[] = array('id' => $key, 'fullname' => $catpathnames[1]);;
                                }
                            }
                        }
                    }
                    break;
                case 'costcenter_course_selector':
                    $classname = '\\local_courses\\local\\general_lib';
                    $class = class_exists($classname) ? new $classname() : NULL;
                    if (!is_null($class)) {
                        $methodname = 'get_courses_having_completion_criteria';
                        if (isset($formoptions->courseid) && $formoptions->courseid > 1 && method_exists($class, $methodname)) {
                            $courses = $class->$methodname($formoptions->courseid, $query, ($page * $perpage) - 0, $perpage + 1);
                            if ($courses) {
                                $totalcourses = count($courses);
                                $morecourses = $totalcourses > $perpage;

                                if ($morecourses) {
                                    // We need to discard the last record.
                                    array_pop($courses);
                                }
                            }
                            $return = array_values(json_decode(json_encode(($courses)), true));
                        }
                    }
                    break;
                case 'costecenter_coursetype_selector':
                    if ((is_array($formoptions->parentid) && !empty($formoptions->parentid)) ||
                        (!is_array($formoptions->parentid) && $formoptions->parentid > 0)
                    ) {
                        list($organisationidssql, $organisationparams) = $DB->get_in_or_equal($formoptions->parentid, SQL_PARAMS_NAMED, 'organisationid');

                        $fields      = 'SELECT id, name as fullname';
                        $lobssql = " FROM {local_course_types}
                                         WHERE  active = 1 AND orgid $organisationidssql OR orgid=0 ";
                        $coursetypes = $DB->get_records_sql($fields . $lobssql, $organisationparams, ($page * $perpage) - 0, $perpage + 1);
                        $return = array_values(json_decode(json_encode($coursetypes), true));
                    }
                    break;
                case 'custom_category_selector':
                    if ((is_array($formoptions->parentid) && !empty($formoptions->parentid)) ||
                        (!is_array($formoptions->parentid) && $formoptions->parentid > 0)
                    ) {
                        $sqlparams['parentid'] = $formoptions->parentid ? $formoptions->parentid : 0;
                        $fields = array("fullname");
                        $likesql = array();
                        $i = 0;
                        if (!empty($query)) {
                            foreach ($fields as $field) {
                                $i++;
                                $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                                $sqlparams["queryparam$i"] = "%$query%";
                            }
                            $sqlfields = implode(" OR ", $likesql);
                            $concatsql .= " AND ($sqlfields) ";
                        }
                        if ($formoptions->type == 'parent_selector') {
                            $fields      = 'SELECT id, fullname , parentid ';
                            $accountssql = " FROM {local_custom_category}
                                WHERE 1=1 AND depth =1 $concatsql AND costcenterid = :parentid ";
                        } else {
                            $fields      = 'SELECT id, fullname, parentid ';
                            $accountssql = " FROM {local_custom_category}
                                WHERE 1=1 $concatsql AND costcenterid = :parentid ";
                        }
                        if ($formoptions->id > 0) {
                            $accountssql .= " AND id !=" . $formoptions->id;
                        }


                        $customcat = $DB->get_records_sql($fields . $accountssql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                        foreach ($customcat as $cat) {
                            $parentname = '';
                            if ($cat->parentid > 0) {
                                $parentname = $DB->get_field('local_custom_category', 'fullname', ['id' => $cat->parentid]);
                            }
                            if ($parentname) {
                                $cat->fullname = $parentname . ' / ' . $cat->fullname;
                            }
                        }
                        if ($formoptions->enableallfield) {
                            $customcat = $allobjectarr + $customcat;
                        }
                        if ($customcat) {
                            $totalaccounts = count($customcat);
                            $moreaccounts = $totalaccounts > $perpage;

                            if ($moreaccounts) {
                                // We need to discard the last record.
                                array_pop($customcat);
                            }
                        }
                        foreach ($customcat as $key => $categorywise) {

                            if(!is_string($categorywise)){
                                continue;
                            }

                            $explodepaths = explode('/', $categorywise);

                            $countcat = count($explodepaths);
                            if ($countcat > 0) {
                                $catpathnames = array();
                                for ($i = 0; $i < $countcat; $i++) {
                                    if ($i != 0) {
                                        $catpathnames[$i] = $DB->get_field('local_custom_category', 'fullname', array('id' => $explodepaths[$i]));
                                    }
                                }
                                if (count($catpathnames) > 1) {
                                    $return[] = array('id' => $key, 'fullname' => implode(' / ', $catpathnames));
                                } else {
                                    $return[] = array('id' => $key, 'fullname' => $catpathnames[1]);;
                                }
                            }
                        }

                        $return = array_values(json_decode(json_encode($customcat), true));
                    }
                    break;
                case 'costcenter_element_selector':
                    $fields = array("fullname");
                    if ($formoptions->depth < $USER->useraccess['currentroleinfo']['depth']) {
                        $elements = $USER->useraccess['currentroleinfo']['contextinfo'];
                        if ($USER->useraccess['currentroleinfo']['depth'] - 1 > $formoptions->depth) {
                            $elements = [$elements[0]];
                        }

                        $accounts = array_map(function ($data) use ($formoptions, $DB) {
                            $identifiers = explode('/', $data['costcenterpath']);
                            $identifier = $identifiers[$formoptions->depth];
                            $fullname = $DB->get_field('local_costcenter', 'fullname', ['id' => $identifier]);
                            return array('id' => $identifier, 'fullname' => $fullname);
                        }, $elements);
                    } else {
                        $parentids = [];
                        if (is_array($formoptions->parentid)) {
                            $parentids = $formoptions->parentid;
                            if (empty($parentids) && $formoptions->prefix != 'filter') {
                                array_push($parentids, 0);
                            }
                        } else {
                            $parentid = $formoptions->parentid ? $formoptions->parentid : 0;
                            array_push($parentids, $parentid);
                        }
                        if (!empty($parentids)) {
                            list($parentsql, $parentparams) = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED, 'organisationid');
                        } else {
                            $parentsql = '';
                            $parentparams = [];
                        }
                        // $sqlparams['parentid'] = $formoptions->parentid ? $formoptions->parentid : 0;
                        $sqlparams['depth'] = $formoptions->depth;
                        $likesql = array();
                        $i = 0;
                        if (!empty($query)) {
                            foreach ($fields as $field) {
                                $i++;
                                $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                                $sqlparams["queryparam$i"] = "%$query%";
                            }
                            $sqlfields = implode(" OR ", $likesql);
                            $concatsql .= " AND ($sqlfields) ";
                        }
                        $costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='path');
                        $fields      = 'SELECT id, fullname';
                        $accountssql = " FROM {local_costcenter}
                                         WHERE 1=1 $concatsql AND parentid {$parentsql} $costcenterpathconcatsql AND depth = :depth ";
                        if ($formoptions->id == 0) {
                            $accountssql .= ' AND visible = 1';
                        }
                        $accounts = $DB->get_records_sql($fields . $accountssql, array_merge($parentparams, $sqlparams), ($page * $perpage) - 0, $perpage + 1);

                        if ($formoptions->enableallfield) {
                            $accounts = $allobjectarr + $accounts;
                        }
                        if ($accounts) {
                            $totalaccounts = count($accounts);
                            $moreaccounts = $totalaccounts > $perpage;

                            if ($moreaccounts) {
                                // We need to discard the last record.
                                array_pop($accounts);
                            }
                        }
                    }
                    $return = array_values(json_decode(json_encode(($accounts)), true));
                    break;
                case 'user_supervisor_selector':
                    $sqlparams['parentpath'] = $formoptions->parentid ? '%/' . $formoptions->parentid . '/%' : 0;
                    if (!empty($query)) {
                        $fields = array("u.firstname", "u.lastname");
                        foreach ($fields as $field) {
                            $i++;
                            $likesql[] = $DB->sql_like($field, ":queryparam$i", false);
                            $sqlparams["queryparam$i"] = "%$query%";
                        }
                        $sqlfields = implode(" OR ", $likesql);
                        $concatsql .= " AND ($sqlfields) ";
                    }
                    $fields = "SELECT id, concat(u.firstname,' ',u.lastname) AS fullname ";
                    $userssql = " FROM {user} AS u
                                         WHERE u.suspended = 0 AND u.deleted = 0 $concatsql AND CONCAT('/', u.open_path,'/') LIKE :parentpath ";
                    if ($formoptions->id > 0) {
                        $userssql .= " AND u.id <> :userid ";
                        $sqlparams['userid'] = $formoptions->id;
                    }
                    $users = $DB->get_records_sql($fields . $userssql, $sqlparams, ($page * $perpage) - 0, $perpage + 1);
                    if ($users) {
                        $totalusers = count($users);
                        $moreusers = $totalusers > $perpage;
                        if ($moreusers) {
                            // We need to discard the last record.
                            array_pop($users);
                        }
                    }
                    $return = array_values(json_decode(json_encode(($users)), true));
                    break;
            }
        }
        return json_encode($return);
    }
    public static function form_option_selector_returns()
    {
        return new external_value(PARAM_RAW, 'data');
    }

    /* Start of Department Creation*/
    public static function department_create_parameters()
    {
        return new external_function_parameters(
            array(
                'orgcode' => new external_value(PARAM_RAW, 'The context id for the evaluation'),
                'fullname' => new external_value(PARAM_RAW, 'DepartmentName'),
                'departmentcode' => new external_value(PARAM_RAW, 'Department Code'),


            )
        );
    }

    /**
     * form submission of costcenter name and returns instance of this object
     *
     * @param int $contextid 
     * @param [string] $jsonformdata 
     * @return costcenter form submits
     */
    public static function department_create($orgcode, $fullname, $departmentcode)
    {
        global $PAGE, $CFG, $DB;

        require_once($CFG->dirroot . '/local/costcenter/lib.php');

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::department_create_parameters(),
            ['orgcode' => $orgcode, 'fullname' => $fullname, 'departmentcode' => $departmentcode]
        );
        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
        // We always must call validate_context in a webservice.
        $departmentcode = preg_replace('/\s+/', '', $departmentcode);
        $data = array();
        $orgid = $DB->get_field('local_costcenter', 'id', array('shortname' => $orgcode));

        $valdata = $DB->get_field('local_costcenter', 'id', array('shortname' => $departmentcode, 'parentid' => $orgid));
        $deptdata->parentid = $orgid;
        $deptdata->fullname = $fullname;
        $deptdata->shortname = $departmentcode;

        if ($valdata) {
            $valdata->id = $valdata;
            $deptdata->id = $valdata;
            $costcenterupdate = costcenter_edit_instance($valdata, $deptdata);
            $return[] = array('status' => 'success', 'departmentid' => $valdata);
        } else {
            $costcenterinsert = costcenter_insert_instance($deptdata);

            if ($costcenterinsert) {
                $return[] = array('status' => 'success', 'departmentid' => $costcenterinsert);
            }
        }
        return $return;
    }



    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */

    public static function department_create_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'status' => new external_value(PARAM_TEXT, 'status'),
                    'departmentid'       => new external_value(PARAM_INT, 'Department id', VALUE_OPTIONAL, NULL)
                )
            )
        );
    }


    /*End of Department creation */

    public static function generate_shortcode_parameters()
    {
        return new external_function_parameters(
            array(
                'accountid' => new external_value(PARAM_INT, 'Account id', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the account', false),
                'actions' => new external_value(PARAM_RAW, 'action', false),

            )
        );
    }
    public static function generate_shortcode($accountid, $contextid, $actions)
    {
        $params = self::validate_parameters(
            self::generate_shortcode_parameters(),
            ['accountid' => $accountid, 'contextid' => $contextid, 'actions' => $actions]
        );
        $context = \context::instance_by_id($params['contextid']);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        global $DB;
        switch ($actions) {
            case 'accountselect':
                if ($accountid > 0) {
                    try {
                        $return = $DB->get_field('local_costcenter', 'shortname', array('id' => $accountid));
                    } catch (Exception $e) {
                        $return = '';
                    }
                }
                break;
            default:

                break;
        }
        return $return;
    }
    public static function generate_shortcode_returns()
    {
        return new external_value(PARAM_RAW, 'Data of account');
    }
}
