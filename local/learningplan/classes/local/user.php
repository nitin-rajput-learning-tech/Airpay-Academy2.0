<?php
namespace local_learningplan\local;
class user {
	 public function user_profile_content($userid, $return = false,$start = 0, $limit = 5){
        global $OUTPUT;
        $returnobj = new \stdClass();
        $returnobj->learningplanexist = 1;
        $data = array();
        $userlearningplans = $this->enrol_get_users_learningplan($userid,false,true,$start,$limit);
        foreach($userlearningplans['data'] as $learniningplan){
            $learningplanarray = array();
            $learningplanarray['id'] = $learniningplan->id;
            $learningplanarray['name'] = $learniningplan->name;
            $plan_summary = \local_costcenter\lib::strip_tags_custom($learniningplan->description);
            $plan_summary = strlen($plan_summary) > 140 ? substr($plan_summary, 0, 140)."..." : $plan_summary;
            $learningplanarray['description'] = $plan_summary;
            $learningplanarray['url'] = '';
            $learningplanarray['percentage'] = '';
            $learningplan_lib = new \local_learningplan\lib\lib();
            $learningplanarray['module_img_url'] = $learningplan_lib->get_learningplansummaryfile($learniningplan->id);
            
            $data[] = $learningplanarray;
        }
        $returnobj->sequence = 2;
        $returnobj->count = $userlearningplans['count'];
        $returnobj->divid = 'user_learningplans';
        $returnobj->moduletype = 'learningplan';
        $returnobj->userid = $userid;
        $returnobj->string = get_string('learningplans', 'local_users');
        $returnobj->navdata = $data;
        return $returnobj;
    }
    public function user_team_content($user){
        global $OUTPUT;
        $total = $this->get_team_member_learningplan_status($user->id);
        $completed = $this->get_team_member_learningplan_status($user->id,1);
        $teamstatus = new \local_myteam\output\team_status_lib();
        $templatedata['elementcolor'] = $teamstatus->get_colorcode_tm_dashboard($completed,$total);
        $templatedata['completed'] = $completed;
        $templatedata['enrolled'] = $total;
        $templatedata['username'] = fullname($user);
        $templatedata['userid'] = $user->id;
        $templatedata['modulename'] = 'learningplan';
        //return $OUTPUT->render_from_template('local_users/team_status_element', $templatedata);
        return (object) $templatedata;
    }
    public function get_team_member_learningplan_status($userid,$completed = false){
        global $DB;
            
        $sql = "SELECT lpu.id,lpu.planid,lpu.status,lpu.userid as userid
                FROM {local_learningplan_user} AS lpu
                JOIN {local_learningplan} AS lp ON lpu.planid = lp.id
                WHERE lpu.userid = $userid AND lp.visible = 1";
        if($completed){
            $sql .= " AND lpu.status=1 AND lpu.completiondate!='' ";
        }
            
        $lpstatus = $DB->get_records_sql($sql);
        return count($lpstatus);
    }


    /**
     * [function to get_user modulecontent]
     * @param  [INT] $id [id of the user]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function user_modulewise_content($id,$start =0,$limit=5){
      global $OUTPUT,$PAGE;
      $returnobj = new \stdClass();
      $learningplans = $this->enrol_get_users_learningplan($id,false,true,$start,$limit);
      $data = array();
      foreach($learningplans['data'] as $learningplan){
          $learningplansarray = array();
          $learningplansarray['name'] = $learningplan->name;
          $learningplansarray['code'] = $learningplan->shortname;
          $learningplansarray['enrolldate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i",$learningplan->enrolldate);
          if($learningplan->status == 1){
            $status = 'Completed';
          }else{
            $status = 'Not Completed';
          }
          $learningplansarray['status'] = $status;
          if(!empty($learningplan->completiondate)){
            $learningplansarray['completiondate'] = \local_costcenter\lib::get_userdate("d/m/Y H:i",$learningplan->completiondate);
          }else{
            $learningplansarray['completiondate'] = 'NA';
          }

          $data[] = $learningplansarray;
      }
      $returnobj->navdata = $data;
      return $returnobj;
    }

     /**
     * [function to get_enrolled onlinetests data and count]
     * @param  [INT] $userid [id of the user]
     * @param  [BOOLEAN] $count [true or false]
     * @param  [BOOLEAN] $limityesorno [true or false]
     * @param  [INT] $start [start]
     * @return [INT] $limit [limit]
     */
    public function enrol_get_users_learningplan($userid,$count=false,$limityesorno = false,$start =0,$limit = 5){
        global $DB;
        $countsql = "SELECT count(lp.id)";
        $selectsql = "SELECT lp.id,lp.name,lp.shortname,lp.description,
                      lpu.timecreated as enrolldate,lpu.status, lpu.completiondate, summaryfile AS module_logo ";
        $learningplan_sql = " FROM {local_learningplan} AS lp
                            JOIN {local_learningplan_user} AS lpu ON lp.id = lpu.planid
                            WHERE lpu.userid = :userid";
        $params = array();
        $params['userid'] = $userid;

        if($limityesorno){
            $learningplans = $DB->get_records_sql($selectsql.$learningplan_sql, $params,$start,$limit);
        }else{
            $learningplans = $DB->get_records_sql($selectsql.$learningplan_sql, $params);
        }

        $learningplanscount = $DB->count_records_sql($countsql.$learningplan_sql, $params);
        return array('count' => $learningplanscount,'data' => $learningplans);

    }
    public function user_team_headers(){
        return array('learningplan' => get_string('pluginname', 'local_learningplan'));
    }
}
