<?php
namespace usersprofilefields_district;

class lib{
    public function create_update_district($formdata){
        global $DB, $USER;
        // print_object($formdata);
        // exit;
        if($formdata->id){
            $updata = new \stdClass();
            $updata->id           = $formdata->id;
            $updata->district_name   = $formdata->district_name;
            $updata->code         = $formdata->code;
            $updata->statesid = $formdata->statesid;
            $updata->timemodified = time();
            $updata->usermodified = $USER->id;
            $districtid = $DB->update_record('local_district', $updata);
        }else{
            $newdata = new \stdClass();
            $newdata->district_name   = $formdata->district_name;
            $newdata->code         = $formdata->code;
            $newdata->statesid = $formdata->statesid;
            $newdata->timecreated  = time();
            $newdata->usercreated  = $USER->id;
            $districtid = $DB->insert_record('local_district', $newdata);
        }
        return $districtid;
    }
    public function district_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = (new \usersprofilefields_district\lib\accesslib())::get_module_context();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $district_sql = "SELECT ld.id,ld.district_name,(SELECT ls.states_name FROM {local_states} AS ls WHERE ls.id=ld.statesid) as statesname FROM {local_district} as ld";
        }
        $district_sql .= " ORDER BY ld.district_name";

        $dist = $DB->get_records_sql($district_sql);
        $district_table = new \html_table();
        $district_table->id = 'district_table';
        $headarray = array(get_string('districtname','usersprofilefields_district'),
                        get_string('statesname','usersprofilefields_district'));
        if(is_siteadmin() || has_capability('usersprofilefields/district:edit',$systemcontext) || has_capability('usersprofilefields/district:delete',$systemcontext)){
            $headarray[] = get_string('actions','usersprofilefields_district');
        }
        $district_table->head = $headarray;
        $tabledata = array();
        if(!empty($dist)){
            foreach($dist as $district){
                $data=array();
                $data[] = $district->district_name;
                $data[] = $district->statesname;
                if(is_siteadmin() || has_capability('usersprofilefields/district:edit',$systemcontext) || has_capability('usersprofilefields/district:delete',$systemcontext)){
                    $actions= '';
                    $userexist = $DB->record_exists('local_subdistrict', array('districtid'=>$district->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/district:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-cog"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_district/createDistrict").init({selector:"createdistrictmodal", contextid:'.$systemcontext->id.', districtid:'.$district->id.' }) })(event)'));
                    }
                    if($userexist > 0){
                        $delicon = '<i class="fa fa-trash"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_district/createDistrict").nodeleteField({selector:"deletedistrictmodal", contextid:'.$systemcontext->id.', compid:'.$district->id.', type:"district", name:"'.$district->district_name.'" }) })(event)'));
                    }else{
                        if(is_siteadmin() || has_capability('usersprofilefields/district:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash"></i>';
                            $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_district/createDistrict").deleteField({selector:"deletedistrictmodal", contextid:'.$systemcontext->id.', compid:'.$district->id.', type:"district", name:"'.$district->district_name.'" }) })(event)'));
                        }
                    }
                    $data[] = $actions;

                }
                $tabledata[] = $data;
            }
            $district_table->data = $tabledata;
            echo '<br>';
            $rendertable = \html_writer::table($district_table);
        }else{
            echo '<br>';
            $rendertable = \html_writer::tag('div', get_string('no_records', 'usersprofilefields_district'), array('class' => 'alert alert-info text-center'));
        }
        return $rendertable;
    }
}
