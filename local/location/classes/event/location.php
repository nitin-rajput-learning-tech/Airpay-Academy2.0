<?php
namespace local_location\event;
use stdClass;
defined('MOODLE_INTERNAL') or die;
class location{
    /**
     * @param [object] $data
     * @return institute updated
     */
    public function institute_update_instance($data){
    	global $DB;
    	$DB->update_record('local_location_institutes', $data);
        return '';
    }
    /**
     * @param [object] $roomdata
     * @return room updated
     */
    public function room_update_instance($roomdata){
    	global $DB;
    	$DB->update_record('local_location_room', $roomdata);
        return '';
    }
    /**
     * @param [integer] $id
     * @return institute form setdata
     */
    public function set_data_institute($id){
        if($id > 0){
            global $DB;
      $userdata=$DB->get_record('local_location_institutes',array('id'=>$id));
          $record=new stdClass();
          $record->costcenter = $userdata->costcenter;
          $record->fullname = $userdata->fullname;
          $record->shortname = $userdata->address;
          $record->address = $userdata->address;
          $record->visible = 1;
          $record->institute_type = $userdata->institute_type;
          $record->usercreated = $USER->id;
          $record->timecreated = time();

         return $record;
        }
    }
    /**
     * @param [integer] $id
     * @return room form setdata
     */
    public function set_data_room($id){
        if($id > 0){
          $userdata=$DB->get_record('local_location_room',array('id'=>$id));
          $record=new stdClass();
          $record->instituteid = $userdata->institute;
          $record->name = $userdata->name;
          $record->building = $userdata->building;
          $record->address = $userdata->address;
          $record->capacity = $userdata->capacity;
          $record->description = $userdata->description;
          $record->visible = 1;
          $record->usercreated = $USER->id;
          $record->timecreated = time();
          $record->instituteid = $userdata->instituteid;

          return $record;
        }

    }
    /**
     * @param [object] $data
     * @return institute inserted
     */
    public function institute_insert_instance($data){
        global $DB, $CFG, $USER;
          $record = new stdClass();
          $record->costcenter = $data->costcenter;
          $record->fullname = $data->fullname;
          $record->shortname = $data->fullname;
          $record->address = $data->address;
          $record->visible = 1;
          $record->institute_type = $data->institute_type;
          $record->usercreated = $USER->id;
          $record->timecreated = time();
          try{
        $institutes = $DB->insert_record('local_location_institutes', $record);
            }catch(dml_exception $ex) {
        print_error($ex);
        }
        return $institutes;
    }
    /**
     * @param [object] $roomdata
     * @return room inserted
     */
    public function room_insert_instance($roomdata){
        global $DB, $CFG, $USER;
          $record = new stdClass();
          $record->instituteid = $roomdata->instituteid;
          $record->name = $roomdata->name;
          $record->building = $roomdata->building;
          $record->address = $roomdata->address;
          $record->capacity = $roomdata->capacity;
          $record->description = $roomdata->description;
          $record->visible = 1;
          $record->usercreated = $USER->id;
          $record->timecreated = time();
        try {
            $rooms = $DB->insert_record('local_location_room', $record);
        } catch(dml_exception $ex) {
        print_error($ex);
        }

        return $rooms;
    }
    /**
     * @param [integer] $id
     * @return institute deleted
     */
    public function delete_institutes($id){
        global $DB,$CFG;
        $res= $DB->delete_records('local_location_institutes',array('id'=>$id));
        redirect($CFG->wwwroot .'/local/location/index.php');

    }
    /**
     * @param [integer] $id
     * @return room deleted
     */
    public function delete_rooms($id){
        global $DB,$CFG;
        $res= $DB->delete_records('local_location_room',array('id'=>$id));
        redirect($CFG->wwwroot .'/local/location/room.php');

    }
}
