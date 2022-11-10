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
 * @subpackage  ratings
 * @author eabyas  <info@eabyas.in>
**/
defined('MOODLE_INTERNAL') || die;
$functions = array(
	'local_ratings_get_ratings_info' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'get_specific_rating_info',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'specific rating info',
        'type'        => 'read',
        'ajax' => true,
    ),
    'local_ratings_save_comment' => array(
    	'classname'   => 'local_ratings_external',
        'methodname'  => 'save_comment',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'save comment',
        'type'        => 'write',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    ),
    'local_ratings_display_ratings_content' => array(
    	'classname'   => 'local_ratings_external',
        'methodname'  => 'display_ratings_content',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Display Ratings Content',
        'type'        => 'Read',
        'ajax' => true,
    ),
    'local_ratings_set_module_rating' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'set_module_rating',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Set Ratings to Modules',
        'type'        => 'Write',
        'ajax' => true,
    ),
    'local_ratings_like_dislike' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'like_dislike',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Like/Dislike',
        'type'        => 'read/write',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    ),
    'local_ratings_get_likedislike' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'get_likedislike',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Get Likes/Dislikes',
        'type'        => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    ),
    'local_ratings_submit_rating' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'submit_rating',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Submit Rating',
        'type'        => 'write',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    ),
    'local_ratings_get_ratings' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'get_ratings',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Get Ratings',
        'type'        => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    ),
    'local_ratings_get_reviews' => array(
        'classname'   => 'local_ratings_external',
        'methodname'  => 'get_reviews',
        'classpath'   => 'local/ratings/classes/external.php',
        'description' => 'Get Reviews',
        'type'        => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax' => true,
    )
);