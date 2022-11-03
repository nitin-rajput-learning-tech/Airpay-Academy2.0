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
    public static function get_module_context($costcenterid = null,$moduleid=null,$moduletype=null){

        global $DB,$USER;

    
        if($costcenterid = null){

            $costcenterid=$USER->open_costcenterid ? $USER->open_costcenterid : 0;

            $cachekey = "{$userid}_userid_costcenter_context";

        }else{

            $cachekey = "{$moduleid}_{$moduletype}_costcenter_context";

        }

        if(!is_siteadmin() && $costcenterid){

             try{

                // Get a cache instance
                $cache = \cache::make('local_costcenter','costcentercontextdata');

                // Get all of the roles used in this context, including special roles such as user, and frontpageuser.

                $context = $cache->get($cachekey);

                if ($context === false) {

                    $sql = "SELECT cc.category FROM {local_costcenter} AS cc WHERE cc.id= :costcenterid ";

                    $categoryid = $DB->get_field_sql($sql, array('costcenterid' => $costcenterid));   

                    $context = \context_coursecat::instance($categoryid);

                    $cache->set($cachekey, $context);
                }
            }

            catch(dml_exception $e){

                print_r($e->debuginfo);

            }

        }else{

            $context = \context_system::instance();
        }
        
            
        return $context;

    }
}
