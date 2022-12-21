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
    public static function get_user_geography_fields($requiredfields=null){

        $geographyfields = array(
            'open_states',
            'open_district',
            'open_subdistrict',
            'open_village',
        );

        if($requiredfields !=null ){

            $fields = is_array($requiredfields) ? $requiredfields : array($requiredfields);
            $fields = array_filter($fields);

            if (is_array($fields) and !empty($fields)){

                $geographyflipfields=array_flip($geographyfields);

                foreach($fields as $field){

                   if(!isset($geographyflipfields[$field])){

                        unset($fields[$field]);

                   }

                }

                $geographyfields =$fields;
            }

        }

        return $geographyfields;
    }
    public static function get_geographical_target_users_concatsql($moduledata){

        global $USER;

        $geographicaltargets=array();

        $concatsql="";

        if(empty($USER->id) || is_siteadmin()){

            return $concatsql;

        }else{

            $fields = self::get_user_geography_fields();

            foreach($fields as $field){

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
