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
 * List the tool provided 
 *
 * @package    local
 * @subpackage course_completions
 * @copyright  2019 eAbyas Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(__FILE__) . '/../config.php');

// global $DB, $USER, $CFG,$COURSE,$PAGE,$OUTPUT;

// require_once($CFG->dirroot . '/completion/cron.php');

//  completion_cron_mark_started();
//  completion_cron_criteria();
//  completion_cron_completions();


$daily_task = new \core\task\completion_daily_task();
$regular_task = new core\task\completion_regular_task();

$daily_task->execute();
$regular_task->execute();







