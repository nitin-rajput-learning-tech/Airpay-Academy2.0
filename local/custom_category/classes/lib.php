<?php
namespace local_custom_category;

class lib{
    public function custom_category_opertaions($formdata){
        global $DB, $USER;
        $data = new \stdClass();
        $costcenter = explode('/', $USER->open_path);
        $data->costcenterid = $formdata->open_costcenterid ? $formdata->open_costcenterid: $costcenter[1];
        $data->fullname = $formdata->name;
        $data->shortname = $formdata->shortname;
        $data->parentid = $formdata->parentid ? $formdata->parentid:0;
        if ($formdata->parentid == 0) {
            $data->depth = $formdata->depth = 1;
        } else {
            $parent = $DB->get_record('local_custom_category', array('id' => $formdata->parentid));
            $data->depth = $parent->depth + 1;
        }
        if($formdata->id){
            $data->id           = $formdata->id;
            $data->timemodified = time();
            $data->usermodified = $USER->id;
            $parentpath = $DB->get_field('local_custom_category', 'path', array('id'=>$formdata->parentid));
            $path = $parentpath.'/'.$formdata->id;
            $data->path = $path;
            $statesid->id = $DB->update_record('local_custom_category', $data);
        }else{
            $data->timecreated  = time();
            $data->usercreated  = $USER->id;

            $statesid->id = $DB->insert_record('local_custom_category', $data);

            if($statesid->id) {
                $parentpath = $DB->get_field('local_custom_category', 'path', array('id'=>$formdata->parentid));
                $path = $parentpath.'/'.$statesid->id;
                $datarecord = new \stdClass();
                $datarecord->id = $statesid->id;
                $datarecord->path = $path;
                $DB->update_record('local_custom_category',  $datarecord);
            }
            blocks_add_default_org_blocks($statesid->id);
        }
        return $statesid->id;
    }
}
