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
 * 
 *
 * @package    block_achievements
 * @copyright  2017 eAbyas info solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
global $CFG,$USER,$DB,$PAGE,$OUTPUT;

$systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
$PAGE->set_context($systemcontext);

$PAGE->set_url(new moodle_url('/blocks/achievements/displayallbadges.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('allbadgedisplay', 'block_achievements'));
$PAGE->set_heading(get_string('allbadgedisplay', 'block_achievements'));
$PAGE->navbar->add(get_string('allbadgedisplay', 'block_achievements'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('allbadgedisplay', 'block_achievements'), 3);
$renderer = $PAGE->get_renderer('core', 'badges');
$count = $DB->count_records_sql("SELECT count(id) FROM {badge_issued} WHERE userid = $USER->id");
$courseid = null;
if ($badges = badges_get_user_badges($USER->id, $courseid, 0, $count)) {
	echo $data = $renderer->print_badges_list($badges, $USER->id, true);
}
echo $OUTPUT->footer();