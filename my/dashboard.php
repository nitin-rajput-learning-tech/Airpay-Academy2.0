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
 * Lists the course categories
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package course
 */

require_once("../config.php");
require_once($CFG->dirroot. '/course/lib.php');
$orgid  = optional_param('orgid', 0, PARAM_INT);
//echo $orgid;exit;
$context =(new \local_costcenter\lib\accesslib())::get_module_context();
$site = get_site();
if ($CFG->forcelogin) {
    require_login();
}

$heading = $site->fullname;
if ($context->id != CONTEXT_SYSTEM && !is_siteadmin($USER)) {
    if($orgid==0)
    {
    $categoryid=$DB->get_field('context', 'instanceid', array('id' => $context->id));
    }
    else
    {
        $categoryid=$orgid;
    }
    $category = core_course_category::get($categoryid); // This will validate access.
    $PAGE->set_category_by_id($categoryid);
    $PAGE->set_url(new moodle_url('/my/dashboard.php', array('orgid' => $categoryid)));
    //$PAGE->set_pagetype('course-index-category');
    $heading = $category->get_formatted_name();
} else if ($category = core_course_category::user_top()) {
    // Check if there is only one top-level category, if so use that.
    if($orgid==0)
    {
        $categoryid = $category->id;
    }
    else
    {
        $categoryid=$orgid;
    }
    
    $PAGE->set_url('/my/dashboard.php');

    if ($category->is_uservisible() && $categoryid) {
        
        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_context($category->get_context());
        if (!core_course_category::is_simple_site()) {
            $PAGE->set_url(new moodle_url('/my/dashboard.php', array('orgid' => $categoryid)));
            $heading = $category->get_formatted_name();
        }
    } else {
      
        $PAGE->set_context(context_system::instance());
    }
    $PAGE->set_pagetype('course-index-category');
} else {
    throw new moodle_exception('cannotviewcategory');
}

//$PAGE->set_pagelayout('coursecategory');
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_primary_active_tab('home');
$PAGE->add_body_class('limitedwidth');
$courserenderer = $PAGE->get_renderer('core', 'course');
$PAGE->set_heading($heading);
$content = $courserenderer->course_category($categoryid);
//$PAGE->set_secondary_active_tab('categorymain');
echo $OUTPUT->header(); 
echo $OUTPUT->skip_link_target();
echo $content;
// Trigger event, course category viewed.
$eventparams = array('context' => $PAGE->context, 'objectid' => $categoryid);
$event = \core\event\course_category_viewed::create($eventparams);
$event->trigger();

 
echo $OUTPUT->footer();
