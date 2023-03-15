<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @package   Bizlms
 * @subpackage  trending_modules
 * @author eabyas  <info@eabyas.in>
**/
namespace block_trending_modules;
class querylib {
	public $db;
	public $user;
    public function __construct($user = NULL){
    	global $DB, $USER;
    	$this->user = $user ? $user : $USER;
    	$this->db = $DB;
    }
    /**
    moduletype : $moduletype is the database values stored (ex local_courses for courses ....) 
	plugin_name : $plugin_name is the directory name in moodle (ex courses for courses ....).
	returns array(sql and params) 
    */
    // public function get_trending_modules_sql($moduletype = NULL, $plugin_name = NULL){
    // 	$mytaginfo = $this->get_my_tags_info($plugin_name);
    // 	$modules_sql = array();
    // 	$module_params = array();
    // 	if(is_null($moduletype)){
    // 		$tags = array();
	//     	foreach($mytaginfo AS $key => $value){
	//     		$tags = array_merge($tags, $value);
	//     	}
	//     	$unique = array_unique($tags);
	//     	sort($tags);
	//     	foreach($mytaginfo AS $key => $value){
	//     		$this->get_trending_modules_query($key, implode(',', $tags), $modules_sql, $module_params);
	//     	}
	//     	$taggedelements_sql = implode(' UNION ', $modules_sql);
	//     }else{
	//     	$tags = $mytaginfo[$moduletype];
	//     	$this->get_trending_modules_query($moduletype, implode(',', $tags), $modules_sql, $module_params);
	// 		$taggedelements_sql = $modules_sql[0];
	//     }
    // 	return array('sql' => $taggedelements_sql, 'params' => $module_params);
    // }
    private function get_tagged_element_inmodules($module, $tags, &$modules_sql, &$module_params){
        $group_list = $this->db->get_records_sql_menu("SELECT cm.id, cm.cohortid as groupid FROM {cohort_members} cm WHERE cm.userid IN ({$this->user->id})");
        $params = array();
        if (!empty($group_list)){
             $groups_members = implode(',', $group_list);
             if(!empty($group_list)){
                $groupquery = array();
                foreach ($group_list as $key => $group) {
                    $groupquery[] = " CONCAT(',',lc.open_group,',') LIKE CONCAT('%,',{$group},',%') ";
                }
                $groupqueeryparams =implode('OR',$groupquery);
                
                $params[]= '('.$groupqueeryparams.')';
             }
        }if(count($params) > 0){
	        $group_query_concat = implode('AND',$params);
	    }else{
	        $group_query_concat = " 1 != 1 ";
	    }
	    $plugin = explode('_', $module)[1];
    	
    	switch($module){
			case 'local_courses':
				$modules_sql[] = "SELECT c.id, c.fullname, '$module' AS module, 
				c.summary AS description, c.legacyfiles AS logoimgfile, 
				lrl.module_rating, (lrl.module_like/lrl.module_like_users) AS likeratio 
				FROM {course} AS c 
				LEFT JOIN {course_completions} AS cc ON cc.course = c.id AND cc.userid = {$this->user->id}
				{$this->get_common_sql('c', $module, $tags)} AND c.visible=1

				AND c.selfenrol = 1 AND cc.timecompleted IS NULL ";
			break;
			// case 'local_classroom':
			// 	$modules_sql[] = "SELECT lc.id, lc.name AS fullname, '$module' AS module,
			// 	lc.description AS description, lc.classroomlogo AS logoimgfile,
			// 	lrl.module_rating, (lrl.module_like/lrl.module_like_users) AS likeratio
			// 	FROM {local_classroom} AS lc {$this->get_common_sql('lc', $module, $tags)}
			// 	AND lc.visible = 1 AND lc.costcenter != 0
			// 	AND {$this->user->open_costcenterid} IN (lc.costcenter)
			// 	AND (lc.id IN (SELECT classroomid FROM {local_classroom_users} WHERE userid = {$this->user->id} AND completiondate <= 0)
			// 		OR lc.id IN (SELECT lc.id FROM {local_classroom} AS lc
			// 			WHERE lc.id not in (SELECT DISTINCT classroomid
			// 				FROM {local_classroom_users}
			// 				WHERE userid = {$this->user->id})
			// 			AND lc.status in (1)
			// 			AND 1 = CASE WHEN lc.open_group is NOT NULL
            //             THEN
            //                 CASE
            //                     WHEN  {$group_query_concat}
            //                         THEN 1
            //                         ELSE 0
            //                 END
            //             ELSE 1 END
            //             AND 1 = CASE WHEN lc.department!='-1'
            //             THEN
            //                 CASE
            //                     WHEN CONCAT(',',lc.department,',') LIKE CONCAT('%,',{$this->user->open_departmentid},',%')
            //                         THEN 1
            //                         ELSE 0
            //                 END
            //             ELSE 1 END AND 1 = CASE WHEN lc.subdepartment != '-1'
            //             THEN
            //                 CASE
            //                     WHEN CONCAT(',',lc.subdepartment,',') LIKE :subdept_{$plugin}
            //                         THEN 1
            //                         ELSE 0
            //                 END
            //             ELSE 1 END AND 1 = CASE WHEN lc.open_hrmsrole IS NOT NULL
            //             THEN
            //                 CASE
            //                     WHEN CONCAT(',',lc.open_hrmsrole,',') LIKE :hrmsrole_{$plugin}
            //                         THEN 1
            //                         ELSE 0
            //                 END
            //             ELSE 1 END AND 1 = CASE WHEN lc.open_designation IS NOT NULL
            //             THEN
            //                 CASE
            //                     WHEN CONCAT(',',lc.open_designation,',') LIKE :designation_{$plugin}
            //                         THEN 1
            //                         ELSE 0
            //                 END
            //             ELSE 1 END AND 1 = CASE WHEN lc.open_location IS NOT NULL
            //             THEN
            //                 CASE
            //                     WHEN CONCAT(',',lc.open_location,',') LIKE :location_{$plugin}
            //                         THEN 1
            //                         ELSE 0
            //                 END
            //             ELSE 1 END
            //         )
            //     ) ";//AND lc.status in (1);
            //     $this->set_module_params($plugin, $module_params);
			// break;
			// case 'local_certification':
			// 	$modules_sql[] = "SELECT lcr.id, lcr.name AS fullname, '$module' AS module,
			// 	lcr.description AS description, lcr.certificationlogo AS logoimgfile,
			// 	lrl.module_rating, (lrl.module_like/lrl.module_like_users) AS likeratio
			// 	FROM {local_certification} AS lcr {$this->get_common_sql('lcr', $module, $tags)}
			// 	AND lcr.visible = 1 AND {$this->user->open_costcenterid} IN (lcr.costcenter)
			// 	AND lcr.status = 1
			// 	AND (lcr.id IN (SELECT certificationid FROM {local_certification_users}
			// 		WHERE userid={$this->user->id} AND completiondate > 0)
			// 		OR lcr.id IN (SELECT lc.id FROM {local_certification} AS lc  WHERE
			// 			lc.id not in (SELECT DISTINCT certificationid
			// 				FROM {local_certification_users} WHERE userid = {$this->user->id})
			// 			AND 1 = CASE WHEN (lc.open_group!='-1' AND lc.open_group <> '')
			// 				THEN
            //             	CASE WHEN {$group_query_concat}
            //             		THEN 1
            //             		ELSE 0 END
            //             ELSE 1 END
            //             AND 1 = CASE WHEN lc.department!='-1'
            //             	THEN
			// 					CASE WHEN CONCAT(',',lc.department,',') LIKE '%,{$this->user->open_departmentid},%'
			// 					THEN 1
			// 					ELSE 0 END
			// 				ELSE 1 END
			// 			AND 1 = CASE WHEN lc.subdepartment!='-1'
			// 				THEN
			// 					CASE WHEN CONCAT(',',lc.subdepartment,',') LIKE :subdept_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 				ELSE 1 END
			// 			AND 1 = CASE WHEN lc.open_hrmsrole IS NOT NULL
			// 				THEN
			// 					CASE WHEN CONCAT(',',lc.open_hrmsrole,',') LIKE :hrmsrole_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 				ELSE 1 END
			// 			AND 1 = CASE WHEN lc.open_designation IS NOT NULL
			// 				THEN
			// 					CASE WHEN CONCAT(',',lc.open_designation,',') LIKE :designation_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 				ELSE 1 END
			// 			AND 1 = CASE WHEN lc.open_location IS NOT NULL
			// 				THEN
			// 					CASE WHEN CONCAT(',',lc.open_location,',') LIKE :location_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 				ELSE 1 END
			// 			)
			// 		)";
			// 	$this->set_module_params($plugin, $module_params);
			// break;
			// case 'local_program':
			// 	$modules_sql[] = "SELECT lp.id, lp.name AS fullname, '$module' AS module,
			// 	lp.description AS description, lp.programlogo AS logoimgfile,
			// 	lrl.module_rating, (lrl.module_like/lrl.module_like_users) AS likeratio
			// 	FROM {local_program} AS lp {$this->get_common_sql('lp', $module, $tags)}
			// 	AND lp.visible=1 AND {$this->user->open_costcenterid} IN (lp.costcenter)
			// 	AND (lp.id in (SELECT programid FROM {local_program_users} WHERE userid={$this->user->id} AND completion_status = 0)
			// 		OR
			// 		lp.id in (SELECT lp.id FROM {local_program} AS lp
			// 			WHERE lp.id not IN
			// 				(SELECT DISTINCT programid FROM {local_program_users} WHERE userid={$this->user->id})
			// 			AND 1 = CASE WHEN (lp.open_group IS NOT NULL)
			// 				THEN
            //             	CASE WHEN {$group_query_concat}
            //             		THEN 1
            //             		ELSE 0 END
            //             ELSE 1 END
            //             AND 1 = CASE WHEN lp.department !='-1'
			// 				THEN
			// 					CASE WHEN CONCAT(',',lp.department,',') LIKE '%,{$this->user->open_departmentid},%'
			// 					THEN 1
			// 					ELSE 0 END
			// 			ELSE 1 END
			// 			AND 1 = CASE WHEN lp.subdepartment !='-1'
			// 				THEN
			// 					CASE WHEN CONCAT(',',lp.subdepartment,',') LIKE :subdept_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 			ELSE 1 END
			// 			AND 1 = CASE WHEN lp.open_hrmsrole IS NOT NULL
			// 			THEN
			// 				CASE WHEN CONCAT(',',lp.open_hrmsrole,',') LIKE :hrmsrole_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 			ELSE 1 END
			// 			 AND 1 = CASE WHEN lp.open_designation IS NOT NULL
			// 				THEN
			// 					CASE WHEN CONCAT(',',lp.open_designation,',') LIKE :designation_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 			ELSE 1 END
			// 			AND 1 = CASE WHEN lp.open_location IS NOT NULL
			// 				THEN
			// 					CASE WHEN CONCAT(',',lp.open_location,',') LIKE :location_{$plugin}
			// 					THEN 1
			// 					ELSE 0 END
			// 			ELSE 1 END
			// 		))";

			// 	$this->set_module_params($plugin, $module_params);
			// break;
			// case 'local_learningplan':
			// 	$modules_sql[] = "SELECT ll.id, ll.name AS fullname, '$module' AS module,
			// 	ll.description AS description, ll.summaryfile AS logoimgfile,
			// 	lrl.module_rating, (lrl.module_like/lrl.module_like_users) AS likeratio
			// 	FROM {local_learningplan} AS ll {$this->get_common_sql('ll', $module, $tags)}
			// 	AND ll.id > 0 AND ll.visible = 1
			// 	AND {$this->user->open_costcenterid} in (ll.costcenter)
			// 	AND 1 = CASE WHEN ( ll.open_group IS NULL OR ll.open_group = -1 )
			// 		THEN
	        //         	CASE WHEN {$group_query_concat}
	        //         		THEN 1
            //     			ELSE 0 END
            //     ELSE 1 END
            //     AND 1 = CASE WHEN ll.department!='-1'
			// 		THEN
			// 			CASE WHEN CONCAT(',',ll.department,',') LIKE '%,{$this->user->open_departmentid},%'
			// 				THEN 1
			// 				ELSE 0 END
			// 	ELSE 1 END
			// 	AND 1 = CASE WHEN ll.subdepartment!='-1'
			// 		THEN
			// 			CASE WHEN CONCAT(',',ll.subdepartment,',') LIKE :subdept_{$plugin}
			// 				THEN 1
			// 				ELSE 0 END
			// 	ELSE 1 END
			// 	AND 1 = CASE WHEN ll.open_hrmsrole IS NOT NULL
			// 		THEN
			// 			CASE WHEN CONCAT(',',ll.open_hrmsrole,',') LIKE :hrmsrole_{$plugin}
			// 			THEN 1
			// 			ELSE 0 END
			// 		ELSE 1 END
			// 	AND 1 = CASE WHEN ll.open_designation IS NOT NULL
			// 		THEN
			// 			CASE WHEN CONCAT(',',ll.open_designation,',') LIKE :designation_{$plugin}
			// 				THEN 1
			// 				ELSE 0 END
			// 	ELSE 1 END
			// 	AND 1 = CASE WHEN ll.open_location IS NOT NULL
			// 		THEN
			// 			CASE WHEN CONCAT(',',ll.open_location,',') LIKE :location_{$plugin}
			// 				THEN 1
			// 				ELSE 0 END
			// 	ELSE 1 END ";
			// 	$this->set_module_params($plugin, $module_params);
			// break;
		}
    }
    public function set_module_params($module, &$module_params){
    	if(!empty($this->user->open_subdepartment) && $this->user->open_subdepartment != ""){
            $module_params['subdept_'.$module] = "%,{$this->user->open_subdepartment},%";
        }else{
            $module_params['subdept_'.$module] = "";
        }
        if(!empty($this->user->open_hrmsrole) && $this->user->open_hrmsrole != ""){
            $module_params['hrmsrole_'.$module] = "%,{$this->user->open_hrmsrole},%";
        }else{
            $module_params['hrmsrole_'.$module] = "";
        }
        if(!empty($this->user->open_designation) && $this->user->open_designation != ""){
            $module_params['designation_'.$module] = "%,{$this->user->open_designation},%";
        }else{
            $module_params['designation_'.$module] = "";
        }
        if(!empty($this->user->open_location) && $this->user->open_location != ""){
            $module_params['location_'.$module] = "%,{$this->user->open_location},%";
        }else{
            $module_params['location_'.$module] = "";
        }
    }
    private function get_common_sql($itemprefix, $module, $tags){
    	return " JOIN {tag_instance} AS ti ON ti.itemid = {$itemprefix}.id 
    		AND ti.component LIKE '{$module}'
    		LEFT JOIN {local_ratings_likes} AS lrl ON lrl.module_id = {$itemprefix}.id AND lrl.module_area LIKE '{$module}'
    		WHERE ',{$tags},' LIKE CONCAT('%,', ti.tagid, ',%') ";//ORDER BY lrl.module_rating DESC, (lrl.module_like/lrl.module_like_users) DESC 
    }
    // public function get_modules_data($args){
    // 	$trending_data = $this->get_trending_modules_sql();
	// 	$trending_sql = $trending_data['sql'];

