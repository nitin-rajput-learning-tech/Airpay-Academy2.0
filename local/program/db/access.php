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
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:inactiveprogram' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:activeprogram' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:editprogram' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:deleteprogram' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:manageprogram' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:addusers' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:removeusers' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:manageusers' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:viewusers' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:trainer_viewprogram' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:view_allprogramtab' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:view_newprogramtab' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:view_activeprogramtab' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:view_holdprogramtab' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:view_cancelledprogramtab' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:view_completedprogramtab' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:createfeedback' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:viewfeedback' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:editfeedback' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:deletefeedback' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:managefeedback' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:addcourse' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:createcourse' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:viewcourse' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:editcourse' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:deletecourse' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:removecourse' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:managecourse' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:publish' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:cancel' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:release_hold' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:hold' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:complete' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:programcompletion' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:createlevel' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:viewlevel' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:editlevel' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:deletelevel' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:managelevel' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:setlevelcompletioncriteria' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/program:cansetprogramcompletioncriteria' => array(
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
           'manager'      => CAP_ALLOW,
        ),
    ),
);
