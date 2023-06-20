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
 * @subpackage local_location
 */


defined('MOODLE_INTERNAL') or die;
/*
 *  @method institute output fragment
 *  @param $args
 */
function local_location_output_fragment_new_instituteform($args) {
	global $CFG, $DB;

	$args = (object) $args;
	$context = $args->context;
	$instituteid = $args->instituteid;
	$o = '';
	$formdata = [];
	if (!empty($args->jsonformdata)) {
		$serialiseddata = json_decode($args->jsonformdata);
   		if(is_object($serialiseddata)){
        $serialiseddata = serialize($serialiseddata);
    	}
    	parse_str($serialiseddata, $formdata);
		}

	if ($args->instituteid > 0) {
		$heading = get_string('update_institute','local_location');
		$collapse = false;
		$data = $DB->get_record('local_location_institutes', array('id' => $instituteid));
	}
	$editoroptions = [
		'maxfiles' => EDITOR_UNLIMITED_FILES,
		'maxbytes' => $course->maxbytes,
		'trust' => false,
		'context' => $context,
		'noclean' => true,
		'subdirs' => false,
	];
	$group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);

	$mform = new local_location\form\instituteform(null, array('editoroptions' => $editoroptions), 'post', '', null, true, $formdata);

	$mform->set_data($data);

	if (!empty($formdata)) {
		// If we were passed non-empty form data we want the mform to call validation functions and show errors.
		$mform->is_validated();
	}

	ob_start();
	$mform->display();
	$o .= ob_get_contents();
	ob_end_clean();
	return $o;
}
/*
 *  @method room output fragment
 *  @param $args
 */
function local_location_output_fragment_new_roomform($args) {
	global $CFG, $DB;

	$args = (object) $args;
	$context = $args->context;
	$roomid = $args->roomid;
	$o = '';
	$formdata = [];
	if (!empty($args->jsonformdata)) {
		$serialiseddata = json_decode($args->jsonformdata);
   		if(is_object($serialiseddata)){
        $serialiseddata = serialize($serialiseddata);
    	}
    	parse_str($serialiseddata, $formdata);
		//}
	}

	if ($args->roomid > 0) {
		$heading = get_string('update_room','local_location');
		$collapse = false;
		$data = $DB->get_record('local_location_room', array('id' => $roomid));
	}
	$editoroptions = [
		'maxfiles' => EDITOR_UNLIMITED_FILES,
		'maxbytes' => $course->maxbytes,
		'trust' => false,
		'context' => $context,
		'noclean' => true,
		'subdirs' => false,
	];
	$group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);

	$mform = new local_location\form\roomform(null, array('editoroptions' => $editoroptions), 'post', '', null, true, $formdata);

	$mform->set_data($data);

	if (!empty($formdata)) {
		// If we were passed non-empty form data we want the mform to call validation functions and show errors.
		$mform->is_validated();
	}

	ob_start();
	$mform->display();
	$o .= ob_get_contents();
	ob_end_clean();
	return $o;
}

function local_location_masterinfo(){
    global $CFG, $PAGE, $OUTPUT, $DB, $USER;
    $costcenterid = explode('/',$USER->open_path)[1];
    $categorycontext =  (new \local_classroom\lib\accesslib())::get_module_context();
    $content = '';
    if(has_capability('local/location:viewinstitute', $categorycontext) || is_siteadmin() ) {
        $locations = "SELECT count(id) FROM {local_location_institutes}";
        if(!is_siteadmin()){
            $locations .=" WHERE costcenter = $costcenterid";
        }
        $totallocation = $DB->count_records_sql($locations);

        if($totallocation > 0) {
            $loc = '('.$totallocation.')';
        }


        // room
        $rooms = "SELECT count(llr.id) FROM {local_location_room} AS llr";
        if(!is_siteadmin()){
            $rooms .=" JOIN {local_location_institutes} AS lli ON lli.id = llr.instituteid
            AND lli.costcenter = $costcenterid";
        }
        $totalroom = $DB->count_records_sql($rooms);

        if($totalroom > 0) {
            $room = '('.$totalroom.')';
        } /*else {
            $room = '<i class="fa fa-times" aria-hidden="true"></i>';
        }*/
        $templatedata = array();
        $templatedata['show'] = true;
        $templatedata['count'] = $loc;
        $templatedata['link'] = $CFG->wwwroot.'/local/location/index.php?component=classroom';
        $templatedata['stringname'] = get_string('location','block_masterinfo');
        $templatedata['show2'] = true;
        $templatedata['count2'] = $room;
        $templatedata['link2'] = $CFG->wwwroot.'/local/location/room.php';
        $templatedata['stringname2'] = get_string('room','block_masterinfo');
        $templatedata['icon'] = '<i class="fa fa-map"></i>';

        $content = $OUTPUT->render_from_template('block_masterinfo/masterinfo', $templatedata);
    }
    return array('8' => $content);

}

