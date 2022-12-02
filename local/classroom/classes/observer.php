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
 * @package Bizlms 
 * @subpackage local_classroom
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_classroom.
 */
class local_classroom_observer {

    /**
     * Triggered via response_deleted event.
     *
     * @param \local_evaluation\event\response_deleted $event
     */
    public static function response_deleted(\local_evaluation\event\response_deleted $event) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot.'/local/classroom/lib.php');
        //print_object($event);exit;
        $sql = "SELECT id,evaluationtype,instance,plugin  FROM {local_evaluations} WHERE id =:id ";
                    $params = array();
                    $params['id'] =  $event->objectid;
         
        $feedback = $DB->get_record_sql($sql, $params);
        if($feedback&&$feedback->plugin=="classroom"&&$feedback->instance>0){
            $pluginevaluationtypes = classroom_evaluationtypes();
            switch($pluginevaluationtypes[$feedback->evaluationtype]) {
                case 'Trainer feedback':
                    
                     $local_classroom_trainers=$DB->get_record_sql("SELECT id,trainerid
                                                        FROM {local_classroom_trainers}
                                                        WHERE classroomid = $feedback->instance AND feedback_id=$feedback->id");
                    
                     $return = $DB->delete_records('local_classroom_trainerfb', array('clrm_trainer_id'=>$local_classroom_trainers->id,'userid'=>$event->relateduserid));
                break;
            
                case 'Training feedback':
                       $params = array('classroomid' => $feedback->instance,'userid'=>$event->relateduserid,
                                 'timemodified' => time(),'usermodified' => $USER->id,'trainingfeedback'=>0);
 
                       $sql='UPDATE {local_classroom_users} SET
                           trainingfeedback = :trainingfeedback, timemodified = :timemodified,
                           usermodified = :usermodified WHERE classroomid = :classroomid AND userid=:userid';
                       $return = $DB->execute($sql, $params);
                break;
            }
        }
    
    }
}
