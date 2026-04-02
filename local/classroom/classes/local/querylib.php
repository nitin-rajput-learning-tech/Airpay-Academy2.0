<?php
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
 * @package Bizlms 
 * @subpackage local_classroom
 */

namespace local_classroom\local;

defined('MOODLE_INTERNAL') || die();

class querylib {
    /**
     * Get authenticated user manage departments
     * @return array format departments list
     */
    public function get_user_departments() {
        global $DB, $USER;

        $sql = "SELECT lc.id, CONCAT(lc.fullname, '-', lc.shortname) AS fullshrtname
                  FROM {local_costcenter} lc
                  JOIN {user} u on (concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') or concat('/',u.open_path,'/') LIKE concat('%/',lc.parentid,'/%') ) AND lc.depth = 1
                WHERE u.id = :userid";
        $departments = $DB->get_records_sql_menu($sql, array('userid' => $USER->id));
        if (empty($departments)) {
            $departments = array();
        }
        return $departments;
    }
    /**
     * Get authenticated user manage courses based on departments
     * @return array format courses list
     */
    public function get_courses($costcenters = false) {
        global $DB, $USER;
        $costcentersql = '';
        $params = array();
        $courses = array();
        if (!empty($costcenters)) {
            $costcenter = implode(',', $costcenters);
            $costcentersql .= " AND concat('/',u.open_path,'/') LIKE  (:costcenter) ";
            $params['costcenter'] = $costcenter;
        }
        $sql = "SELECT c.id, c.fullname
                  FROM {course} as c
                 WHERE 1 = 1 AND c.visible = :visible AND c.id <> :siteid $costcentersql";
        $params['siteid'] = SITEID;
        $params['visible'] = 1;
        $courses = $DB->get_records_sql_menu($sql, $params);
        return $courses;
    }
    /**
     * [get_user_department_trainerslist description]
     * @method get_user_department_trainerslist
     * @param  boolean                          $service     [description]
     * @param  boolean                          $costcenters [description]
     * @param  array                            $trainers    [description]
     * @param  string                           $query       [description]
     * @return [type]                                        [description]
     */
    public function get_user_department_trainerslist($service = false, $costcenters = false,
        $trainers = array(), $query = '') {
        global $DB,$USER;
        $costcentersql = '';
        $concatsql = '';
        $categorycontext = (new \local_classroom\lib\accesslib())::get_module_context();
        $params = array();
        list($ctxcondition, $ctxparams) = $DB->get_in_or_equal($categorycontext->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'ctx');
        $params = array_merge($params, $ctxparams);
        if (!empty($trainers)) {
            list($trainerslistsql, $trainerslistparams) = $DB->get_in_or_equal($trainers, SQL_PARAMS_NAMED, 'crtr');
            $params = array_merge($params, $trainerslistparams);
        }
        if (!empty(array_filter((array)$costcenters))) {
            $costcenters = implode(',', $costcenters);
            $concatsql .= " AND concat('/',u.open_path,'/') LIKE :costcenter ";
            $params['costcenter'] = '%'.$costcenters.'%';
        }
         if ((has_capability('local/classroom:manageclassroom', $categorycontext)) && ( !is_siteadmin() )) {
            $concatsql .= (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        }
        if (!empty($query)) {

            $fields = " u.email LIKE :search1 OR CONCAT(u.firstname,' ', u.lastname) LIKE :search2 ";
            $params['search1'] = '%' . $query . '%';
            $params['search2'] = '%' . $query . '%';
            $concatsql .= " AND ($fields) ";
        }

        $trainerslist = array();
        $params['confirmed'] = 1;
        $params['suspended'] = 0;
        $params['deleted'] = 0;


        $fields = "SELECT u.id , CONCAT(u.firstname,' ', u.lastname) AS fullname ";
        $sql = "FROM {role_capabilities} as rc
                  JOIN {role_assignments} ra ON ra.roleid=rc.roleid
                  JOIN mdl_role mr ON mr.id = rc.roleid
                  JOIN {user} u ON u.id = ra.userid
                 WHERE u.confirmed = :confirmed
                  AND u.suspended = :suspended AND u.deleted = :deleted AND u.id > 2
                       AND rc.capability LIKE '%trainer_viewclassroom%' and rc.permission=1 AND shortname = 'trainer' ";
        if (!empty($trainers)) {
            $sql .= " AND u.id $trainerslistsql";
        }
        $sql.= $concatsql;
        
        $order = " ORDER BY u.id ASC";
        if ($service) {
            $trainerslist = $DB->get_records_sql($fields . $sql . $order, $params);
        } else {
            $trainerslist = $DB->get_records_sql_menu($fields . $sql . $order, $params);
        }

        return $trainerslist;

    }
    public function get_department_institute_list($costcenters, $institutetype) {
        global $DB;

        $costcenters = implode(',', array_flip($costcenters));

        $sql = "SELECT lci.id,CONCAT(lci.fullname, '-', lci.shortnname) AS fullshrtname
                 FROM {local_location_institutes} lci
                WHERE lci.costcenter IN (:costcenterid) AND lci.institute_type = :institutetype AND lci.visible= :visiblefld";
        $institutelist = $DB->get_records_sql_menu($sql,
            array('costcenterid' => $costcenters, 'institutetype' => $institutetype,
                'visiblefld' => 1));

        if (empty($institutelist)) {
            $institutelist = array();
        }
        return $institutelist;

    }
    public function get_coursecategories() {
        global $DB;
        $params = array();
        $coursecategories = array();
        $sql = "SELECT cc.id, cc.name
                  FROM {course_categories} cc
                 WHERE 1 = 1 AND cc.visible = :visible";
        $params['visible'] = 1;
        $coursecategories = $DB->get_records_sql_menu($sql, $params);
        return $coursecategories;
    }
    public function get_classroom_institutes($institutetype = 0, $service = array()) {
        global $DB;
        $institutes = array();
        if ($institutetype > 0) {
            $params = array();
            $institutessql = "SELECT id, fullname
                                FROM {local_location_institutes}
                               WHERE institute_type = :institute_type";
            $params['institute_type'] = $institutetype;
            if (!empty($service)) {
                if ($service['instituteid'] > 0) {
                    $institutessql .= " AND id = :instituteid ";
                    $params['instituteid'] = $instituteid;
                }
                if ($service['classroomid'] > 0) {
                    $institutessql .= " AND costcenter = :costcenter ";                   
                    $open_path= $DB->get_field('local_classroom', 'open_path', array('id' => $service['classroomid']));
                    list($zero, $org, $ctr, $bu, $cu, $territory) = explode("/",$open_path);
                    $params['costcenter'] =$org;
                }
                if (!empty($service['query'])) {
                    $institutessql .= " AND fullname LIKE :query ";
                    $params['query'] = '%' . $service['query'] . '%';
                }
            }
            $institutes = $DB->get_records_sql($institutessql, $params);
        }
        return $institutes;
    }
    public function get_classroom_institute_rooms($clasroomid) {
        global $DB;
        $locationroomlists = array();
        if ($clasroomid > 0) {
            $locationroomlistssql = "SELECT cr.id, cr.name
                                       FROM {local_location_room} AS cr
                                       JOIN {local_location_institutes} AS ci ON ci.id = cr.instituteid
                                       JOIN {local_classroom} AS c ON ( c.instituteid = ci.id
                                        AND c.institute_type = ci.institute_type)
                                       WHERE cr.visible = 1 AND ci.visible = 1 AND c.id = :classroomid ";

            $locationroomlists = $DB->get_records_sql_menu($locationroomlistssql,array('classroomid' => $clasroomid));
        }
        return $locationroomlists;
    }
}
