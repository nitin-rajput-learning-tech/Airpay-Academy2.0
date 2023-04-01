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
 * @subpackage block_learnerscript
 */
use block_learnerscript\local\ls;
use block_learnerscript\local\pluginbase;
use block_learnerscript\local\querylib;

class plugin_sitelevelroles extends pluginbase {

    public function init() {
        $this->form = true;
        $this->unique = false;
        $this->fullname = get_string('sitelevelroles', 'block_learnerscript');
        $this->reporttypes = array('sql', 'coursesoverview','myclassrooms','mycoursess','mylearningplan','myonlinetests','myprograms','mycertification','coursescompletions','certificatecompletions','certificatesoverview','classroom_completions','classroomsoverview','feedbackcompletions','feedbackoverview','learningplancomletions','learningplansoverview','onlinetestscompletions','onlinetestsoverview','programcompletions','programsoverview','skill','coursegradeactivities','orgusers','userdata', 'users', 'statistics', 'courses','bigbluebutton','coursecompetency','quizzes','assignment','scorm','resources','usercourses','courseprofile','gradedactivity', 'userprofile', 'learnercoursesoverview', 'courseactivities', 'userbadges', 'courseviews', 'noofviews','myscorm','myforums','learners', 'onlinecourses', 'labs', 'assessments', 'webinars', 'classroom', 'exam', 'examenrolments', 'graphexamenrolments', 'graphexamcompletions', 'graphlearning', 'graphlearnercompletions', 'graphlearnerenrolments', 'learning', 'learners', 'learningpaths', 'learnerexamoverview','learnerexamsummary','examlearneroverview','examlearnersummery', 'certificationlearneroverview', 'certificationlearnersummary', 'learnercertificationsoverview', 'learnercertificationssummary', 'certifications', 'certificationsummary', 'programanalysis', 'programs', 'learnerstatus', 'compliancecertificationuserslist', 'compliancecourseuserslist','acclaimusers','orgclassrooms','traininghoursvsusers','trainingsprogress','trainerslist','trainingsoverview','classroomusers','classroomsoverview','userparticipation','dailyuniquelogins','usersdata','departmentoverview','usercourseoverview','feedbackcourses','classroomsessionattendence','userwisecourseoverview','programlevelcompletions','courseparticipation','trainermanhours');
    }

    public function summary($data) {
        global $DB;

        $rolename = $DB->get_field('role', 'shortname', array('id' => $data->roleid));

        return $rolename;
    }

    public function execute($userid, $context, $data) {
        global $CFG, $DB, $USER;
        
        if(empty($USER->useraccess['currentroleinfo']['roleid'])){
            $userroleid = $DB->get_field('role','id', array('shortname'=>'user'));
        }else{
            $userroleid = $USER->useraccess['currentroleinfo']['roleid'];
        }
        if($data->roleid == $userroleid || is_siteadmin()){
            return true;
        }

        // $context = context_system::instance();
        // $userroles = get_user_roles($context, $userid);
        // $authuser = $DB->get_record('role',array('shortname'=>'user'));
        // $authuser->roleid = $authuser->id;
        // $authuserrole = array($authuser->id => $authuser);
        // $userroles = $userroles + $authuserrole;
        // foreach ($userroles as $userrole) {
        //     if ($userrole->roleid == $data->roleid){
        //         return true;
        //     }
        // }
        return false;
    }
}
