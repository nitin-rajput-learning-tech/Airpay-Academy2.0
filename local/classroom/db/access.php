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
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:editsession' => array(
      'captype' => 'write',
      'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:deletesession' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:managesession' => array(
      'captype' => 'write',
      'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:assigntrainer' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:managetrainer' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:addusers' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:removeusers' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:manageusers' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:viewusers' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:takesessionattendance' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:takemultisessionattendance' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:trainer_viewclassroom' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:view_allclassroomtab' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:view_newclassroomtab' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:view_activeclassroomtab' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:view_holdclassroomtab' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:view_cancelledclassroomtab' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:view_completedclassroomtab' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:createfeedback' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:editfeedback' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:deletefeedback' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:managefeedback' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:createcourse' => array(
       'captype' => 'write',
       'contextlevel' => CONTEXT_COURSECAT,

    ),
    'local/classroom:editcourse' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:deletecourse' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:managecourse' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:publish' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:cancel' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:release_hold' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:hold' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:complete' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:manage_owndepartments' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:manage_multiorganizations' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:classroomcompletion' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/classroom:viewwaitinglist_userstab' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
);
