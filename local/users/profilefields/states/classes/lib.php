<?php
namespace usersprofilefields_states;

class lib{
    public function create_update_states($formdata){
        global $DB, $USER;
        // print_object($formdata);
        // exit;
        if($formdata->id){
            $updata = new \stdClass();
            $updata->id           = $formdata->id;
            $updata->states_name   = $formdata->states_name;
            $updata->code         = $formdata->code;
            $updata->territoryid = $formdata->territoryid;
            $updata->timemodified = time();
            $updata->usermodified = $USER->id;
            $statesid = $DB->update_record('local_states', $updata);
        }else{
            $newdata = new \stdClass();
            $newdata->states_name   = $formdata->states_name;
            $newdata->code         = $formdata->code;
            $newdata->territoryid = $formdata->territoryid;
            $newdata->timecreated  = time();
            $newdata->usercreated  = $USER->id;
            $statesid = $DB->insert_record('local_states', $newdata);
        }
        return $statesid;
    }
    public function states_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = \context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $states_sql = "SELECT ls.id,ls.states_name,(SELECT lc.fullname FROM {local_costcenter} AS lc WHERE lc.id=ls.territoryid) as territoryname FROM {local_states} as ls ";
        }else if(has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            $states_sql = "SELECT ls.id,ls.states_name,(SELECT lc.fullname FROM {local_costcenter} AS lc WHERE lc.id=ls.territoryid) as territoryname FROM {local_states} as ls
                JOIN {local_costcenter} as lc ON lc.id = ls.territoryid
                WHERE concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 5 ";//territory depth = 5
        }else{
            $states_sql = "SELECT ls.id,ls.states_name,(SELECT lc.fullname FROM {local_costcenter} AS lc WHERE lc.id=ls.territoryid) as territoryname FROM {local_states} as ls
                JOIN {local_costcenter} as lc ON lc.id = ls.territoryid
                WHERE concat('/',u.open_path,'/') LIKE concat('%/',lc.id,'/%') AND lc.depth = 5 ";//territory depth = 5
        }
        $states_sql .= " ORDER BY ls.states_name";

        $state = $DB->get_records_sql($states_sql);
        $states_table = new \html_table();
        $states_table->id = 'states_table';
        $headarray = array(get_string('statesname','usersprofilefields_states'),
                        get_string('territoryname','usersprofilefields_states'));
        if(is_siteadmin() || has_capability('usersprofilefields/states:edit',$systemcontext) || has_capability('usersprofilefields/states:delete',$systemcontext)){
            $headarray[] = get_string('actions','usersprofilefields_states');
        }
        $states_table->head = $headarray;
        $tabledata = array();
        if(!empty($state)){
            foreach($state as $states){
                $data=array();
                $data[] = $states->states_name;
                $data[] = $states->territoryname;
                if(is_siteadmin() || has_capability('usersprofilefields/states:edit',$systemcontext) || has_capability('usersprofilefields/states:delete',$systemcontext)){
                    $actions= '';

                    $userexist = $DB->record_exists('local_district', array('statesid'=>$states->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/states:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-cog"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_states/createStates").init({selector:"createstatesmodal", contextid:'.$systemcontext->id.', statesid:'.$states->id.' }) })(event)'));
                    }
                    if($userexist){
                        $delicon = '<i class="fa fa-trash"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_states/createStates").nodeleteField({selector:"deletestatesmodal", contextid:'.$systemcontext->id.', compid:'.$states->id.', type:"states", name:"'.$states->states_name.'" }) })(event)'));
                    }else{
                        if(is_siteadmin() || has_capability('usersprofilefields/states:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash"></i>';
                            $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_states/createStates").deleteField({selector:"deletestatesmodal", contextid:'.$systemcontext->id.', compid:'.$states->id.', type:"states", name:"'.$states->states_name.'" }) })(event)'));
                        }
                    }
                    $data[] = $actions;

                }
                $tabledata[] = $data;
            }
            $states_table->data = $tabledata;
            echo '<br>';
            $rendertable = \html_writer::table($states_table);
        }else{
            echo '<br>';
            $rendertable = \html_writer::tag('div', get_string('no_records', 'usersprofilefields_states'), array('class' => 'alert alert-info text-center'));
        }
        return $rendertable;
    }
}