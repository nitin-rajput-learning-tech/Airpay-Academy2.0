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
 * @subpackage blocks_announcement
 */
use \blocks_announcement\form\announcement_form as announcement_form;

function block_announcement_output_fragment_announcement_form($args){
 global $DB,$CFG,$PAGE;
    
    $args = (object) $args;
    $context = $args->context;
    $id = $args->id;
    
    $o = '';
   
    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }
 
    $context = (new \block_announcement\lib\accesslib())::get_module_context();
	$itemid = 0;
    
    if ($id > 0) {
        $heading = get_string('update_announcement', 'block_announcement');
        $collapse = false;
        $data = $DB->get_record('block_announcement', array('id'=>$id));
        // print_r($data);die;
        $formdata = new stdClass();
        $formdata->id = $data->id;
        $formdata->name = $data->name;
        $formdata->description['text'] = $data->description;
        $formdata->attachment = $data->attachment;
        $formdata->startdate = $data->startdate;
        $formdata->enddate = $data->enddate;
        $formdata->open_costcenterid = $data->costcenterid;
    }
    
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => true,
        'subdirs' => false
    ];
    $params = array(
    'id' => $id,
    'context' => $context,
	'itemid' => $itemid,
    'editoroptions' => $editoroptions,
    'attachment' => $data->attachment,
    );
 
    $mform = new block_announcement\form\announcement_form(null, $params, 'post', '', null, true, (array)$formdata);
    $mform->set_data($formdata);

    if (!empty($args->jsonformdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }
 
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();
 
    return $o;
}
function block_announcement_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG;

    if ($filearea == 'announcement') {
        $itemid = (int) array_shift($args);

        $fs = get_file_storage();
        $filename = array_pop($args);
        if (empty($args)) {
            $filepath = '/';
        } else {
            $filepath = '/' . implode('/', $args) . '/';
        }

        $file = $fs->get_file($context->id, 'block_announcement', $filearea, $itemid, $filepath, $filename);

        if (!$file) {
            return false;
        }
        $filedata = $file->resize_image(200, 200);
        \core\session\manager::write_close();
        send_stored_file($file, null, 0, 1);
    }

    send_file_not_found();
}














