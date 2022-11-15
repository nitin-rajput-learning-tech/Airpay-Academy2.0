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
    public static function get_module_context($costcenterparamid = null){

        global $DB,$USER,$OUTPUT;

        if((empty($USER->id) || is_siteadmin()) && $costcenterparamid == null){

            $context = \context_system::instance();

            return $context;

        }else{                


            if(($costcenterparamid == null || $costcenterparamid == 0)){


                if(isset($USER->access['rsw']) && !empty($USER->access['rsw'])){

                    if(!empty($USER->access['rsw']['currentroleinfo']['context'])){

                        $context =$USER->access['rsw']['currentroleinfo']['context'];


                    }else{

                        $contextpath=array_values(array_flip($USER->access['rsw']));

                        $extractcontextpath=array_filter(explode('/',$contextpath[0]));

                        $endpathvalue=end($extractcontextpath);

                        $context =\context::instance_by_id($endpathvalue);

                    
                    }

                }else{

                     $highestroleinfo = self::get_user_roles_in_catgeorycontexts($USER->id);

                     if (!empty($highestroleinfo)) {

                        $accessdata = get_empty_accessdata();

                        $context =\context::instance_by_id($highestroleinfo->contextid);
        
                        $OUTPUT->roleswitch($highestroleinfo->roleid, $context, $accessdata);


                    }else{

                        $context = \context_system::instance();

                    }
                }

            }elseif($costcenterparamid > 0){

                 try{

                    // Get a cache instance
                    $cache = \cache::make('local_costcenter','costcentercontextdata');

                    // Get all of the roles used in this context, including special roles such as user, and frontpageuser.

                    $cachekey = "costcenter_context_$costcenterparamid";

                    $context = $cache->get($cachekey);


                    if ($context === false) {

                        $sql = "SELECT cc.category FROM {local_costcenter} AS cc WHERE cc.id= :costcenterid ";

                        $costcentercategory = $DB->get_field_sql($sql, array('costcenterid' => $costcenterparamid)); 

                        if($costcentercategory){

                            $context = \context_coursecat::instance($costcentercategory);

                            $cache->set($cachekey, $context);

                        }else{

                            $context = \context_system::instance();
                        }

                    }
                }catch(dml_exception $e){

                    print_r($e->debuginfo);

                }

            }

            return $context;

        }
    }
    public static function get_user_roles_in_catgeorycontexts($userid = null){
        global $DB, $USER;
        if(is_null($userid)){
            $userid = $USER->id;
        }
        $assignedsql = "SELECT ra.id, cc.id as categoryid, cc.name as categoryname, r.id as roleid, r.name AS rolename, r.shortname as rolecode, ra.contextid
        FROM {role_assignments} AS ra 
        JOIN {role} AS r ON r.id =  ra.roleid
        JOIN {context} AS c ON c.id = ra.contextid AND c.contextlevel = 40
        JOIN {course_categories} AS cc ON cc.id = c.instanceid 
        WHERE ra.userid = :userid ORDER BY ra.id DESC ";
        $assignedroles = $DB->get_records_sql($assignedsql, ['userid' => $userid]);
        return $assignedroles;
    }

}
