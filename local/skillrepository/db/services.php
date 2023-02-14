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
 * @subpackage local_skillrepository
 */

// We defined the web service functions to install.

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'local_skillrepository_submit_skill_repository_form_form' => array(
        'classname'   => 'local_skillrepository_external',
        'methodname'  => 'submit_skill_repository_form_form',
        'classpath'   => 'local/skillrepository/classes/external.php',
        'description' => 'Submit form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_skillrepository_form_repository_selector' => array(
        'classname' => 'local_skillrepository_external',
        'methodname' => 'repository_selector',
        'classpath' => 'local/skillrepository/classes/external.php',
        'description' => 'All forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_skillrepository_submit_skill_category_form' => array(
        'classname' => 'local_skillrepository_external',
        'methodname' => 'submit_skill_category',
        'classpath' => 'local/skillrepository/classes/external.php',
        'description' => 'category forms event handling',
        'ajax' => true,
        'type' => 'read',
    ),
    'local_skillrepository_delete_skill' => array(
        'classname'   => 'local_skillrepository_external',
        'methodname'  => 'delete_skill',
        'classpath'   => 'local/skillrepository/classes/external.php',
        'description' => 'deleting of skill',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_skillrepository_submit_level_form' => array(
        'classname'   => 'local_skillrepository_external',
        'methodname'  => 'submit_level_form',
        'classpath'   => 'local/skillrepository/classes/external.php',
        'description' => 'Submit level form',
        'type'        => 'write',
        'ajax' => true,
    ),
    'local_skillrepository_manageskills_view' => array(
        'classname'   => 'local_skillrepository_external',
        'methodname'  => 'manageskillsview',
        'classpath'   => 'local/skillrepository/classes/external.php',
        'description' => 'Display the Skills Page',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_skillrepository_manageskillslevel_view' => array(
        'classname'   => 'local_skillrepository_external',
        'methodname'  => 'manageskillslevelview',
        'classpath'   => 'local/skillrepository/classes/external.php',
        'description' => 'Display the Skills Level Page',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_skillrepository_manageskillscategory_view' => array(
        'classname'   => 'local_skillrepository_external',
        'methodname'  => 'manageskillscategoryview',
        'classpath'   => 'local/skillrepository/classes/external.php',
        'description' => 'Display the Skills Category Page',
        'type'        => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
     ),

    'local_skillrepository_submit_skills_interested_form' => array(
        'classname'     => 'local_skillrepository_external',
        'methodname'    => 'submit_skills_interested_form',
        'classpath'     => 'local/skillrepository/classes/external.php',
        'description'   => 'Submit Skills Interested form',
        'type'          => 'write',
        'ajax'          => true,  
    )
);
