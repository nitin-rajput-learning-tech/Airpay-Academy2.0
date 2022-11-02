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
 * @subpackage local_notifications
 */

namespace local_notifications\local;
defined('MOODLE_INTERNAL') || die;

use stdClass;
use moodle_exception;

/**
 * Notification master definition class
 */
class notification_master
{
   
     public function getSenderDetails($sender_id) {
        global $DB;
    
        $result = $DB->get_record_sql('SELECT *  FROM {user} where id=?',array($sender_id));
        $fullname=$result->firstname." ".$result->lastname;
    
        return $fullname;

    } 
    public function getReceiverDetails($receiver_id) {
        global $DB;

        $result = $DB->get_record_sql('SELECT *  FROM {user} where id=?',array($receiver_id));
        $fullname=$result->firstname." ".$result->lastname;
        return $fullname;

    } 


    public function getNotificationType($notification_id) {
        global $DB;

        $result = $DB->get_record_sql('SELECT *  FROM {local_notification_type} where id=?',array($notification_id));

       
        return $result;

    }

    public function getOrganizationDetails($costcenterid) {
        global $DB;

        $result = $DB->get_record_sql('SELECT *  FROM {local_costcenter} where id=?',array($costcenterid));
  
        return $result;

    }

    public function getALLOrganizationDetails() {
        global $DB;

        $result = $DB->get_records_sql('SELECT id,fullname  FROM {local_costcenter} WHERE parentid=0 AND visible=1');

        return $result;

    }


    public function getNotificationInfoById($id) {
        global $DB;

        $result = $DB->get_record_sql('SELECT *  FROM {local_emaillogs} where id=?',array($id));
      
        return $result;

    } 
}