<?php
namespace usersprofilefields_subdistrict;

class lib{
    public function create_update_subdistrict($formdata){
        global $DB, $USER;
        $data = new \stdClass();
        $data->subdistrict_name   = $formdata->subdistrict_name;
        $data->code         = $formdata->code;
        $data->districtid = $formdata->districtid;
        $sql = $DB->get_record('local_district', array('id' => $formdata->districtid));
        $data->statesid = $sql->statesid;
        $data->costcenterid = $sql->costcenterid;
        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $subdistrictid = $DB->update_record('local_subdistrict', $data);
            $DB->execute("UPDATE {local_village} SET districtid='{$data->districtid}',statesid ='{$data->statesid}', costcenterid='{$data->costcenterid}' WHERE subdistrictid ='{$data->id}'");
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $USER->id;
            $subdistrictid = $DB->insert_record('local_subdistrict', $data);
        }
        return $subdistrictid;
    }
    public function subdistrict_page_content(){
        global $DB,$OUTPUT,$USER, $PAGE;
        $systemcontext = (new \usersprofilefields_subdistrict\lib\accesslib())::get_module_context();
        $subdistrict_sql = "SELECT lsd.id,lsd.subdistrict_name,ld.district_name AS districtname,ls.states_name AS statesname,lc.fullname AS costcentername
            FROM {local_subdistrict} AS lsd
            JOIN {local_district} AS ld ON ld.id = lsd.districtid
            JOIN {local_states} AS ls ON ls.id=lsd.statesid
            JOIN {local_costcenter} AS lc ON lc.id=lsd.costcenterid";
        if(!is_siteadmin()){
            $territoriescond = [];
            foreach($USER->useraccess['currentroleinfo']['contextinfo'] AS $contextinfo){
                $costcenterid = explode('/', $contextinfo['costcenterpath'])[1];
                $territoriescond[] = " lsd.costcenterid = {$costcenterid} ";
            }
            if(!empty($territoriescond)){
                $subdistrict_sql .= " AND ( ".implode(' OR ', $territoriescond)." )  ";
            }else{
                $subdistrict_sql .= " AND 1 <> 1 ";
            }
        }
        $subdistrict_sql .= " ORDER BY lsd.subdistrict_name";

        $subdist = $DB->get_records_sql($subdistrict_sql);
        $subdistrict_table = new \html_table();
        $subdistrict_table->id = 'subdistrict_table';
        $headarray = array(get_string('subdistrictname','usersprofilefields_subdistrict'),
            get_string('districtname','usersprofilefields_subdistrict'),
            get_string('statesname','usersprofilefields_subdistrict'),
            get_string('costcentername','usersprofilefields_subdistrict'));
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
                $data[] = $subdistrict->statesname;
                $data[] = $subdistrict->costcentername;
                if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:edit',$systemcontext) || has_capability('usersprofilefields/subdistrict:delete',$systemcontext)){
                    $actions= '';
                    $villageexist = $DB->record_exists('local_village', array('subdistrictid'=>$subdistrict->id));
                    $userexist = $DB->record_exists('user', array('open_subdistrict'=>$subdistrict->id));
                    $noredirecturl = 'javascript:void(0)';
                    if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:edit',$systemcontext)){
                        $editicon = '<i class="fa fa-pencil" title="Edit"></i> ';
                        $actions .= \html_writer::link($noredirecturl ,$editicon,array('onclick' => '(function(e){ require("usersprofilefields_subdistrict/createSubdistrict").init({selector:"createsubdistrictmodal", contextid:'.$systemcontext->id.', subdistrictid:'.$subdistrict->id.' }) })(event)'));
                    }
                    if($userexist || $villageexist){
                        $delicon = '<i class="fa fa-trash" title="Delete"></i>';
                        $actions .= \html_writer::link($noredirecturl ,$delicon,array('onclick' => '(function(e){ require("usersprofilefields_subdistrict/createSubdistrict").nodeleteField({selector:"deletesubdistrictmodal", contextid:'.$systemcontext->id.', compid:'.$subdistrict->id.', type:"subdistrict", name:"'.$subdistrict->subdistrict_name.'" }) })(event)'));
                    }else{
                        if(is_siteadmin() || has_capability('usersprofilefields/subdistrict:delete',$systemcontext)){
                            $delicon = '<i class="fa fa-trash" title="Delete"></i>';
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
