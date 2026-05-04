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
 * Language strings — Airpay Course Engine.
 *
 * @package    local_airpay_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Course Engine';

// Capabilities.
$string['airpay_courses:manage'] = 'Manage courses';
$string['airpay_courses:enrol'] = 'Enrol users into courses';
$string['airpay_courses:view'] = 'View course management';
$string['airpay_courses:create'] = 'Create courses';
$string['airpay_courses:update'] = 'Edit courses';
$string['airpay_courses:delete'] = 'Delete courses';
$string['airpay_courses:visibility'] = 'Show or hide courses';

// CRUD form strings.
$string['addcourse'] = 'Add Course';
$string['editcourse'] = 'Edit Course';
$string['deletecourse'] = 'Delete Course';
$string['hidecourse'] = 'Hide Course';
$string['showcourse'] = 'Show Course';

// Form section headings.
$string['heading_basic'] = 'Basic Information';
$string['heading_category'] = 'Category & Organisation';
$string['heading_summary'] = 'Description';
$string['heading_format'] = 'Format & Visibility';

// Form field labels.
$string['fullname'] = 'Course full name';
$string['shortname'] = 'Course short name';
$string['shortname_help'] = 'Unique short identifier — used in URLs and reports.';
$string['idnumber'] = 'Course ID number';
$string['category'] = 'Course category';
$string['organisation'] = 'Organisation (tenant)';
$string['summary'] = 'Course description';
$string['courseformat'] = 'Course format';
$string['format_topics'] = 'Topics format';
$string['format_weeks'] = 'Weekly format';
$string['format_single'] = 'Single activity';
$string['format_social'] = 'Social format';
$string['numsections'] = 'Number of sections';
$string['visibility'] = 'Visible to learners';
$string['startdate'] = 'Start date';
$string['enddate'] = 'End date';

// Error messages.
$string['missingrequiredfields'] = 'Please fill in all required fields.';
$string['shortnametaken'] = 'This short name is already in use. Please choose another.';
$string['enddatebeforestart'] = 'End date must be after start date.';
$string['cannotdeletesitecourse'] = 'The site course cannot be deleted.';
$string['confirmdelete'] = 'Are you sure you want to delete "{$a}"? This will permanently remove the course and all enrolments, activities, and grades. This cannot be undone.';
$string['confirmhide'] = 'Are you sure you want to hide "{$a}"? Learners will no longer see this course.';
$string['confirmshow'] = 'Are you sure you want to make "{$a}" visible to learners?';

// Success messages.
$string['coursecreated'] = 'Course created successfully.';
$string['courseupdated'] = 'Course updated successfully.';
$string['coursedeleted'] = 'Course deleted.';
$string['coursehidden'] = 'Course hidden.';
$string['courseshown'] = 'Course visible.';
