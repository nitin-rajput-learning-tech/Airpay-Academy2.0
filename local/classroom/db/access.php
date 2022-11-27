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
defined('MOODLE_INTERNAL') || die;
$capabilities = array(
    'local/classroom:createclassroom' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:editclassroom' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:deleteclassroom' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:manageclassroom' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:createsession' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    //'local/classroom:viewsession' => array(
    //   'riskbitmask' => RISK_SPAM | RISK_XSS,
    //   'captype' => 'write',
    //    'contextlevel' => CONTEXT_COURSECAT,
    //    'archetypes' => array(
    //       'coursecreator' => CAP_PREVENT,
    //       'teacher'        => CAP_PREVENT,
    //       'editingteacher' => CAP_PREVENT,
    //       'manager'          => CAP_ALLOW,
    //       'user'        => CAP_PREVENT,
    //       'student'      => CAP_PREVENT,
    //       'guest' => CAP_PREVENT
    //    ),
    //),
    'local/classroom:editsession' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:deletesession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:managesession' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:assigntrainer' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:managetrainer' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:addusers' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:removeusers' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:manageusers' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:viewusers' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_ALLOW,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:takesessionattendance' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_ALLOW,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:takemultisessionattendance' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:trainer_viewclassroom' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
            'coursecreator' => CAP_PREVENT,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_PREVENT,
            'manager'          => CAP_PREVENT,
            'user'        => CAP_PREVENT,
            'student'      => CAP_PREVENT,
            'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:view_allclassroomtab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:view_newclassroomtab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:view_activeclassroomtab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:view_holdclassroomtab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:view_cancelledclassroomtab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:view_completedclassroomtab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:createfeedback' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    //'local/classroom:viewfeedback' => array(
    //   'riskbitmask' => RISK_SPAM | RISK_XSS,
    //   'captype' => 'write',
    //    'contextlevel' => CONTEXT_COURSECAT,
    //    'archetypes' => array(
    //       'coursecreator' => CAP_PREVENT,
    //       'teacher'        => CAP_PREVENT,
    //       'editingteacher' => CAP_PREVENT,
    //       'manager'          => CAP_ALLOW,
    //       'user'        => CAP_PREVENT,
    //       'student'      => CAP_PREVENT,
    //       'guest' => CAP_PREVENT
    //    ),
    //),
    'local/classroom:editfeedback' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
          'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:deletefeedback' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:managefeedback' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:createcourse' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    //'local/classroom:viewcourse' => array(
    //   'riskbitmask' => RISK_SPAM | RISK_XSS,
    //   'captype' => 'write',
    //    'contextlevel' => CONTEXT_COURSECAT,
    //    'archetypes' => array(
    //       'coursecreator' => CAP_PREVENT,
    //       'teacher'        => CAP_PREVENT,
    //       'editingteacher' => CAP_PREVENT,
    //       'manager'          => CAP_ALLOW,
    //       'user'        => CAP_PREVENT,
    //       'student'      => CAP_PREVENT,
    //       'guest' => CAP_PREVENT
    //    ),
    //),
    'local/classroom:editcourse' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:deletecourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:managecourse' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:publish' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:cancel' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:release_hold' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:hold' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:complete' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:manage_owndepartments' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
            'coursecreator' => CAP_PREVENT,
            'teacher'        => CAP_PREVENT,
            'editingteacher' => CAP_PREVENT,
            'manager'          => CAP_PREVENT,
            'user'        => CAP_PREVENT,
            'student'      => CAP_PREVENT,
            'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:manage_multiorganizations' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
            'coursecreator' => CAP_PREVENT,
            'teacher'        => CAP_PREVENT,
            'editingteacher' => CAP_PREVENT,
            'manager'          => CAP_PREVENT,
            'user'        => CAP_PREVENT,
            'student'      => CAP_PREVENT,
            'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:classroomcompletion' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
    'local/classroom:viewwaitinglist_userstab' => array(
       'riskbitmask' => RISK_SPAM | RISK_XSS,
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes' => array(
           'coursecreator' => CAP_PREVENT,
           'teacher'        => CAP_PREVENT,
           'editingteacher' => CAP_PREVENT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_PREVENT,
           'student'      => CAP_PREVENT,
           'guest' => CAP_PREVENT
        ),
    ),
);
