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
    protected const COURSE_CONTENT = 'course';
    protected const MODULE_CONTENT = 'module';

    protected const EXTRACT_METHOD_FIRST = 0;
    protected const EXTRACT_METHOD_LAST = 1;


    protected static $content_path_extractmethods = array(

                self::COURSE_CONTENT  =>  self::EXTRACT_METHOD_FIRST,

                self::MODULE_CONTENT  =>  self::EXTRACT_METHOD_LAST
        );


    public static function get_costcenter_path_field_concatsql($columnname,$costcenterparamid,$datatype=self::MODULE_CONTENT){

        global $DB,$USER;

        $concatsql="";

        if(empty($USER->id) || is_siteadmin()){

            return $concatsql;

        }else{
            if($costcenterparamid == null || $costcenterparamid == 0){

                $concatsql =self::get_user_roleswitch_costcenterpath($columnname,$datatype);

            }elseif($costcenterparamid > 0){

                 try{

                    $cache = \cache::make('local_costcenter','costcenterpathdata');

                    $cachekey = "costcenter_path_$costcenterparamid";

                    $costcenterpath = $cache->get($cachekey);

                    if ($costcenterpath === false) {

                        $sql = "SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.id= :costcenterid ";

                        $costcenterpath = $DB->get_field_sql($sql, array('costcenterid' => $costcenterparamid));

                        if($costcenterpath){

                            $extractcostcenterpath=array_values(array_filter(explode('/',$costcenterpath)));

                            if(self::$content_path_extractmethods[$datatype]){

                                $pathvalue=end($extractcostcenterpath);

                            }else{

                                $pathvalue=$extractcostcenterpath[0];

                            }

                            $concatsql = " AND concat('/',$columnname,'/' ) like '%/$pathvalue/%' ";

                            $cache->set($cachekey, $costcenterpath);

                        }else{

                            $concatsql = self::get_user_roleswitch_costcenterpath($columnname,$datatype);

                        }
                    }else{

                        $extractcostcenterpath=array_values(array_filter(explode('/',$costcenterpath)));

                        if(self::$content_path_extractmethods[$datatype]){

                            $pathvalue=end($extractcostcenterpath);

                        }else{

                            $pathvalue=$extractcostcenterpath[0];

                        }

                        $concatsql = " AND concat('/',$columnname,'/' ) like '%/$pathvalue/%' ";

                    }

                }catch(dml_exception $e){
                    print_r($e->debuginfo);
                }
            }

            return $concatsql;

        }
    }
    public static function get_module_context($costcenterparamid = null){

        global $DB,$USER;

        if(empty($USER->id) || is_siteadmin()){

            $context = \context_system::instance();

            return $context;

        }else{
            if($costcenterparamid == null || $costcenterparamid == 0){

                $context=self::get_user_roleswitch_context();

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
    public static function get_user_roleswitch_context(){

        global $USER;


        if(!empty($USER->access['currentroleinfo']['contextinfo'])){

            $context =$USER->access['currentroleinfo']['contextinfo'][0]['context'];

        }else{

            if(!empty($USER->access['rsw'])){

                $contextpath=array_values(array_flip($USER->access['rsw']));

                if(!empty($contextpath[0])){

                    $extractcontextpath=array_values(array_filter(explode('/',$contextpath[0])));

                    $pathvalue=end($extractcontextpath);

                    $context =\context::instance_by_id($pathvalue);

                }else{

                    $context = \context_system::instance();

                }
            }else{
                    $context = \context_system::instance();
            }

        }

        return $context;
    }
    public static function get_user_roles_in_catgeorycontexts($userid = null){

        global $DB, $USER;

        if(is_null($userid)){

            $userid = $USER->id;

        }

        $assignedsql = "SELECT ra.id, cc.id as categoryid, cc.name as categoryname, r.id as roleid, r.name AS rolename, r.shortname as rolecode, ra.contextid, c.depth, cc.path
        FROM {role_assignments} AS ra
        JOIN {role} AS r ON r.id =  ra.roleid
        JOIN {context} AS c ON c.id = ra.contextid AND c.contextlevel = :contextlevel
        JOIN {course_categories} AS cc ON cc.id = c.instanceid
        WHERE ra.userid = :userid  ORDER BY ra.id DESC ";

        $assignedroles = $DB->get_records_sql($assignedsql, ['userid' => $userid,'contextlevel'=>CONTEXT_COURSECAT]);

        return $assignedroles;
    }
    public static function get_costcenterpath_context($context){

        global $DB, $USER;
        $categoryid = $context->instanceid;
        $sql = "SELECT cc.path FROM {local_costcenter} AS cc WHERE cc.category= :categoryid ";
        $costcenterpath = $DB->get_field_sql($sql, array('categoryid' => $categoryid));

        if(!$costcenterpath){

            $costcenterpath=0;

        }

        return $costcenterpath;
    }
    public static function get_category_info($categoryid, $value = null){
        global $DB;
        $coursecatrecordcache = \cache::make('core', 'coursecatrecords');
        $coursecat = $coursecatrecordcache->get($categoryid);
        if ($coursecat === false) {
            $coursecat = $DB->get_record('course_categories', array('id' => $categoryid));
        }
        if(is_null($value)){
            return $coursecat;
        }else{
            return $coursecat->$value;
        }
    }
    public static function get_user_roleswitch_costcenterpath($columnname){

        global $USER;

        $concatsql="";

        $costcenterpath=array();

        $roleid=$USER->access['currentroleinfo']['roleid'];

        if(!empty($USER->access['currentroleinfo']['switchedcostcenterpath'][$roleid])){

            $costcenterpath=$USER->access['currentroleinfo']['switchedcostcenterpath'][$roleid];

        }elseif(!empty($USER->access['currentroleinfo']['contextinfo'])){


            $contextsinfo =$USER->access['currentroleinfo']['contextinfo'];


            foreach($contextsinfo as $contextinfo){


                $extractcostcenterpath=array_values(array_filter(explode('/',$contextinfo['costcenterpath'])));

               if(self::$content_path_extractmethods[$datatype]){

                    $pathvalue=end($extractcostcenterpath);

                }else{

                    $pathvalue=$extractcostcenterpath[0];

                }


                if(empty($costcenterpath[$pathvalue])){

                    $costcenterpath[$pathvalue]= " concat('/',$columnname,'/' ) like '%/$pathvalue/%' ";
                }


            }

            if(!empty($costcenterpath)){

                $USER->access['currentroleinfo']['switchedcostcenterpath'][$roleid] = $costcenterpath;
            }

        }
        if(!empty($costcenterpath)){

            $concatsql="AND (".implode(" OR ", $costcenterpath).")";
        }

        return $concatsql;
    }
}
