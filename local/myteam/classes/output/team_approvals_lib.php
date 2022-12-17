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
 */

namespace local_myteam\output;

defined('MOODLE_INTERNAL') || die;
class team_approvals_lib{

    public function get_team_approval_requests($learningtype = 'elearning', $search = false){
        global $DB, $USER, $OUTPUT;

        if(empty($learningtype)){
        	return false;
        }
        $params = array();
        if($search){
            $condition = " AND CONCAT(u.firstname, ' ', u.lastname) LIKE '%".$search."%' ";
            $params['fullname'] = '%'.$search.'%';
        } else {
            $condition = "";
        }
        $departmentmyteamsql = "SELECT lrr.id, lrr.createdbyid, lrr.compname, lrr.componentid, lrr.status, lrr.responder, lrr.respondeddate,
        							u.firstname, u.lastname, u.email, u.idnumber
        							FROM {local_request_records} as lrr
        							JOIN {user} as u ON u.id = lrr.createdbyid
                                    WHERE lrr.compname = :learningtype AND u.open_supervisorid = :loggedinuserid AND u.id != :userid
                                    AND u.confirmed = :confirmed AND u.suspended = :suspended AND u.deleted = :deleted AND u.id > 2".$condition." ORDER BY lrr.timemodified DESC";
        $params['learningtype'] = $learningtype;
        $params['loggedinuserid'] = $USER->id;
        $params['userid'] = $USER->id;
        $params['confirmed'] = 1;
        $params['suspended'] = 0;
        $params['deleted'] = 0;
        $departmentmyteam = $DB->get_records_sql($departmentmyteamsql,$params);
        return $departmentmyteam;
	}
}