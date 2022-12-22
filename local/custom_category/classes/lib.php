<?php
namespace local_custom_category;

class lib{
    public function custom_category_opertaions($formdata){
        global $DB, $USER;
        // print_object($formdata);
        // exit;
        if($formdata->id){
            $updata = new \stdClass();
            $updata->id           = $formdata->id;
            $updata->costcenterid   = $formdata->open_costcenterid;
            $updata->fullname         = $formdata->name;
            $updata->shortname = $formdata->shortname;
            $updata->timemodified = time();
            $updata->usermodified = $USER->id;
            $updata->parentid = $formdata->parentid ? $formdata->parentid:0;
            $parentpath = $DB->get_field('local_custom_category', 'path', array('id'=>$formdata->parentid));
            $path = $parentpath.'/'.$formdata->id;
            $updata->path = $path;
            if ($formdata->parentid == 0) {
                $updata->depth = $formdata->depth = 1;
            } else {
                $parent = $DB->get_record('local_custom_category', array('id' => $formdata->parentid));
                $updata->depth = $parent->depth + 1;
            }
            $statesid->id = $DB->update_record('local_custom_category', $updata);
        }else{
            $newdata = new \stdClass();
            $newdata->costcenterid   = $formdata->open_costcenterid;
            $newdata->fullname         = $formdata->name;
            $newdata->shortname = $formdata->shortname;
            $newdata->timecreated  = time();
            $newdata->usercreated  = $USER->id;
            $newdata->parentid = $formdata->parentid ?  $formdata->parentid:0;
            if ($formdata->parentid == 0) {
                $newdata->depth = $formdata->depth = 1;
            } else {
                $parent = $DB->get_record('local_custom_category', array('id' => $formdata->parentid));
                $newdata->depth = $parent->depth + 1;
            }
            $statesid->id = $DB->insert_record('local_custom_category', $newdata);

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