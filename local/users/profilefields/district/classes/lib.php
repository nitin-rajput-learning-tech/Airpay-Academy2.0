<?php
namespace usersprofilefields_district;

class lib{
    public function create_update_district($formdata){
        global $DB, $USER;
        $data = new \stdClass();
        $data->district_name   = $formdata->district_name;
        $data->code         = $formdata->code;
        $data->statesid = $formdata->statesid;
        $costcenter = $DB->get_field('local_states', 'costcenterid', array('id' => $formdata->statesid));
        $data->costcenterid = $costcenter;
        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $districtid = $DB->update_record('local_district', $data);
            $DB->execute("UPDATE {local_subdistrict} SET statesid ='{$data->statesid}', costcenterid='{$data->costcenterid}' WHERE districtid ='{$data->id}'");
            $DB->execute("UPDATE {local_village} SET statesid ='{$data->statesid}', costcenterid='{$data->costcenterid}' WHERE districtid ='{$data->id}'");
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $USER->id;
            $districtid = $DB->insert_record('local_district', $data);
        }
        return $districtid;
    }
    public function district_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = (new \usersprofilefields_district\lib\accesslib())::get_module_context();
        $district_sql = "SELECT ld.id,ld.district_name,ls.states_name AS statesname,lc.fullname AS costcentername FROM {local_district} AS ld
            JOIN {local_states} AS ls ON ls.id=ld.statesid
            JOIN {local_costcenter} AS lc ON lc.id=ld.costcenterid";
        if(!is_siteadmin()){
            $territoriescond = [];
            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $territoriescond[] = " ld.costcenterid = {$costcenterid} ";
            }
            if(!empty($territoriescond)){
                $district_sql .= " AND ( ".implode(' OR ', $territoriescond)." ) ";
            }else{
                $district_sql .= " AND 1 <> 1 ";
            }

        }
        $district_sql .= " ORDER BY ld.district_name";

        $dist = $DB->get_records_sql($district_sql);
        $district_table = new \html_table();
        $district_table->id = 'district_table';
        $headarray = array(get_string('districtname','usersprofilefields_district'),
                        get_string('statesname','usersprofilefields_district'), get_string('costcentername','usersprofilefields_district'));
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
                $data[] = $district->costcentername;
                if(is_siteadmin() || has_capability('usersprofilefields/district:edit',$systemcontext) || has_capability('usersprofilefields/district:delete',$systemcontext)){
                    $actions= '';
                    $subdistrictexist = $DB->record_exists('local_subdistrict', array('districtid'=>$district->id));
                    $userexist = $DB->record_exists('user', array('open_district'=>$district->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/district:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-pencil" title="Edit"></i> ';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_district/createDistrict").init({selector:"createdistrictmodal", contextid:'.$systemcontext->id.', districtid:'.$district->id.' }) })(event)'));
                    }
                    if($userexist || $subdistrictexist){
                        $delicon = '<i class="fa fa-trash" title="Delete"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_district/createDistrict").nodeleteField({selector:"deletedistrictmodal", contextid:'.$systemcontext->id.', compid:'.$district->id.', type:"district", name:"'.$district->district_name.'" }) })(event)'));
                    }else{
                        if(is_siteadmin() || has_capability('usersprofilefields/district:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash" title="Delete"></i>';
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
