<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or localify
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
 * Plugin capabilities
 *
 * @package    local_evaluation
 * @copyright  Andreas Grabs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    'local/evaluation:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:view' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:complete' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:viewanalysepage' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:deletesubmissions' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:edititems' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:createprivatetemplate' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:createpublictemplate' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:deletetemplate' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:viewreports' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

    'local/evaluation:receivemail' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    
    'local/evaluation:ownevalauations' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    
    'local/evaluation:allevalauations' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:alltempaltes' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:enroll_users' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:delete' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:manage_multiorganizations' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:manage_ownorganization' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:manage_owndepartments' => array(
       'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:evaluationmode' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/evaluation:create_update_question' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),

);


