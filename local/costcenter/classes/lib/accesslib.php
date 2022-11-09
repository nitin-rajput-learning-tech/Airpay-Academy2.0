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
 * @package BizLMS
 * @subpackage local_costcenter
 */

namespace local_costcenter\lib;

/**
 * get access lib functions
 */
class accesslib
{
    public static function get_module_context($costcenterid = null){

        global $DB,$USER;

        if(is_siteadmin()){

            $context = \context_system::instance();

            return $context;

        }elseif($costcenterid == null || $costcenterid == 0){

            print_object($USER->access['rsw']);

        
            $userraarray = array_values($USER->access['ra']);

            $fisrtrolearray = array_values($userraarray[2]);

            $fisrtroleid=$fisrtrolearray[0];

            if(isset($fisrtroleid)){

                $contextid=$DB->get_field('role_assignments','contextid',  array('roleid'=>$fisrtroleid,'userid'=>$USER->id));

                $context =\context::instance_by_id($contextid);

                if( (isset($USER->access['rsw']) && empty($USER->access['rsw'])) ){

                    $accessdata = get_empty_accessdata();

                    if(self::roleswitch($fisrtroleid, $context, $accessdata)){

                        return $context;

                    }else{

                        $context = \context_user::instance($USER->id);
                    }

                }

            } 

            $costcenterid=$USER->open_costcenterid ? $USER->open_costcenterid : 0;


            if($costcenterid == 0){

                $context = \context_user::instance($USER->id);

                return $context;
            }

        }
         try{

            // Get a cache instance
            $cache = \cache::make('local_costcenter','costcentercontextdata');

            // Get all of the roles used in this context, including special roles such as user, and frontpageuser.

            $cachekey = "costcenter_context_$costcenterid";

            $context = $cache->get($cachekey);


            if ($context === false) {

                $sql = "SELECT cc.category FROM {local_costcenter} AS cc WHERE cc.id= :costcenterid ";

                $costcentercategory = $DB->get_field_sql($sql, array('costcenterid' => $costcenterid)); 

                if($costcentercategory){

                    $context = \context_coursecat::instance($costcentercategory);

                    $cache->set($cachekey, $context);

                }else{

                    $context = \context_user::instance($USER->id);
                }

            }
        }

        catch(dml_exception $e){

            print_r($e->debuginfo);

        }
            
        return $context;

    }
    /**
     * sitelevel roleswitch as buttons.
     *
     * @param int $courseid A course object.
     * @param stdClass $context usually site context.
     * @return string HTML.
     */
    public static function roleswitch($roleid, $context, &$accessdata){

        global $DB, $ACCESSLIB_PRIVATE, $USER;
        $USER->access['rsw'][$context->path] = $roleid;
       /* Get the relevant rolecaps into rdef
        * - relevant role caps
        *   - at ctx and above
        *   - below this ctx
        */

        if (empty($context->path)) {
            // weird, this should not happen
            return;
        }

        list($parentsaself, $params) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'pc_');
        $params['roleid'] = $roleid;
        $params['childpath'] = $context->path.'/%';

        $sql = "SELECT ctx.path, rc.capability, rc.permission
                  FROM {role_capabilities} rc
                  JOIN {context} ctx ON (rc.contextid = ctx.id)
                 WHERE rc.roleid = :roleid AND (ctx.id $parentsaself OR ctx.path LIKE :childpath)
              ORDER BY rc.capability"; // fixed capability order is necessary for rdef dedupe
        $rs = $DB->get_recordset_sql($sql, $params);

        $newrdefs = array();
        foreach ($rs as $rd) {
            $k = $rd->path.':'.$roleid;
            if (isset($accessdata['rdef'][$k])) {
                continue;
            }
            $newrdefs[$k][$rd->capability] = (int)$rd->permission;
        }
        $rs->close();

        // share new role definitions
        foreach ($newrdefs as $k=>$unused) {
            if (!isset($ACCESSLIB_PRIVATE->rolepermissions[$k])) {
                $ACCESSLIB_PRIVATE->rolepermissions[$k] = $newrdefs[$k];
            }
            $accessdata['rdef'][$k] =& $ACCESSLIB_PRIVATE->rolepermissions[$k];
        }
        return true;
    }
    public static function get_user_roles_in_catgeorycontexts($userid = null){
        global $DB, $USER;
        if(is_null($userid)){
            $userid = $USER->id;
        }
        $assignedsql = "SELECT ra.id, cc.name as categoryname, r.id as roleid, r.name AS rolename, r.shortname as rolecode, ra.contextid
        FROM {role_assignments} AS ra 
        JOIN {role} AS r ON r.id =  ra.roleid
        JOIN {context} AS c ON c.id = ra.contextid AND c.contextlevel = 40
        JOIN {course_categories} AS cc ON cc.id = c.instanceid 
        WHERE ra.userid = :userid ORDER BY ra.id DESC ";
        $assignedroles = $DB->get_records_sql($assignedsql, ['userid' => $userid]);
        return $assignedroles;
    }
}
