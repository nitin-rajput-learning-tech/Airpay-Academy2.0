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
use \block_announcement\form\announcement_form as announcement_form;
defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot.'/course/lib.php');
class block_announcement_external extends external_api {
	/**
     * Describes the parameters for submit_create_group_form webservice.
     * @return external_function_parameters
     */
    public static function submit_create_announcement_form_parameters() {
        return new external_function_parameters(
            array(
                'id' => new external_value(PARAM_INT, 'ID', 0),
                'contextid' => new external_value(PARAM_INT, 'The context id for the system'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array')
            )
        );
    }
    
    /**
     * Submit the create group form.
     *
     * @param int $contextid The context id for the category.
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return int new category id.
     */
    public static function submit_create_announcement_form($id,$contextid, $jsonformdata) {
        global $DB, $CFG, $USER;
	
        require_once($CFG->dirroot . '/blocks/announcement/lib.php');
        
        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_create_announcement_form_parameters(),
                                            ['id'=>$id,'contextid' => $contextid, 'jsonformdata' => $jsonformdata]);
        
        $context = context::instance_by_id($params['contextid'], MUST_EXIST);
        //print_object($contextid);
        // We always must call validate_context in a webservice.
        self::validate_context($context);
        $serialiseddata = json_decode($params['jsonformdata']);
        
        $data = array();
        parse_str($serialiseddata, $data);
        
        $warnings = array();
        
         $id = $data['id'];
		 $context = (new \block_announcement\lib\accesslib())::get_module_context();
		 $itemid = 0;
        // The last param is the ajax submitted data.
        $mform = new block_announcement\form\announcement_form(null, array(), 'post', '', null, true, $data);
        
        $validateddata = $mform->get_data();
        $itemid = $validateddata->description['itemid'];
        $blockcontext = (new \block_announcement\lib\accesslib())::get_module_context();
        if ($validateddata) {
			file_save_draft_area_files($itemid, $blockcontext->id, 'block_announcement', 'announcement',$itemid, array('maxfiles' => 1));
            if($validateddata->attachment){
                file_save_draft_area_files($validateddata->attachment, $blockcontext->id, 'block_announcement', 'announcement',$validateddata->attachment, array('maxfiles' => 1));
            }
            $announcement_lib = new \block_announcement\local\lib();
	        if($validateddata->id > 0){
				$record = $announcement_lib->update($validateddata, $mform->get_description_editor_options());
	        }else{
	            $record = $announcement_lib->create($validateddata, $mform->get_description_editor_options());	
	        }
        } else {
            // Generate a warning.
            throw new moodle_exception('Error in submission');
        }
        
        return $record->id;
    }
    
    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function submit_create_announcement_form_returns() {
         return new external_value(PARAM_INT, 'return');
    }
	
}