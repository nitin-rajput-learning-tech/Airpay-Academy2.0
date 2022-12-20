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
 * @subpackage local_users
 */

namespace local_users\lib;

/**
 * get access lib functions
 */
class accesslib extends \local_costcenter\lib\accesslib{


    public static function user_costcenterpath($userid = null) {

        global $DB;

        $endpathvalue=null;

        if($userid != null && $userid > 0){

            $costcenterpath=$DB->get_field('user','open_costcenterpath',  array('id'=> $userid));

            if(!empty($costcenterpath)){

                $extractcostcenterpath=array_filter(explode('/',$costcenterpath));

                $endpathvalue=end($extractcostcenterpath);

            }
        }

        return $endpathvalue;

    }
    public static function get_module_context($userid = null){

        return parent::get_module_context(self::user_costcenterpath($userid));

    }
    public static function get_costcenter_path_field_concatsql($columnname,$userid = null, $datatype = NULL){

        return parent::get_costcenter_path_field_concatsql($columnname, self::user_costcenterpath($userid));

    }
    public static function get_user_geography_fields(){

        $categorycontext = self::get_module_context();

        $targetstateaudience = false;
        $targetdistrictaudience = false;
        $targetsubdistrictaudience = false;
        $targetvillageaudience = false;

        if(is_siteadmin() || has_capability('usersprofilefields/states:targetstateaudience',$categorycontext)){
            $targetstateaudience = true;
        }
        if(is_siteadmin() || has_capability('usersprofilefields/district:targetdistrictaudience',$categorycontext)){
            $targetdistrictaudience = true;
        }
        if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:targetsubdistrictaudience',$categorycontext)){
            $targetsubdistrictaudience = true;
        }
        if(is_siteadmin() || has_capability('usersprofilefields/village:targetvillageaudience',$categorycontext)){
            $targetvillageaudience = true;
        }

        $fields = array(
            'open_states' => $targetstateaudience,
            'open_district' => $targetdistrictaudience,
            'open_subdistrict' => $targetsubdistrictaudience,
            'open_village' => $targetvillageaudience,
        );
        return $fields;
    }
    public static function get_geographical_target_users_concatsql($moduledata){

        global $USER;

        $geographicaltargets=array();

        $concatsql="";

        if(empty($USER->id) || is_siteadmin()){

            return $concatsql;

        }else{

            $fields = self::get_user_geography_fields();

            foreach($fields as $field =>$fieldenabled){

                if($fieldenabled == false){
                    continue;
                }

                if(isset($moduledata->$field) && !empty($moduledata->$field)){


                    if(empty($geographicaltargets[$field])){

                        $items = is_array($moduledata->$field) ? $moduledata->$field : explode(',', $moduledata->$field);
                        $items = array_filter($items);

                        if (is_array($items) and !empty($items)){

                            $geographicaltargets[$field] = ''.$field.' IN ('.implode(',', $items).')';

                        }

                    }
                }
            }
        }

        if(!empty($geographicaltargets)){

            $concatsql="AND (".implode(" OR ", $geographicaltargets).")";
        }

        return $concatsql;


    }
}
