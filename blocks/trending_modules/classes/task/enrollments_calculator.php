<?php
namespace block_trending_modules\task;
class enrollments_calculator extends \core\task\scheduled_task{
	public function get_name() {
        return get_string('taskenrollments_calculator', 'block_trending_modules');
    }
    public function execute(){
    	global $DB;
    	$todaytime = strtotime(date('d m Y', time()));
    	$lastweektime = $todaytime -(7*86400);
    	$dates = explode('-',date('d m Y', time()));
    	$month = (int) $dates[1];
    	if($month == 1){
    		$lastmonthtime = mktime(0, 0, 0, 12, $dates[0], (int) $dates[2]-1);
    	}else{
    		$lastmonthtime = mktime(0, 0, 0, $month-1, $dates[0], $dates[2]);
    	}
    	$modules = $DB->get_records('block_trending_modules', array(), '', 'id, module_id, module_type');
    	$trendinglib = new \block_trending_modules\lib();
    	$classobject = array();
    	foreach($modules AS $module){
    		if(!isset($classobject[$module->module_type])){
    			$classobject[$module->module_type] = $trendinglib->get_existing_moduleinfo($module->module_type, 'get_completion_count_from');
    		}
    		if($classobject[$module->module_type]){
                $weekenrolments = $classobject[$module->module_type]->get_completion_count_from($module->module_id, 'enrolled', $lastweektime);

                $monthenrolments = $classobject[$module->module_type]->get_completion_count_from($module->module_id, 'enrolled', $lastmonthtime);

                $totalenrolments = $classobject[$module->module_type]->get_completion_count_from($module->module_id, 'enrolled');
    			
    			$weekcompletions = $classobject[$module->module_type]->get_completion_count_from($module->module_id, 'completed', $lastweektime);
                
                $monthcompletions = $classobject[$module->module_type]->get_completion_count_from($module->module_id, 'completed', $lastmonthtime);

                $totalcompletions = $classobject[$module->module_type]->get_completion_count_from($module->module_id, 'completed');
                
                $module->week_enrollments = $weekenrolments;
                $module->month_enrollments = $monthenrolments;
                $module->enrollments = $totalenrolments;

                $module->week_completions = $weekcompletions;
                $module->month_completions = $monthcompletions;
                $module->completions = $totalcompletions;
                $DB->update_record('block_trending_modules', $module);
    		}
    	}
    }
}