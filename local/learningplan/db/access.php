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

defined('MOODLE_INTERNAL') || die;

$capabilities = array(
    'local/learningplan:manage' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:exportplans' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:view' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
       
    ),
    'local/learningplan:visible' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/learningplan:create' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'local/learningplan:delete' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:update' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:publishplan' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:assignhisusers' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:assigncourses' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:assigncourses_ownorganization' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:assigncourses_owndepartment' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:owndepartment_learningplan' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:ownorganization_learningplan' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),
    'local/learningplan:multiorganizations_learningplan' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT
    ),  
);
 