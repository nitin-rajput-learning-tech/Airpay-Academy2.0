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

        if(is_siteadmin() && $costcenterpath == null){
            return $concatsql;

        }else{

            if($costcenterpath === null || $costcenterpath === 0){

                $concatsql =self::get_user_roleswitch_costcenterpath_concatsql($matchcolumnname,$datatype);

            }else{

                 $first_character = substr($costcenterpath, 0, 1);

                 if($first_character !== '/'){

                    $costcenterpath = "/".$costcenterpath."";

                 }

                $concatsql=self::costcenterpath_match_sql($costcenterpath,$matchcolumnname,$datatype);
                $concatsql="AND (".$concatsql.")";

            }

            return $concatsql;

        }
    }
    public static function get_module_context($costcenterpath = null){

        global $DB;

        if(is_siteadmin() && $costcenterpath == null){

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

        global $USER,$OUTPUT;

        if(!empty($USER->useraccess['currentroleinfo']['contextinfo'])){

            $firstrole =current($USER->useraccess['currentroleinfo']['contextinfo']);

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
            } else{

                /*Start of the role Switch */

                $roles = self::get_user_roles_in_catgeorycontexts($USER->id);

                if (is_array($roles) && (count($roles) > 0)) {


                    $depths = [];
                    $user_ra_array = array_values(array_filter(array_map(function($role)use(&$depths){
                                    $categoryids = array_values(array_filter((explode('/', $role->path))));
                                    $category = \local_costcenter\lib\accesslib::get_category_info($categoryids[0], 'name');
                                    if(!in_array($role->depth.'_'.$categoryids[0], $depths['depth'])){
                                        $depths['depth'][] = $role->depth.'_'.$categoryids[0];
                                        $role->categoryname = $category;
                                        $role->highest_catid = $categoryids[0];
                                        return $role;
                                    }
                                }, $roles)));

                    if(is_array($user_ra_array)){
                        $highest_roleinfo = max($user_ra_array);
                    }else{
                        $highest_roleinfo = (object)['roleid' => 0, 'contextid' => SYSCONTEXTID];
                    }

                    $highest_roleid = '';

                    if((count($roles) > 0) && (!isset($USER->useraccess['currentroleinfo']) || empty($USER->useraccess['currentroleinfo'])) ){

                        if($highest_roleinfo->roleid){

                            $highest_roleid = $highest_roleinfo->roleid;
                            $contextid = $highest_roleinfo->contextid;

                            // $OUTPUT->role_switch_basedon_userroles($highest_roleid, false, $contextid);

                            $context =\context::instance_by_id($contextid);
                        }

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
    public static function get_costcenter_info($costcenterid, $value = null){
        global $DB;
        $costcenterrecordcache = \cache::make('local_costcenter', 'costcenterrecords');
        $costcenterrecord = $costcenterrecordcache->get($costcenterid);
        if ($costcenterrecord === false) {
            $costcenterrecord = $DB->get_record('local_costcenter', array('id' => $costcenterid));
        }
        if(is_null($value) || !isset($costcenterrecord->$value)){
            return $costcenterrecord;
        }else{
            return $costcenterrecord->$value;
        }
    }
    public static function get_user_roleswitch_costcenterpath_concatsql($matchcolumnname,$datatype){

        global $USER;

        $concatsql="";

        $sqlarray=array();

        if(!empty($USER->useraccess['currentroleinfo']['contextinfo'])){


            $contextarray =$USER->useraccess['currentroleinfo']['contextinfo'];


            foreach($contextarray as $context){

                $costcenterpath=$context['costcenterpath'];

                if($costcenterpath > 0){

                    if(empty($sqlarray[$costcenterpath])){

                        $sqlarray[$costcenterpath]=self::costcenterpath_match_sql($costcenterpath,$matchcolumnname,$datatype);
                    }
                }else{

                    $sqlarray[$costcenterpath]=self::userpath_match_sql($matchcolumnname,$datatype);

                }
            }
        }else{

            $sqlarray[]=self::userpath_match_sql($matchcolumnname,$datatype);

        }
        if(!empty($sqlarray)){

            $concatsql="AND (".implode(" OR ", $sqlarray).")";
        }

        return $concatsql;
    }

    public static function costcenterpath_match_sql($costcenterpath,$matchcolumnname,$datatype){


        if($datatype == self::ALL_MODULE_CONTENT){

            $match_sql='';
            $paths[] = $costcenterpath.'/%';
            $paths[] = $costcenterpath;

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

            $match_sql= " CONCAT('',$matchcolumnname,'/') LIKE '%$costcenterpath/%' ";

        }

        return $match_sql;
    }
    public static function userpath_match_sql($matchcolumnname,$datatype){

        global $OUTPUT,$USER,$DB;

        $match_sql='';

        if(!is_siteadmin()){

                $usercostcenterpaths = $DB->get_records_menu('local_userdata', array('userid' => $USER->id), '', 'id, costcenterpath');

                $paths = [];

                foreach($usercostcenterpaths AS $userpath){

                    $userpathinfo = $userpath;

                    $paths[] = $userpathinfo.'/%';

                    if($datatype == self::ALL_MODULE_CONTENT){

                        $paths[] = $userpathinfo;

                        while ($userpathinfo = rtrim($userpathinfo,'0123456789')) {

                            $userpathinfo = rtrim($userpathinfo, '/');

                            if ($userpathinfo === '') {
                              break;
                            }

                            $paths[] = $userpathinfo;
                        }
                    }
                }
                if(!empty($paths)){

                    foreach($paths AS $path){

                        $pathsql[] = " $matchcolumnname LIKE '{$path}' ";

                    }

                    $match_sql.= " ( ".implode(' OR ', $pathsql).' ) ';
                }
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
    public static function get_user_roleswitch_path($depth=0){

        global $USER;

        $costcenterpath = 0;

        if($depth > 0){

            $costcenterpath = array();

        }

        if(!empty($USER->useraccess['currentroleinfo']['contextinfo'])){

            $firstrole =current($USER->useraccess['currentroleinfo']['contextinfo']);

            $costcenterpath =$firstrole['costcenterpath'];

            if($depth > 0){

                $costcenterpatharray =array_filter(explode('/',$costcenterpath));

                if(isset($costcenterpatharray[$depth])){

                    $costcenterpath=$costcenterpatharray[$depth];

                }else{

                   return self::get_user_roleswitch_path($depth-1);

                }

            }

        }

        return $costcenterpath;
    }
    public static function get_user_role_switch_path(){

        global $USER;


        $costcenterpath = array();


        if(!empty($USER->useraccess['currentroleinfo']['contextinfo'])){

            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){

                $costcenterpath[] = $contextinfo['costcenterpath'];
            }
        }

        return $costcenterpath;
    }
    public static function get_user_role_switch_select_option($url,$paramname='id'){

        global $OUTPUT,$USER,$DB;


        $options = array();


        if(!empty($USER->useraccess['currentroleinfo']['contextinfo'])){

            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){


                $optionkey=end(explode('/',$contextinfo['costcenterpath']));

                $costcenterinfo = $DB->get_field('local_costcenter', 'fullname', array('id' => $optionkey));

                $options[$optionkey] = $costcenterinfo;
            }
        }

        if (count($options) > 1) {


            $datatype = optional_param($paramname, $optionkey, PARAM_INT);

            if (array_key_exists($datatype,$options)){

                $USER->useraccess['currentroleinfo']['roleswitch_selected_option']=$datatype ;
            }

            $cachedatatype =$USER->useraccess['currentroleinfo']['roleswitch_selected_option'];

            $depth = $USER->useraccess['currentroleinfo']['depth'];
            if(count($USER->useraccess['currentroleinfo']['contextinfo']) > 1){
                 $depth--;
            }
            if($depth < 2){
                $rowdatadepth = get_string('organization','local_users');
            } elseif($depth < 3){
                $rowdatadepth = get_string('department','local_users');
            } elseif($depth < 4){
                $rowdatadepth = get_string('commercialunit','local_users');
            } elseif($depth < 5){
                $rowdatadepth = get_string('commercialarea','local_users');
            } elseif($depth < 6){
                $rowdatadepth = get_string('territory','local_users');
            }

            $selectdropdown=$OUTPUT->single_select($url,$paramname, $options,$cachedatatype,null, 'roleswitchfieldform',
                    array('label' => $rowdatadepth . ':'));


            return $selectdropdown;
        }
    }
}


