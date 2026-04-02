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
 * local onlinetests
 *
 * @package    local_tags
 * @copyright  2019 eAbyas <eAbyas.in>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
global $DB, $OUTPUT, $USER, $PAGE, $CFG;
$query     = optional_param('query', '', PARAM_RAW);
$tagcollid = optional_param('tc', 0, PARAM_INT);
$edit      = optional_param('edit', -1, PARAM_BOOL);
require_login();
//merge index and search
if (!empty($query)){
	$arr = explode("-", $query, 2);
	$id = $arr[0];
	$query = $arr[1];
    $params = array('id' => $id);
    $record = $DB->get_record('tag', $params);
    $tagid = $record->id;
    $tagname = $record->name;
    $tagcollid = $record->tagcollid;
} else {
	$tagid       = optional_param('id', 0, PARAM_INT); // tag id
	$tagname     = optional_param('tag', '', PARAM_TAG); // tag
}

$tagareaid   = optional_param('ta', 0, PARAM_INT); // Tag area id.
$exclusivemode = optional_param('excl', 0, PARAM_BOOL); // Exclusive mode (show entities in one tag area only).
$page        = optional_param('page', 0, PARAM_INT); // Page to display.
$fromctx     = optional_param('from', null, PARAM_INT);
$ctx         = optional_param('ctx', null, PARAM_INT);
$rec         = optional_param('rec', 1, PARAM_INT);
$sort         = optional_param('sort', 'highrate', PARAM_RAW);

$params = array();
if ($query !== '') {
    $params['query'] = $query;
}
if ($tagcollid) {
    $params['tc'] = $tagcollid;
}

if ($tagname) {
    if (!$tagcollid) {
        // Tag name specified but tag collection was not. Try to guess it.
        $tags = local_tags_tag::guess_by_name($tagname, '*');
        if (count($tags) > 1) {
            // This tag was found in more than one collection, redirect to search.
            redirect(new moodle_url('/local/tags/search.php', array('query' => $tagname)));
        } else if (count($tags) == 1) {
            $tag = reset($tags);
        }
    } else {
        if (!$tag = local_tags_tag::get_by_name($tagcollid, $tagname, '*')) {
            redirect(new moodle_url('/local/tags/search.php', array('tc' => $tagcollid, 'query' => $tagname)));
        }
    }
} else if ($tagid) {
    $tag = local_tags_tag::get($tagid, '*');
}
// unset($tagid);



$context =(new \local_tags\lib\accesslib())::get_module_context();
$PAGE->set_url('/local/tags/index.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_tags'));
$PAGE->set_heading(get_string('pluginname', 'local_tags'));
$PAGE->requires->jquery();
$pagenavurl = new moodle_url('/local/tags/index.php');
$PAGE->navbar->add(get_string("pluginname", 'local_tags'), new moodle_url($pagenavurl));
echo $OUTPUT->header();
if (is_siteadmin() OR has_capability('local/tags:manage',$context)) {
    $backurl = new moodle_url('/local/tags/manage.php?tc=1');
    $continue='<div class="w-100 pull-left text-right mt-3">';
    $continue.=$OUTPUT->single_button($backurl, get_string('managetags', 'local_tags'));
    $continue.='</div>';
    echo $continue;
}

$tagrenderer = $PAGE->get_renderer('local_tags');
$pagecontents = $tagrenderer->tag_search_page($query, $tagcollid, $sort, $tagname);
echo $pagecontents;

// Find all areas in this collection and their items tagged with this tag.
if ($tagid OR $tag) {
	$tagareas = local_tags_collection::get_areas($tagcollid);
	if ($tagareaid) {
	    $tagareas = array_intersect_key($tagareas, array($tagareaid => 1));
	}
	if (!$tagareaid && count($tagareas) == 1) {
	    // Automatically set "exclusive" mode for tag collection with one tag area only.
	    $exclusivemode = 1;
	}
	$entities = array();
	foreach ($tagareas as $ta) {
	    $entities[] = $tag->get_tag_index($ta, $exclusivemode, $fromctx, $ctx, $rec, $page,$sort);
	}
	$entities = array_filter($entities);
	$tagcontents = $tagrenderer->tag_index_page($tag, array_filter($entities), $tagareaid,
	        $exclusivemode, $fromctx, $ctx, $rec, $page);
	echo $tagcontents;
}

echo $OUTPUT->footer();