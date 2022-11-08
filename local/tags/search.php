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
 * local tags
 *
 * @package    local_tags
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
global $DB, $OUTPUT, $USER, $CFG;
require_login();

$context =(new \local_tags\lib\accesslib())::get_module_context();
if (empty($CFG->usetags)) {
    print_error('tagsaredisabled', 'tag');
}

$query     = optional_param('query', '', PARAM_RAW);
$tagcollid = optional_param('tc', 0, PARAM_INT);
$edit      = optional_param('edit', -1, PARAM_BOOL);

$params = array();
if ($query !== '') {
    $params['query'] = $query;
}
if ($tagcollid) {
    $params['tc'] = $tagcollid;
}

$PAGE->set_url(new moodle_url('/tag/search.php', $params));

$PAGE->set_context($context);

$PAGE->set_pagelayout('standard');

$PAGE->set_title(get_string('tags', 'tag'));
$PAGE->set_heading($SITE->fullname);

$buttons = '';
if (has_capability('moodle/tag:manage', $context)) {
    $buttons .= $OUTPUT->single_button(new moodle_url('/tag/manage.php'),
            get_string('managetags', 'tag'), 'GET');
}
if ($PAGE->user_allowed_editing()) {
    if ($edit != -1) {
        $USER->editing = $edit;
    }
    $buttons .= $OUTPUT->edit_button(clone($PAGE->url));
}
$PAGE->set_button($buttons);

$tagrenderer = $PAGE->get_renderer('core', 'tag');
$pagecontents = $tagrenderer->tag_search_page($query, $tagcollid);

echo $OUTPUT->header();
echo $pagecontents;
echo $OUTPUT->footer();
