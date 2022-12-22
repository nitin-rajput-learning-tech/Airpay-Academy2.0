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
    protected const ALL_MODULE_CONTENT = 'upperandsamepath';
    protected const PATH_MODULE_CONTENT = 'lowerandsamepath';

    public static function get_costcenter_path_field_concatsql($matchcolumnname,$costcenterpath=null,$datatype=null){

        global $DB;

        if($datatype == null){

            $datatype=self::ALL_MODULE_CONTENT;
        }

        $concatsql="";

        if(is_siteadmin()){

            return $concatsql;

        }else{

            if($costcenterpath == null || $costcenterpath == 0){

                $concatsql =self::get_user_roleswitch_costcenterpath_concatsql($matchcolumnname,$datatype);

            }elseif($costcenterpath > 0){

                $concatsql=self::costcenterpath_match_sql($costcenterpath,$matchcolumnname,$datatype);

            }

            return $concatsql;

        }
    }
    public static function get_module_context($costcenterpath = null){

        global $DB;

        if(is_siteadmin()){

            $context = \context_system::instance();

            return $context;

        }else{

            if($costcenterpath == null){

                $context=self::get_user_roleswitch_context();

            }else{

                $context=self::costcenterpath_contextdata($costcenterpath);
            }

            return $context;
        }
    }
    public static function get_user_roleswitch_context(){

        global $USER;


        if(!empty($USER->access['currentroleinfo']['contextinfo'])){

            $firstrole =current($USER->access['currentroleinfo']['contextinfo']);

            $context =$firstrole['context'];

        }else{

            $context = \context_system::instance();

            if(!empty($USER->access['rsw'])){

                $contextpath=current(array_values(array_flip($USER->access['rsw'])));

                if(!empty($contextpath)){

                    $extractcontextpath=array_values(array_filter(explode('/',$contextpath)));

                    if(!empty($extractcontextpath)){

                        $pathvalue=end($extractcontextpath);

                        $context =\context::instance_by_id($pathvalue);
                    }


                }
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

        global $DB;
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
    public static function get_user_roleswitch_costcenterpath_concatsql($matchcolumnname,$datatype){

        global $USER;

        $concatsql="";

        $sqlarray=array();

        if(!empty($USER->access['currentroleinfo']['contextinfo'])){


            $contextarray =$USER->access['currentroleinfo']['contextinfo'];


            foreach($contextarray as $context){

                $costcenterpath=$context['costcenterpath'];

                if(empty($sqlarray[$costcenterpath])){

                    $sqlarray[$costcenterpath]=self::costcenterpath_match_sql($costcenterpath,$matchcolumnname,$datatype);
                }


            }
        }
        if(!empty($sqlarray)){

            $concatsql="AND (".implode(" OR ", $sqlarray).")";
        }

        return $concatsql;
    }

    public static function costcenterpath_match_sql($costcenterpath,$matchcolumnname,$datatype){


        if($datatype == self::ALL_MODULE_CONTENT){

            $match_sql='';
            $paths[] = $costcenterpath.'%';

            while ($costcenterpath = rtrim($costcenterpath,'0123456789')) {
                $costcenterpath = rtrim($costcenterpath, '/');
                if ($costcenterpath === '') {
                  break;
                }
                $paths[] = $costcenterpath;
            }

            if(!empty($paths)){
                foreach($paths AS $path){
                    $pathsql[] = " $matchcolumnname LIKE '$path' ";
                }
                $match_sql.= " ( ".implode(' OR ', $pathsql).' ) ';
            }

        }else{

            $match_sql= " $matchcolumnname LIKE '%$costcenterpath%' ";

        }

        return $match_sql;
    }

    public static function costcenterpath_contextdata($costcenterpath){

        global $DB;

        $context = \context_system::instance();

        try{
            // Get a cache instance
            $cache = \cache::make('local_costcenter','costcenterpathcontextdata');
            // Get all of the roles used in this context, including special roles such as user, and frontpageuser.

            $cachekey = "costcenterpath_context_$costcenterpath";

            $context = $cache->get($cachekey);

            if ($context === false) {

                $sql = "SELECT cc.category FROM {local_costcenter} AS cc WHERE cc.path like '$costcenterpath' ";

                $costcentercategory = $DB->get_field_sql($sql);

                if($costcentercategory){

                    $context = \context_coursecat::instance($costcentercategory);

                    $cache->set($cachekey, $context);

                }
            }

        }catch(dml_exception $e){
            print_r($e->debuginfo);
        }

        return $context;

    }

}
