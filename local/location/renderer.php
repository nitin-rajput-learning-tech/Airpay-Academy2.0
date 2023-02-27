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

require_once(dirname(__FILE__) . '/../../config.php');

class local_location_renderer extends plugin_renderer_base {
/*
 *  @method Display institutes
 *  @return institutes information
 */
    public function display_institutes() {
    	global $DB, $CFG, $OUTPUT,$USER, $PAGE;

        $categorycontext = (new \local_location\lib\accesslib())::get_module_context();
        $costcenterid = explode('/',$USER->open_path)[1];
        $params=array();
    	$sql = "SELECT * FROM {local_location_institutes} where 1=1 ";
        if ((has_capability('local/location:manageinstitute', $categorycontext) || has_capability('local/location:viewinstitute', $categorycontext)) && ( !is_siteadmin() ) ) {
            $sql .= " AND (costcenter = :costcenter OR usercreated = :usercreated) ";
            $params['costcenter'] = $costcenterid;
            $params['usercreated'] = $USER->id;
        }
        $sql .= " ORDER BY id DESC ";
        // print_r($sql);die;
    	$institutes = $DB->get_records_sql($sql,$params);
    	$table = new html_table();
		$table->id = 'local_institutes';
        $table->attributes['class'] = 'generaltable';

        $table->head = [get_string('institute_name', 'local_location'),
						get_string('institutetype', 'local_location'),
						get_string('address', 'local_location')];
            if ((has_capability('local/location:manageinstitute', $categorycontext))) {
		      $table->head[] =get_string('actions');
            }

		$table->align = array('' ,'center', 'center', 'center');
        if ($institutes) {
            foreach ($institutes as $institute) {
            $id = $institute->id;
            if((is_siteadmin() || $institute->usercreated == $USER->id) && has_capability('local/location:manageinstitute', $categorycontext)){
                $actions = html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('t/editinline'),'title' => get_string('edit'), 'data-action' => 'createinstitutemodal', 'class'=>'createinstitutemodal', 'data-value'=>$id, 'class' => 'iconsmall', 'onclick' =>'(function(e){ require("local_location/newinstitute").init({selector:"createinstitutemodal", contextid:1, instituteid:'.$institute->id.'}) })(event)'))/*)*/;
				$actions .= '&nbsp&nbsp';

				$actions .= html_writer::link(new moodle_url('/local/location/index.php', array('id' => $institute->id, 'delete' => 1)),
                        html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('i/trash'), 'title' => get_string('delete'), 'alt' => get_string('delete'))),array('id' => "delconfirm".$institute->id));
                        $confirmationmsg = "Are you sure you want to delete?";
                $PAGE->requires->event_handler("#delconfirm".$institute->id, 'click', 'M.util.moodle_location_confirm_dialog',array('message' => $confirmationmsg,'callbackargs' => array()));
            }else{
                $actions="Created by Admin";
            }
                if($institute->institute_type == 1){
                    $institute->institute_type = get_string('internal','local_location');
                }else{
                    $institute->institute_type = get_string('external','local_location');
                }
                if ((has_capability('local/location:manageinstitute', $categorycontext))) {

                    $table->data[] = [$institute->fullname, $institute->institute_type, $institute->address, $actions];
                }else{
                    $table->data[] = [$institute->fullname, $institute->institute_type, $institute->address];
                }
            }
            $institutestable =  html_writer::table($table);
            $institutestable .= html_writer::script(' $(document).ready(function() {
                        $("#local_institutes").dataTable({
                        searching: true,
                        responsive: true,
                         "aaSorting": [],
                         "lengthMenu": [[5, 10, 25,50,100, -1], [5,10,25, 50,100, "All"]],
                        "aoColumnDefs": [{ \'bSortable\': false, \'aTargets\': [ 0 ] }],
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "'.get_string('search','local_location').'",
                            "paginate": {
                                "next": ">",
                                "previous": "<"
                              }
                        }
                        });
                        });');
        } else
            $institutestable = get_string('no_institutes','local_location');

			return $institutestable;

    }
