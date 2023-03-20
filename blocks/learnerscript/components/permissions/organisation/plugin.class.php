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

class plugin_organisation extends pluginbase {

    public function init() {
        $this->form = true;
        $this->unique = false;
        $this->fullname = get_string('organisation', 'local_costcenter');
        $this->reporttypes = array('sql', 'coursesoverview','myclassrooms','mycoursess','mylearningplan','myonlinetests','myprograms','mycertification','coursescompletions','certificatecompletions','certificatesoverview','classroom_completions','classroomsoverview','feedbackcompletions','feedbackoverview','learningplancomletions','learningplansoverview','onlinetestscompletions','onlinetestsoverview','programcompletions','programsoverview','skill','coursegradeactivities','orgusers','userdata', 'users', 'statistics', 'courses','bigbluebutton','coursecompetency','quizzes','assignment','scorm','resources','usercourses','courseprofile','gradedactivity', 'userprofile', 'learnercoursesoverview', 'courseactivities', 'userbadges', 'courseviews', 'noofviews','myscorm','myforums','learners', 'onlinecourses', 'labs', 'assessments', 'webinars', 'classroom', 'exam', 'examenrolments', 'graphexamenrolments', 'graphexamcompletions', 'graphlearning', 'graphlearnercompletions', 'graphlearnerenrolments', 'learning', 'learners', 'learningpaths', 'learnerexamoverview','learnerexamsummary','examlearneroverview','examlearnersummery', 'certificationlearneroverview', 'certificationlearnersummary', 'learnercertificationsoverview', 'learnercertificationssummary', 'certifications', 'certificationsummary', 'programanalysis', 'programs', 'learnerstatus', 'compliancecertificationuserslist', 'compliancecourseuserslist','acclaimusers','orgclassrooms','traininghoursvsusers','trainingsprogress','trainerslist','trainingsoverview','classroomusers','classroomsoverview','userparticipation','dailyuniquelogins','usersdata','departmentoverview','usercourseoverview','feedbackcourses','classroomsessionattendence','userwisecourseoverview','organizationoverview','courseparticipation');
    }

    public function summary($data) {
        global $DB;
        $orgname = $DB->get_field('local_costcenter', 'fullname', array('id' => $data->organisationid));
        return $orgname;
    }

    public function execute($userid, $context, $data) {
        global $CFG, $DB, $USER;
        
        $organisationids = [];
        if(!empty($USER->useraccess['currentroleinfo']['contextinfo'])){
            foreach($USER->useraccess['currentroleinfo']['contextinfo'] as $contextinfo){
                $pathinfo = explode('/', $contextinfo['costcenterpath']);
                if(isset($pathinfo[1])){//Costecenter id is stored with index 1.
                    $organisationids[] = $pathinfo[1];
                }
            }
        } else {
            $user = $userid ? $userid : $USER->id;
            $assignedroles = \local_costcenter\lib\accesslib::get_user_roles_in_catgeorycontexts($user);
            if($assignedroles){
                foreach($assignedroles as $role){
                    $path = $DB->get_field('local_costcenter', 'path', array('category' => $role->categoryid));
                    if($path){
                        $pathinfo = explode('/', $path);
                            if(isset($pathinfo[1])){//Costecenter id is stored with index 1.
                                $organisationids[] = $pathinfo[1];
                            }
                    }
                }
            } else {
                $path = $DB->get_field('user', 'open_path', array('id' => $user));
                if($path){
                    $pathinfo = explode('/', $path);
                        if(isset($pathinfo[1])){//Costecenter id is stored with index 1.
                            $organisationids[] = $pathinfo[1];
                        }
                }
            }
        }
        if(in_array($data->organisationid, $organisationids)){
            return true;
        }else{
            return false;
        }

    }
}
