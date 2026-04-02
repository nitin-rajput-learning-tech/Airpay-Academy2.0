<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
 $capabilities = array(
 
    'block/trainerdashboard:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'clonepermissionsfrom' => 'moodle/my:manageblocks'
    ),
 
    'block/trainerdashboard:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/trainerdashboard:viewtrainerslist' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'block/trainerdashboard:viewconductedtrainings' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    'block/trainerdashboard:viewtrainermanhours' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    ),
    // 'block/trainerdashboard:viewdepttrainingavg' => array(
    //     'captype' => 'read',
    //     'contextlevel' => CONTEXT_COURSECAT,
    // ),
    'block/trainerdashboard:viewupcomingtrainings' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSECAT,
    )
);