    // 	$params = $trending_data['params'];
    // 	$trending_sql .= " ORDER BY lrl.module_rating DESC, (lrl.module_like/lrl.module_like_users) DESC ";
    // 	$trending_modules = $this->db->get_records_sql($trending_sql, $params, $args->limitfrom,  $args->limitnum);
    // 	$content = [];
    // 	foreach($trending_modules AS $module){
    // 		$functionname = $module->module.'_content';
    // 		$content[] = $this->$functionname($module);
    // 	}
    // 	return $content;
    // }
    public function get_my_tags_info($plugin_name = NULL){
    	$my_tags = array();
    	if(is_null($plugin_name)){
	    	$core_component = new \core_component();
	    	$local_pluginlist = $core_component::get_plugin_list('local');
	    	$plugins = array_keys($local_pluginlist);
	    }else{
	    	$plugins = array($plugin_name);
	    }
    	if(in_array('courses', $plugins)){
    		$coursetags_sql = "SELECT tag.id FROM {tag} AS tag
    			JOIN {tag_instance} AS ti on ti.tagid = tag.id AND ti.component LIKE 'local_courses'
    			JOIN {course} AS c ON c.id = ti.itemid AND concat('%,',c.open_identifiedas,',%') LIKE '%,3,%' 
    			JOIN {enrol} AS e ON e.courseid = c.id AND e.enrol IN ('self','manual','auto')
    			JOIN {user_enrolments} AS ue ON ue.enrolid = e.id
    			WHERE ue.userid = :userid
    			";
			$my_tags['local_courses'] = $mycourse_tags = $this->db->get_fieldset_sql($coursetags_sql,  array('userid' => $this->user->id));
    	}
    	if(in_array('classroom', $plugins)){
    		$classroomtags_sql = "SELECT tag.id FROM {tag} AS tag 
    			JOIN {tag_instance} AS ti on ti.tagid = tag.id AND ti.component LIKE 'local_classroom'
    			JOIN {local_classroom_users} AS lcu ON lcu.classroomid = ti.itemid
    			WHERE lcu.userid = :userid ";
			$my_tags['local_classroom'] = $myclassroom_tags = $this->db->get_fieldset_sql($classroomtags_sql,  array('userid' => $this->user->id));	
    	}
    	if(in_array('certification', $plugins)){
    		$certificationtags_sql = "SELECT tag.id FROM {tag} AS tag 
    			JOIN {tag_instance} AS ti on ti.tagid = tag.id AND ti.component LIKE 'local_certification'
    			JOIN {local_certification_users} AS lcu ON lcu.certificationid = ti.itemid
    			WHERE lcu.userid = :userid ";
			$my_tags['local_certification'] = $mycertification_tags = $this->db->get_fieldset_sql($certificationtags_sql,  array('userid' => $this->user->id));	
    	}
    	if(in_array('program', $plugins)){
    		$programtags_sql = "SELECT tag.id FROM {tag} AS tag 
    			JOIN {tag_instance} AS ti on ti.tagid = tag.id AND ti.component LIKE 'local_program'
    			JOIN {local_program_users} AS lpu ON lpu.programid = ti.itemid
    			WHERE lpu.userid = :userid ";
			$my_tags['local_program'] = $myprogram_tags = $this->db->get_fieldset_sql($programtags_sql,  array('userid' => $this->user->id));	
    	}
    	if(in_array('learningplan', $plugins)){
    		$learningplantags_sql = "SELECT tag.id FROM {tag} AS tag 
    			JOIN {tag_instance} AS ti on ti.tagid = tag.id AND ti.component LIKE 'local_learningplan'
    			JOIN {local_learningplan_user} AS llu ON llu.planid = ti.itemid
    			WHERE llu.userid = :userid ";
			$my_tags['local_learningplan'] = $mylearningplan_tags = $this->db->get_fieldset_sql($learningplantags_sql,  array('userid' => $this->user->id));	
    	}
    	return $my_tags;
    }
    /**
    search : search is the content to be searched on the modules
    count : boolean to be send to get the count sql or content sql
    moduletype : $moduletype is the database values stored (ex local_courses for courses ....) 
	plugin_name : $plugin_name is the directory name in moodle (ex courses for courses ....).
	returns array(sql, ordersql and params) 
    */
    public function get_trending_modules_query($blockconfig, $search = NULL, $count = false, $moduletype = NULL, $pluginname = NULL, $filtervalues = NULL){
    	$systemcontext = \context_system::instance();
    	// $blockinstance_config = $this->db->get_field('block_instances', 'configdata', array('blockname' => 'trending_modules', 'parentcontextid' => $systemcontext->id/*, 'pagetypepattern' => 'my-index'*/));
    	// var_dump($blockconfig);exit;
    	if(isset($filtervalues->module_tags)){
    		$tags = explode(',',$filtervalues->module_tags);
    		$tag_sql_arr = [];
		    foreach($tags AS $key => $tag){
		    	$tag_sql_arr[] = " CONCAT(',',tm.module_tags,',') LIKE '%,{$tag},%' ";
		    }
		    // $blockconfig = new \stdClass();
		    // $blockconfig = get_config('block_trending_modules');
		    $blockconfig->modules_type = 'suggested_modules';
    	}else{
	    	// if(!empty($blockinstance_config)){
	    	// 	$blockconfig = unserialize(base64_decode($blockinstance_config));
	    	// }else{
	    		// $blockconfig = get_config('block_trending_modules');
		    	$blockconfig->modules_type = 'trending_modules';
	    	// }
	    	// print_object($blockconfig);
	    	if($blockconfig->modules_type == 'suggested_modules'){
		    	$mytaginfo = $this->get_my_tags_info($plugin_name);
		    	$tags = array();
		    	if(is_null($moduletype)){
			    	foreach($mytaginfo AS $key => $value){
			    		$tags = array_merge($tags, $value);
			    	}
			    	// sort($tags);
			    }else{
			    	$tags = $mytaginfo[$moduletype];
			    	// sort($tags);
			    }
		    	$tags = array_unique($tags);
			    $tag_sql_arr = [];
			    $query_params = array();
			    foreach($tags AS $key => $tag){
			    	// $tag_sql_arr[] = " CONCAT(',',tm.module_tags,',') LIKE :tag_{$key} ";
			    	// $query_params['tag_'.$key] = "'%,$tag,%'";
			    	$tag_sql_arr[] = " CONCAT(',',tm.module_tags,',') LIKE '%,{$tag},%' ";
			    	// $query_params['tag_'.$key] = "'%,$tag,%'";
			    }
			}else{
				$tag_sql_arr = [];
			}
		}
    	$group_list = $this->db->get_records_sql_menu("SELECT cm.id, cm.cohortid as groupid FROM {cohort_members} cm WHERE cm.userid IN ({$this->user->id})");
        $params = array();
        if (!empty($group_list)){
             $groups_members = implode(',', $group_list);
             if(!empty($group_list)){
                $groupquery = array();
                foreach ($group_list as $key => $group) {
                    $groupquery[] = " CONCAT(',',tm.open_group,',') LIKE CONCAT('%,',{$group},',%') ";
                }
                $groupqueeryparams =implode('OR',$groupquery);
                
                $params[]= '('.$groupqueeryparams.')';
             }
        }if(count($params) > 0){
	        $group_query_concat = implode('AND',$params);
	    }else{
	        $group_query_concat = " 1 != 1 ";
	    }
	    $currenttime = time();
	    $plugin = $pluginname;

	    if($count){
	    	$trending_sql = "SELECT COUNT(tm.id) ";
	    }else{
	    	$trending_sql = "SELECT tm.* ";
	    }

		$costcenterpathconcatsql = (new \local_costcenter\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='tm.open_path', $this->user->open_path, 'lowerandsamepath');
		$trending_sql .= "FROM {block_trending_modules} AS tm
		    WHERE tm.module_visible = 1 {$costcenterpathconcatsql}
		    AND 1 = CASE WHEN (tm.module_type LIKE 'local_classroom' OR tm.module_type LIKE 'local_certification')
		    	THEN module_status
		    	ELSE 1
	    	END 

			AND 1 = CASE WHEN (tm.module_type LIKE 'local_courses')
		    	THEN 
		    		CASE WHEN (tm.module_id NOT IN (SELECT ie.courseid 
		    			FROM {enrol} AS ie 
		    			JOIN {user_enrolments} AS iue ON iue.enrolid = ie.id AND iue.userid = {$this->user->id} ))
					THEN 1
					ELSE 0 END
			ELSE 1 END
			AND 1 = CASE WHEN (tm.open_group IS NOT NULL AND tm.open_group != 0) 
				THEN
	            	CASE WHEN {$group_query_concat}
	            		THEN 1
	        			ELSE 0 END 
	        ELSE 1 END 
			AND 1 = CASE WHEN tm.open_hrmsrole IS NOT NULL
				THEN 
					CASE WHEN CONCAT(',',tm.open_hrmsrole,',') LIKE :hrmsrole_{$plugin}
					THEN 1
					ELSE 0 END 
				ELSE 1 END 
			AND 1 = CASE WHEN tm.open_designation IS NOT NULL
				THEN 
					CASE WHEN CONCAT(',',tm.open_designation,',') LIKE :designation_{$plugin}
						THEN 1
						ELSE 0 END 
			ELSE 1 END 
			AND 1 = CASE WHEN tm.open_location IS NOT NULL
				THEN 
					CASE WHEN CONCAT(',',tm.open_location,',') LIKE :location_{$plugin}
						THEN 1
						ELSE 0 END 
			ELSE 1 END";
			// Commented for future use
			// AND 1 = CASE WHEN (tm.module_type LIKE 'local_classroom')
		    // 	THEN
		    // 		CASE WHEN (tm.module_id NOT IN (SELECT ilcu.classroomid
		    // 			FROM {local_classroom_users} AS ilcu
		    // 			WHERE ilcu.userid = {$this->user->id} )
		    // 			AND ({$currenttime} < tm.module_enddate OR tm.module_enddate = 0))
			// 		THEN 1
			// 		ELSE 0 END
			// ELSE 1 END
			// AND 1 = CASE WHEN (tm.module_type LIKE 'local_certification')
		    // 	THEN
		    // 		CASE WHEN (tm.module_id NOT IN (SELECT ilcu.certificationid
		    // 				FROM {local_certification_users} AS ilcu
		    // 				WHERE ilcu.userid = {$this->user->id})
		    // 			AND ({$currenttime} < tm.module_enddate OR tm.module_enddate = 0))
			// 		THEN 1
			// 		ELSE 0 END
			// ELSE 1 END
			// AND 1 = CASE WHEN (tm.module_type LIKE 'local_program')
		    // 	THEN
		    // 		CASE WHEN (tm.module_id NOT IN (SELECT programid
			// 			FROM {local_program_users}
			// 			WHERE userid = {$this->user->id}))
			// 		THEN 1
			// 		ELSE 0 END
			// ELSE 1 END
			// AND 1 = CASE WHEN (tm.module_type LIKE 'local_learningplan')
		    // 	THEN
		    // 		CASE WHEN (tm.module_id NOT IN (SELECT planid
			// 			FROM {local_learningplan_user}
			// 			WHERE userid = {$this->user->id}))
			// 		THEN 1
			// 		ELSE 0 END
			// ELSE 1 END
		if(!is_null($moduletype)){
			// var_dump($moduletype);
			$moduletype = array($moduletype);
			$moduletypes = implode(',', $moduletype);
			// var_dump($moduletypes);
			$trending_sql .= " AND tm.module_type IN (:moduletypes) ";
			$query_params['moduletypes'] = $moduletypes;
		}
		if(!is_null($search)){
			$trending_sql .= " AND (tm.module_name LIKE :search_name OR module_description LIKE :search_desc) ";
			$query_params['search_name'] = "%$search%";
			$query_params['search_desc'] = "%$search%";
			// var_dump($search);
		}
		
		$order_sql = " ORDER BY ";
		$orderdata = array();
		if (!empty($tag_sql_arr)){
			if($blockconfig->modules_type == 'trending_modules'){
				$orderdata[] = " tm.enrollments DESC, tm.module_rating DESC ";
				$concat_sql	= " 1 != 1 ";
			}else{
				$concat_sql = implode(' OR ', $tag_sql_arr);
				$orderdata[] =  " CASE WHEN ( $concat_sql ) 
								THEN 1
								ELSE 0
							END DESC , tm.module_rating DESC
							";
			}
		}else {
			$concat_sql	= " 1 != 1 ";
			if(!empty($filtervalues)){
				// if(isset($filtervalues->type)){
				// 	switch($filtervalues->type){
				// 		case 'enrolments' : 
				// 			$type = 'enrollments';
				// 		break;
				// 		case 'completions' :
				// 			$type = 'completions';
				// 		break;
				// 		case 'ratings' :
				// 			$type = 'ratings';
				// 		break;
				// 	}
				// 	// $type = $filtervalues->type == 'enrolments' ? 'enrollments': 'completions';
				// }else{
					$type = False;
				//}
				if(isset($filtervalues->duration)){
					switch($filtervalues->duration){
						case 'weekly':
							$duration = 'week_';
						break;
						case 'monthly':
							$duration = 'month_';
						break;
						case 'total':
							$duration = '';
						break;
						default :
							$duration = False;
						break;
					}
				}
				if(isset($filtervalues->order)){
					$order = $filtervalues->order == 'asc' ? ' ASC ' : ' DESC ';
				}else{
					$order = ' ASC ';
				}
				if($type == 'ratings'){
					$orderdata[] = " tm.module_rating {$order} ";
				}else if ($type){
					$filter_value = /*$duration ? $duration.$type : */$type;
					$orderdata[] = " tm.{$filter_value} {$order} ";
				}
			}else{
				$orderdata[] = " tm.module_rating DESC ,tm.enrollments DESC, tm.completions DESC ";
			}
		}
		if(!empty($orderdata)){
			$order_sql .= implode(' , ', $orderdata);
		}else{
			$order_sql = ' ';
		}
		if($blockconfig->modules_type == 'trending_modules' || $blockconfig->modules_type == 'both'){
			// $trending_sql .= " ";
			if(/*isset($blockconfig->frequency) && */isset($blockconfig->minenrollments)  && isset($blockconfig->mincompletions) && isset($blockconfig->rating) ){
				// $freqprefix = $blockconfig->frequency == 'overall' ? '' : $blockconfig->frequency.'_';
				$enrollmentsField = 'tm.'./*$freqprefix.*/'enrollments';
				$completionsField = 'tm.'./*$freqprefix.*/'completions';
				$trending_sql .= " AND ( {$enrollmentsField} >= {$blockconfig->minenrollments} OR {$completionsField} >= {$blockconfig->mincompletions} OR CAST(tm.module_rating AS decimal(5,2)) >= {$blockconfig->rating} ) ";
			}
		}else if($blockconfig->modules_type == 'suggested_modules'){
			$trending_sql .= " AND ($concat_sql) ";
		// }else if($blockconfig->config_modules_type == 'both'){
		// 	$trending_sql .= " ";
		}
		
		// print_object($query_params);
		$this->set_module_params($plugin, $query_params);
		// print_object($query_params);

		// echo "<pre>$trending_sql.$order_sql</pre>";exit;
		return array('sql' => $trending_sql, 'ordersql' => $order_sql, 'params' => $query_params);
    }
    public function make_trending_instance_object($objectid, $pluginname){
    	$logo_fieldnames = array('local_classroom' => 'classroomlogo', 'local_certification' => 'certificationlogo', 'local_program' => 'programlogo', 'local_learningplan' => 'summaryfile');
		switch($pluginname){
			case 'local_courses':
				$sql = "SELECT c.id,'{$pluginname}' AS module_type, c.fullname AS name, 
					c.open_path AS open_path, '0' AS module_imagelogo,
					c.startdate AS module_startdate, c.enddate AS module_enddate, 
					c.summary AS module_description, c.visible AS module_visible
					FROM {course} AS c WHERE c.id = :id ";
			break;
			case 'local_classroom': 
			case 'local_certification':
			case 'local_program':
			case 'local_learningplan':
				$statusFld = ($pluginname == 'local_classroom' || $pluginname == 'local_certification') ? 'c.status' : 'c.id';
				$sql = "SELECT c.id,'{$pluginname}' AS module_type, c.name,   
					c.open_path AS open_path,
					CASE WHEN ('{$pluginname}' = 'local_classroom' OR '{$pluginname}' = 'local_certification')
						THEN {$statusFld}
						ELSE 1 END AS module_status, 
					c.open_group , c.open_hrmsrole , 
					c.open_designation , c.open_location ,c.visible AS module_visible, 
					c.startdate AS module_startdate, c.enddate AS module_enddate,
					c.description AS module_description , 
					{$logo_fieldnames[$pluginname]} AS module_imagelogo
					FROM {{$pluginname}} AS c  
					WHERE c.id = :id ";
			break;
		}
		$module_data = $this->db->get_record_sql($sql, array('id' => $objectid));
		$tags_sql = "SELECT ti.tagid FROM {tag_instance} AS ti WHERE ti.itemid = :id 
					AND ti.component LIKE :component ";
		$tags = $this->db->get_fieldset_sql($tags_sql, array('id' => $objectid,'component' => $pluginname));
		if(!empty($tags)){
			sort($tags);
			$module_data->module_tags = implode(',', $tags);
		}else{
			$module_data->module_tags = NULL;
		}
		return $module_data;
	}
}
