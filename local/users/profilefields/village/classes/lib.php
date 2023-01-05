<?php
namespace usersprofilefields_village;

class lib{
    public function create_update_village($formdata){
        global $DB, $USER;
        $data = new \stdClass();
        $data->village_name   = $formdata->village_name;
        $data->code         = $formdata->code;
        $data->subdistrictid = $formdata->subdistrictid;
        $sql = $DB->get_record('local_subdistrict', array('id' => $formdata->subdistrictid));
        $data->districtid = $sql->districtid;
        $data->statesid = $sql->statesid;
        $data->costcenterid = $sql->costcenterid;

        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $villageid = $DB->update_record('local_village', $data);
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $USER->id;
            $villageid = $DB->insert_record('local_village', $data);
        }
        return $villageid;
    }
    public function village_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = (new \usersprofilefields_village\lib\accesslib())::get_module_context();
        $village_sql = "SELECT lv.id,lv.village_name,lsd.subdistrict_name AS subdistrictname,ld.district_name AS districtname,ls.states_name AS statesname,lc.fullname AS costcentername
            FROM {local_village} AS lv
            JOIN {local_subdistrict} AS lsd ON lsd.id=lv.subdistrictid
            JOIN {local_district} AS ld ON ld.id = lv.districtid
            JOIN {local_states} AS ls ON ls.id=lv.statesid
            JOIN {local_costcenter} AS lc ON lc.id=lv.costcenterid";
        if(!is_siteadmin()){
            $territoriescond = [];
            foreach($USER->access['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $territoriescond[] = " lv.costcenterid = {$costcenterid} ";
            }
            if(!empty($territoriescond)){
                $village_sql .= " AND ( ".implode(' OR ', $territoriescond)." ) ";
            }else{
                $village_sql .= " AND 1 <> 1 ";
            }
        }
        $village_sql .= " ORDER BY lv.village_name";

        $dist = $DB->get_records_sql($village_sql);
        $village_table = new \html_table();
        $village_table->id = 'village_table';
        $headarray = array(get_string('villagename','usersprofilefields_village'),
            get_string('subdistrictname','usersprofilefields_village'),
            get_string('districtname','usersprofilefields_village'),
            get_string('statesname','usersprofilefields_village'),
            get_string('costcentername','usersprofilefields_village'));
        if(is_siteadmin() || has_capability('usersprofilefields/village:edit',$systemcontext) || has_capability('usersprofilefields/village:delete',$systemcontext)){
            $headarray[] = get_string('actions','usersprofilefields_village');
        }
        $village_table->head = $headarray;
        $tabledata = array();
        if(!empty($dist)){
            foreach($dist as $village){
                $data=array();
                $data[] = $village->village_name;
                $data[] = $village->subdistrictname;
                $data[] = $village->districtname;
                $data[] = $village->statesname;
                $data[] = $village->costcentername;
                if(is_siteadmin() || has_capability('usersprofilefields/village:edit',$systemcontext) || has_capability('usersprofilefields/village:delete',$systemcontext)){
                    $actions= '';
                    $userexist = $DB->record_exists('user', array('open_village' => $village->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/village:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-pencil" title="Edit"></i> ';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_village/createVillage").init({selector:"createvillagemodal", contextid:'.$systemcontext->id.', villageid:'.$village->id.' }) })(event)'));
                    }
                    if($userexist > 0){
                        $delicon = '<i class="fa fa-trash" title="Delete"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_village/createVillage").nodeleteField({selector:"deletevillagemodal", contextid:'.$systemcontext->id.', compid:'.$village->id.', type:"village", name:"'.$village->village_name.'" }) })(event)'));
                    }else{
                        if(is_siteadmin() || has_capability('usersprofilefields/village:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash" title="Delete"></i>';
                            $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_village/createVillage").deleteField({selector:"deletevillagemodal", contextid:'.$systemcontext->id.', compid:'.$village->id.', type:"village", name:"'.$village->village_name.'" }) })(event)'));
                        }
                    }
                    $data[] = $actions;

                }
                $tabledata[] = $data;
            }
            $village_table->data = $tabledata;
            echo '<br>';
            $rendertable = \html_writer::table($village_table);
        }else{
            echo '<br>';
            $rendertable = \html_writer::tag('div', get_string('no_records', 'usersprofilefields_village'), array('class' => 'alert alert-info text-center'));
        }
        return $rendertable;
    }
}
