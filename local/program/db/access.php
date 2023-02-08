<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * program Capabilities
 *
 * program - A Moodle plugin for managing ILT's
 *
 * @package     local_program
 * @author:     Arun Kumar Mukka <arun@eabyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') || die;
$capabilities = array(
    'local/program:createprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:inactiveprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:activeprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:editprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher' => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager' => CAP_ALLOW,
            'user' => CAP_INHERIT,
            'student' => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:deleteprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:manageprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:createsession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:viewsession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:editsession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:deletesession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:managesession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_ALLOW,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:assigntrainer' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:managetrainer' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:addusers' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:removeusers' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:manageusers' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:viewusers' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_ALLOW,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:takesessionattendance' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_ALLOW,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:takemultisessionattendance' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:trainer_viewprogram' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_INHERIT,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:view_allprogramtab' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:view_newprogramtab' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:view_activeprogramtab' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:view_holdprogramtab' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:view_cancelledprogramtab' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:view_completedprogramtab' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:createfeedback' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:viewfeedback' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:editfeedback' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
          'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:deletefeedback' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:managefeedback' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:addcourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:createcourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:viewcourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:editcourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:deletecourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:removecourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:managecourse' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:publish' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:cancel' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:release_hold' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:hold' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:complete' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:manage_owndepartments' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_INHERIT,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:manage_multiorganizations' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'coursecreator' => CAP_INHERIT,
            'teacher'        => CAP_INHERIT,
            'editingteacher' => CAP_INHERIT,
            'manager'          => CAP_INHERIT,
            'user'        => CAP_INHERIT,
            'student'      => CAP_INHERIT,
            'guest' => CAP_INHERIT
        ),
    ),
    'local/program:programcompletion' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:createlevel' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:viewlevel' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:editlevel' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:deletelevel' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:managelevel' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'coursecreator' => CAP_INHERIT,
           'teacher'        => CAP_INHERIT,
           'editingteacher' => CAP_INHERIT,
           'manager'          => CAP_ALLOW,
           'user'        => CAP_INHERIT,
           'student'      => CAP_INHERIT,
           'guest' => CAP_INHERIT
        ),
    ),
    'local/program:enrolsession' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'student'      => CAP_ALLOW,
        ),
    )
);
