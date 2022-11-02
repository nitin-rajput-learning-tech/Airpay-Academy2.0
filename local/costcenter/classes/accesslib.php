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

namespace local_costcenter;

use dml_exception;

/**
 * get access lib functions
 */
class accesslib
{
    public static function get_module_context($costcenterid = null,$userid = null){

        global $DB;

        if(is_siteadmin()) {

           $context = \context_system::instance();

        }else{

            if($costcenterid){

                $sql = "SELECT cc.category FROM {local_costcenter} AS cc 
                         WHERE cc.id= :costcenterid ";

            
                try{

                    $categoryid = $DB->get_field_sql($sql, array('costcenterid' => $costcenterid));   
                }

                catch(dml_exception $e){

                    print_r($e->debuginfo);

                }

            }elseif($userid){

                $sql = "SELECT cc.category FROM {local_costcenter} AS cc 
                        JOIN {user} AS u on cc.id = u.open_costcenterid 
                         WHERE u.id= :userid ";

            
                 try{

                    $categoryid = $DB->get_field_sql($sql, array('userid' => $userid));   

                    $context = \context_coursecat::instance($categoryid);
                }

                catch(dml_exception $e){

                    print_r($e->debuginfo);

                }

            }else{

                $context = \context_system::instance();
            }
        } 
            
        return $context;

    }
}
