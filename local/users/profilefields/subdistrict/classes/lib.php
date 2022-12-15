<?php
namespace usersprofilefields_subdistrict;

class lib{
    public function create_update_subdistrict($formdata){
        global $DB, $USER;
        // print_object($formdata);
        // exit;
        if($formdata->id){
            $updata = new \stdClass();
            $updata->id           = $formdata->id;
            $updata->subdistrict_name   = $formdata->subdistrict_name;
            $updata->code         = $formdata->code;
            $updata->districtid = $formdata->districtid;
            $updata->timemodified = time();
            $updata->usermodified = $USER->id;
            $subdistrictid = $DB->update_record('local_subdistrict', $updata);
        }else{
            $newdata = new \stdClass();
            $newdata->subdistrict_name   = $formdata->subdistrict_name;
            $newdata->code         = $formdata->code;
            $newdata->districtid = $formdata->districtid;
            $newdata->timecreated  = time();
            $newdata->usercreated  = $USER->id;
            $subdistrictid = $DB->insert_record('local_subdistrict', $newdata);
        }
        return $subdistrictid;
    }
    public function subdistrict_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = \context_system::instance();
        if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', $systemcontext)){
            $subdistrict_sql = "SELECT ls.id,ls.subdistrict_name,(SELECT ld.district_name FROM {local_district} AS ld WHERE ld.id=ls.districtid) as districtname FROM {local_subdistrict} as ls";
        }
        $subdistrict_sql .= " ORDER BY ls.subdistrict_name";

        $subdist = $DB->get_records_sql($subdistrict_sql);
        $subdistrict_table = new \html_table();
        $subdistrict_table->id = 'subdistrict_table';
        $headarray = array(get_string('subdistrictname','usersprofilefields_subdistrict'),
                        get_string('districtname','usersprofilefields_subdistrict'));
        if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:edit',$systemcontext) || has_capability('usersprofilefields/subdistrict:delete',$systemcontext)){
            $headarray[] = get_string('actions','usersprofilefields_subdistrict');
        }
        $subdistrict_table->head = $headarray;
        $tabledata = array();
        if(!empty($subdist)){
            foreach($subdist as $subdistrict){
                $data=array();
                $data[] = $subdistrict->subdistrict_name;
                $data[] = $subdistrict->districtname;
                if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:edit',$systemcontext) || has_capability('usersprofilefields/subdistrict:delete',$systemcontext)){
                    $actions= '';
                    $userexist = $DB->record_exists('local_village', array('subdistrictid'=>$subdistrict->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-cog"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_subdistrict/createSubdistrict").init({selector:"createsubdistrictmodal", contextid:'.$systemcontext->id.', subdistrictid:'.$subdistrict->id.' }) })(event)'));
                    }
                    if($userexist){
                        $delicon = '<i class="fa fa-trash"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_subdistrict/createSubdistrict").nodeleteField({selector:"deletesubdistrictmodal", contextid:'.$systemcontext->id.', compid:'.$subdistrict->id.', type:"subdistrict", name:"'.$subdistrict->subdistrict_name.'" }) })(event)'));
                    }else{
                        if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash"></i>';
                            $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_subdistrict/createSubdistrict").deleteField({selector:"deletesubdistrictmodal", contextid:'.$systemcontext->id.', compid:'.$subdistrict->id.', type:"subdistrict", name:"'.$subdistrict->subdistrict_name.'" }) })(event)'));
                        }
                    }
                    $data[] = $actions;
                }
                $tabledata[] = $data;
            }
            $subdistrict_table->data = $tabledata;
            echo '<br>';
            $rendertable = \html_writer::table($subdistrict_table);
        }else{
            echo '<br>';
            $rendertable = \html_writer::tag('div', get_string('no_records', 'usersprofilefields_subdistrict'), array('class' => 'alert alert-info text-center'));
        }
        return $rendertable;
    }
}