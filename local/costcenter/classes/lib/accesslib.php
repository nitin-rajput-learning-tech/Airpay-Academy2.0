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

                }else{

                    $context = \context_user::instance($USER->id);
                }

                $cache->set($cachekey, $context);
            }
        }

        catch(dml_exception $e){

            print_r($e->debuginfo);

        }
            
        return $context;

    }
    public static function get_parent_costcenter($costcenterid){

        global $DB,$USER;

        $sql = "SELECT cc.id,cc.category,cc.parentid FROM {local_costcenter} AS cc WHERE cc.id= :costcenterid ";

        $parentcostcenter = $DB->get_record_sql($sql, array('costcenterid' => $costcenterid)); 

        return $parentcostcenter;

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
