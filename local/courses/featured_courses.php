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
 * @subpackage local_courses
 */

global $PAGE,$CFG,$OUTPUT,$DB;
require_once(dirname(__FILE__) . '/../../config.php');

$featured_course = optional_param('id', 0, PARAM_INT);
$featured = optional_param('featured', 0, PARAM_INT);

$PAGE->requires->js('/local/costcenter/js/custom.js');

if($featured==1 && $featured_course){
	
	$custom = new local_courses\action\update();
	$update = $custom->featured_course($featured_course,$featured);
	
  	$requestid->id=$featured_course;
	$featured_value = 0;
		
	$html = html_writer::tag('a',
					html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('colored', 'local_costcenter'),
					'title' => get_string('featuredcourses', 'local_courses'), 'alt' => get_string('featuredcourses', 'local_courses'), 'onClick' => 'featuredcourses(' . $requestid->id . ','.$featured_value.')', 'class'=>'myFunction','style'=>'width:18px;height:18px;padding: 0px;')),
					array('href' => 'javascript:void(0)','featured_id' => $requestid->id, 'featured' =>$featured_value, 'sesskey' => sesskey() ));
			echo $html;
}elseif($featured==0 && $featured_course){
	$custom = new local_courses\action\update();
	$update = $custom->featured_course($featured_course,$featured);
  	$requestid->id=$featured_course;
	$featured_value = 1;
	$html = html_writer::tag('a',
				html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('coloredIcon', 'local_costcenter'),
				'title' => get_string('featuredcourses', 'local_courses'), 'alt' => get_string('featuredcourses', 'local_courses'),'onClick' => 'featuredcourses(' . $requestid->id . ','.$featured_value.')', 'class'=>'myFunction','style'=>'width:18px;height:18px;padding: 0px;')),
								array('href' => 'javascript:void(0)','featured_id' => $requestid->id, 'featured' =>$featured_value, 'sesskey' => sesskey() ));
	echo $html;
}
