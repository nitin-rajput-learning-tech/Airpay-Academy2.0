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
 * @package   local
 * @subpackage  users
 * @author eabyas  <info@eabyas.in>
**/

defined('MOODLE_INTERNAL') || die;
$functions = array(
    'block_trending_modules_display_content' => array(
        'classname'   => 'block_trending_modules_external',
        'methodname'  => 'display_module_content',
        'classpath'   => 'blocks/trending_modules/classes/external.php',
        'description' => 'Display the module',
        'type'        => 'read',
        'ajax' => true,
    ),
    'block_trending_modules_display_paginated' => array(
        'classname'   => 'block_trending_modules_external',
        'methodname'  => 'display_module_paginated',
        'classpath'   => 'blocks/trending_modules/classes/external.php',
        'description' => 'Display the module paginated',
        'type'        => 'read',
        'ajax' => true,
    ),
    'block_trending_modules_alter_popup_status' => array(
        'classname'   => 'block_trending_modules_external',
        'methodname'  => 'alter_popup_status',
        'classpath'   => 'blocks/trending_modules/classes/external.php',
        'description' => 'Alter popup show hide',
        'type'        => 'read',
        'ajax' => true,
    ),
    'block_trending_modules_get_modules' => array(
        'classname'   => 'block_trending_modules_external',
        'methodname'  => 'get_trending_modules',
        'classpath'   => 'blocks/trending_modules/classes/external.php',
        'description' => 'Get Trending Modules for Mobile',
        'type'        => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    ),
);
