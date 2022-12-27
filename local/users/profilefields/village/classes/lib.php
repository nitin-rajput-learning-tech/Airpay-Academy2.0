<?php
namespace usersprofilefields_village;

class lib{
    public function create_update_village($formdata){
        global $DB, $USER;
        // print_object($formdata);
        // exit;
        if($formdata->id){
            $updata = new \stdClass();
            $updata->id           = $formdata->id;
            $updata->village_name   = $formdata->village_name;
            $updata->code         = $formdata->code;
            $updata->subdistrictid = $formdata->subdistrictid;
            $updata->timemodified = time();
            $updata->usermodified = $USER->id;
            $villageid = $DB->update_record('local_village', $updata);
        }else{
            $newdata = new \stdClass();
            $newdata->village_name   = $formdata->village_name;
            $newdata->code         = $formdata->code;
            $newdata->subdistrictid = $formdata->subdistrictid;
            $newdata->timecreated  = time();
            $newdata->usercreated  = $USER->id;
            $villageid = $DB->insert_record('local_village', $newdata);
        }
        return $villageid;
    }
    public function village_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = (new \usersprofilefields_village\lib\accesslib())::get_module_context();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $village_sql = "SELECT lv.id,lv.village_name,(SELECT ls.subdistrict_name FROM {local_subdistrict} AS ls WHERE ls.id=lv.subdistrictid) as subdistrictname FROM {local_village} as lv";
        }
        $village_sql .= " ORDER BY lv.village_name";

        $dist = $DB->get_records_sql($village_sql);
        $village_table = new \html_table();
        $village_table->id = 'village_table';
        $headarray = array(get_string('villagename','usersprofilefields_village'),
                        get_string('subdistrictname','usersprofilefields_village'));
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
                if(is_siteadmin() || has_capability('usersprofilefields/village:edit',$systemcontext) || has_capability('usersprofilefields/village:delete',$systemcontext)){
                    $actions= '';
                    // $userexist = $DB->record_exists('user', array('open_village' => $village->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/village:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-cog"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_village/createVillage").init({selector:"createvillagemodal", contextid:'.$systemcontext->id.', villageid:'.$village->id.' }) })(event)'));
                    }
                    /*if($userexist > 0){
                        $delicon = '<i class="fa fa-trash"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_village/createVillage").nodeleteField({selector:"deletevillagemodal", contextid:'.$systemcontext->id.', compid:'.$village->id.', type:"village", name:"'.$village->village_name.'" }) })(event)'));
                    }else{*/
                        if(is_siteadmin() || has_capability('usersprofilefields/village:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash"></i>';
                            $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_village/createVillage").deleteField({selector:"deletevillagemodal", contextid:'.$systemcontext->id.', compid:'.$village->id.', type:"village", name:"'.$village->village_name.'" }) })(event)'));
                        }
                    // }
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
