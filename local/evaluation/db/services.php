<?php

/*
 * This program is free software; you can redistribute it and/or localify
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
 * @package local
 * @subpackage evaluation
 * @author Sreenivas <sreenivasula@eabyas.in>
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
        'local_evaluation_submit_create_evaluation_form' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'submit_create_evaluation_form',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'Submit form',
                'type'        => 'write',
                'ajax' => true,
        ),
        'local_evaluation_evaluationview' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'evaluationview',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'Submit form',
                'type'        => 'read',
                'ajax' => true,
        ),
        'local_evaluation_displayquestion' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'displayquestion',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'displays question form',
                'type'        => 'read',
                'ajax' => true,
        ),
        'local_evaluation_displaytemplate' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'displaytemplate',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'displays template form',
                'type'        => 'read',
                'ajax' => true,
        ),
        'local_evaluation_addnew_question' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'addnew_question',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'Creates New Question',
                'type'        => 'write',
                'ajax' => true,

        ),
        'local_evaluation_evaluation_update_status' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'evaluation_update_status',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'Updates the status of survey',
                'type'        => 'write',
                'ajax' => true,

        ),
        'local_evaluation_evaluations_by_status' => array(
                'classname'   => 'local_evaluation_external',
                'methodname'  => 'evaluations_by_status',
                'classpath'   => 'local/evaluation/classes/external.php',
                'description' => 'List of Evaluations by status',
                'type'        => 'read',
                'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
        ),
        'local_evaluation_get_evaluations' => array(
                'classname'     => 'local_evaluation_external',
                'methodname'    => 'get_evaluations',
                'description'   => 'Returns a list of evaluations in a provided list, if no list is provided all evaluations that
                                    the user can view will be returned.',
                'type'          => 'read',
                'capabilities'  => 'local/evaluation:view',
                'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        ),
    'local_evaluation_get_evaluation_access_information' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_evaluation_access_information',
        'description'   => 'Return access information for a given feedback.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_evaluation_view_evaluation' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'view_evaluation',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_current_completed_tmp' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_current_completed_tmp',
        'description'   => 'Returns the temporary completion record for the current user.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_items' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_items',
        'description'   => 'Returns the items (questions) in the given feedback.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_launch_evaluation' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'launch_evaluation',
        'description'   => 'Starts or continues a feedback submission.',
        'type'          => 'write',
        'capabilities'  => 'local/evaluation:complete',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_page_items' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_page_items',
        'description'   => 'Get a single feedback page items.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:complete',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_process_page' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'process_page',
        'description'   => 'Process a jump between pages.',
        'type'          => 'write',
        'capabilities'  => 'local/evaluation:complete',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_analysis' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_analysis',
        'description'   => 'Retrieves the feedback analysis.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:viewanalysepage',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_unfinished_responses' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_unfinished_responses',
        'description'   => 'Retrieves responses from the current unfinished attempt.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_finished_responses' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_finished_responses',
        'description'   => 'Retrieves responses from the last finished attempt.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_non_respondents' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_non_respondents',
        'description'   => 'Retrieves a list of students who didn\'t submit the feedback.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:viewreports',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_responses_analysis' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_responses_analysis',
        'description'   => 'Return the feedback user responses analysis.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:viewreports',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_get_last_completed' => array(
        'classname'     => 'local_evaluation_external',
        'methodname'    => 'get_last_completed',
        'description'   => 'Retrieves the last completion record for the current user.',
        'type'          => 'read',
        'capabilities'  => 'local/evaluation:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'local_evaluation_userdashboard_content' => array(
        'classname'   => 'local_evaluation_external',
        'methodname'  => 'data_for_evaluations',
        'classpath'   => 'local/evaluation/classes/external.php',
        'description' => 'Get user feedbacks for dashboard',
        'type'        => 'read',
        'ajax' => true,
    ),
    'local_evaluation_userdashboard_content_paginated' => array(
        'classname'   => 'local_evaluation_external',
        'methodname'  => 'data_for_evaluations_paginated',
        'classpath'   => 'local/evaluation/classes/external.php',
        'description' => 'Get user feedbacks for dashboard',
        'type'        => 'read',
        'ajax' => true,
    )
);

