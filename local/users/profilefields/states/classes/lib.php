<?php
namespace usersprofilefields_states;

class lib{
    public function create_update_states($formdata){
        global $DB, $USER;
        $data = new \stdClass();
        $data->states_name   = $formdata->states_name;
        $data->code         = $formdata->code;
        $data->costcenterid = $formdata->costcenterid;
        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $statesid = $DB->update_record('local_states', $data);
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $USER->id;
            $statesid = $DB->insert_record('local_states', $data);
        }
        return $statesid;
    }
    public function states_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = (new \usersprofilefields_states\lib\accesslib())::get_module_context();
        $states_sql = "SELECT ls.id,ls.states_name,lc.fullname as costcentername
                FROM {local_states} as ls
                JOIN {local_costcenter} AS lc ON lc.id = ls.costcenterid WHERE lc.depth = 1 ";
        if(!is_siteadmin()){
            $territoriescond = [];
            foreach($USER->access['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $territoriescond[] = " lc.id = {$costcenterid} ";
            }
            if(!empty($territoriescond)){
                $consql = " AND ( ".implode(' OR ', $territoriescond)." ) ";
            }else{
                $consql = " AND 1 <> 1 ";
            }
            $states_sql .= "  $consql  ";//territory depth = 5
        }
        $states_sql .= " ORDER BY ls.states_name";

        $state = $DB->get_records_sql($states_sql);
        $states_table = new \html_table();
        $states_table->id = 'states_table';
        $headarray = array(get_string('statesname','usersprofilefields_states'),
                        get_string('costcentername','usersprofilefields_states'));
        if(is_siteadmin() || has_capability('usersprofilefields/states:edit',$systemcontext) || has_capability('usersprofilefields/states:delete',$systemcontext)){
            $headarray[] = get_string('actions','usersprofilefields_states');
        }
        $states_table->head = $headarray;
        $tabledata = array();
        if(!empty($state)){
            foreach($state as $states){
                $data=array();
                $data[] = $states->states_name;
                $data[] = $states->costcentername;
                if(is_siteadmin() || has_capability('usersprofilefields/states:edit',$systemcontext) || has_capability('usersprofilefields/states:delete',$systemcontext)){
                    $actions= '';

                    $userexist = $DB->record_exists('local_district', array('statesid'=>$states->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/states:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-pencil"></i>';
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
