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

namespace local_notifications\lib;

/**
 * get access lib functions
 */
class accesslib extends \local_costcenter\lib\accesslib{

    public static function notifications_costcenterpath($notinfoid = null) {

        global $DB;

        $costcenterpath=null;

        if($notinfoid != null && $notinfoid > 0){

            $costcenterpath=$DB->get_field('local_notifications_info','open_path',  array('id'=> $notinfoid));
        }

        return $costcenterpath;

    }
    public static function get_module_context($notinfoid = null){

        return parent::get_module_context(self::notifications_costcenterpath($notinfoid));

    }
    public static function get_costcenter_path_field_concatsql($columnname,$notinfoid = null, $datatype = NULL){

        return parent::get_costcenter_path_field_concatsql($columnname, self::notifications_costcenterpath($notinfoid));

    }
}