/*
 *  @method Display rooms
 *  @return rooms display
 */
     public function display_rooms() {
    	global $DB, $CFG, $OUTPUT,$USER, $PAGE;

        $categorycontext = (new \local_location\lib\accesslib())::get_module_context();

    	$params=array();
    	$sql = "SELECT lcr.*,lci.fullname FROM {local_location_room} as lcr
                JOIN {local_location_institutes} as lci on lci.id=lcr.instituteid WHERE 1=1 ";
       if ((has_capability('local/location:manageroom', $categorycontext) || has_capability('local/location:viewroom', $categorycontext)) && ( !is_siteadmin() ) ) {
            $sql .= " AND (lci.costcenter = :costcenter OR lci.usercreated = :usercreated) ";
            $params['costcenter'] = $USER->open_costcenterid;
            $params['usercreated'] = $USER->id;
        }
        $sql .= " ORDER BY lcr.id DESC ";
    	$rooms = $DB->get_records_sql($sql,$params);
    	$table = new html_table();
		$table->id = 'local_rooms';
        $table->attributes['class'] = 'generaltable';

        $table->head = [get_string('roomname', 'local_location'),
						get_string('institutename', 'local_location'),
						get_string('capacity', 'local_location')];
                   if ((has_capability('local/location:manageroom', $categorycontext))) {
					 $table->head[] =	get_string('actions');
                    }

		$table->align = array('' ,'center', 'center', 'center');
        if ($rooms) {
            foreach ($rooms as $room) {
            $id = $room->id;
            if ((has_capability('local/location:manageroom', $categorycontext))) {
                $actions =  html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('t/editinline'),'title' => get_string('edit'), 'data-action' => 'createroommodal', 'class'=>'createroommodal', 'data-value'=>$id, 'class' => 'iconsmall', 'onclick' =>'(function(e){ require("local_location/newroom").init({selector:"createroommodal",contextid:1, roomid:'.$room->id.'}) })(event)'))/*)*/;
				$actions .= '&nbsp&nbsp';

                $actions .= html_writer::link(new moodle_url('/local/location/room.php', array('id' => $room->id, 'delete' => 1)),
                        html_writer::empty_tag('img', array('src' => $OUTPUT->image_url('i/trash'), 'title' => get_string('delete'), 'alt' => get_string('delete'))),array('id' => "delete".$room->id));
                        $confirmationmsg = "Are you sure you want to delete?";
                $PAGE->requires->event_handler("#delete".$room->id, 'click', 'M.util.moodle_location_confirm_dialog',array('message' => $confirmationmsg,'callbackargs' => array()));

            }else{
                $actions="";
            }

			    $room->instituteid = $room->fullname;
                if ((has_capability('local/location:manageroom', $categorycontext))) {
                    $table->data[] = [$room->name, $room->instituteid, $room->capacity, $actions];
                }else{
                    $table->data[] = [$room->name, $room->instituteid, $room->capacity];
                }
            }
            $roomstable =  html_writer::table($table);
            $roomstable .= html_writer::script(' $(document).ready(function() {
                        $("#local_rooms").dataTable({
                        searching: true,
                        responsive: true,
                         "aaSorting": [],
                         "lengthMenu": [[5, 10, 25,50,100, -1], [5,10,25, 50,100, "All"]],
                        "aoColumnDefs": [{ \'bSortable\': false, \'aTargets\': [ 0 ] }],
                        language: {
                            search: "_INPUT_",
                            searchPlaceholder: "'.get_string('search','local_location').'",
                            "paginate": {
                                "next": ">",
                                "previous": "<"
                              }
                        }
                        });
                        });');
        } else
            $roomstable ='<div class="p-15"><p class="alert alert-info text-center">'.get_string('no_institute_rooms','local_location').'</p></div>';

			return $roomstable;

    }
}