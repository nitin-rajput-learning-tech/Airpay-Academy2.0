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

        $costcenterpath=null;

        if($userid != null && $userid > 0){

            $costcenterpath=$DB->get_field('user','open_path',  array('id'=> $userid));
        }

        return $costcenterpath;

    }
    public static function get_module_context($userid = null){

        return parent::get_module_context(self::user_costcenterpath($userid));

    }
    public static function get_costcenter_path_field_concatsql($columnname,$userid = null, $datatype = self::PATH_MODULE_CONTENT){

        return parent::get_costcenter_path_field_concatsql($columnname, self::user_costcenterpath($userid), $datatype);

    }
    public static function get_userprofile_fields($requiredfields=null){

        $userprofilefields = array(
            'open_states',
            'open_district',
            'open_subdistrict',
            'open_village',
        );

        if($requiredfields !=null ){

            $fields = is_array($requiredfields) ? $requiredfields : array($requiredfields);
            $fields = array_filter($fields);

            if (is_array($fields) and !empty($fields)){

                $userprofileflipfields=array_flip($userprofilefields);

                $matchfields=array();

                foreach($fields as $field){

                   if(isset($userprofileflipfields[$field])){

                        $matchfields[]=$field;

                   }
                }
                $userprofilefields =$matchfields;
            }

        }

        return $userprofilefields;
    }
    public static function get_userprofilematch_concatsql($moduledata){

        $userprofilefields=array();

        $concatsql="";

        if(is_siteadmin()){

            return $concatsql;

        }else{

            $fields = self::get_userprofile_fields();

            foreach($fields as $field){

                if(isset($moduledata->$field) && !empty($moduledata->$field)){


                    if(empty($userprofilefields[$field])){

                        $items = is_array($moduledata->$field) ? $moduledata->$field : explode(',', $moduledata->$field);
                        $items = array_filter($items);

                        if (is_array($items) and !empty($items)){

                            $userprofilefields[$field] = ''.$field.' IN ('.implode(',', $items).')';

                        }
                    }
                }
            }
        }

        if(!empty($userprofilefields)){

            $concatsql="AND (".implode(" OR ", $userprofilefields).")";
        }

        return $concatsql;


    }
}
