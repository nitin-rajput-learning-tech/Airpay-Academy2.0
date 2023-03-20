<?php
namespace block_myskills\output;
use context_system;
class renderer extends \plugin_renderer_base {
	protected $cfg;

	protected $output;

	protected $page;
	
	protected $db;

	protected $user;

	public function __construct(){
		global $DB,$CFG,$OUTPUT, $PAGE, $USER;
		$this->cfg = $CFG;
		$this->output = $OUTPUT;
		$this->page = $PAGE;
		$this->db = $DB;
		$this->user = $USER;
	}

	public function display_skill_acquired_structure(){
		$table = new \html_table();
		$table->id = "all_skills_acquired";
		$table->head = array(get_string('skillname', 'block_myskills'), get_string('proficiencylevel', 'block_myskills'), get_string('coursename', 'block_myskills'), get_string('achieveddate', 'block_myskills'));
    	$table->size = array('30%','25%', '25%', '20%');
    	$table->align = array('left','center', 'left','center');
		$table = \html_writer::table($table);
		return $table;
	}

	public function display_skill_acquired_data($params){
		$context = context_system::instance();
		$this->page->set_context($context);
		$skillssql = "SELECT c.id, c.fullname, c.open_points, ls.name AS skillname, ll.name as levelname, ls.name, cc.timecompleted  FROM {course_completions} cc
              JOIN {course} c ON cc.course = c.id
              JOIN {local_skill} ls ON c.open_skill = ls.id
              JOIN {local_course_levels} ll ON c.open_level = ll.id
              WHERE cc.userid = {$this->user->id} AND cc.timecompleted IS NOT NULL ";
        if (isset($params->recordsperpage) && $params->perpage != '-1'){
            $skillssql .= " LIMIT ".$params->recordsperpage .", ".$params->perpage;
        }
        $skillurl = $this->output->image_url('skill_clr', 'block_myskills');
        $skillimg = \html_writer::tag('img', '', array('src' => $skillurl/*, 'style' => 'height:15px'*/));
      	$skillsacquired = $this->db->get_records_sql($skillssql);
      	$tabledata = array();
      	foreach($skillsacquired as $skill){
      		$data = array();
      		$coursename = strlen($skill->fullname)>12 ? substr($skill->fullname,0,12).'...' : $skill->fullname;
      		$skill->fullname = \html_writer::tag('p', $coursename, array('title' => $skill->fullname));
      		$skillname = strlen($skill->skillname) > 12 ? substr($skill->skillname, 0, 12).'...' : $skill->skillname;
      		$data[] = '<span title="'.$skill->skillname.'">'.$skill->skillname ? $skillimg .' &nbsp '. $skillname : '-'.'</span>';
      		$data[] = $skill->levelname ? $skill->levelname : '-';
      		$data[] = $skill->fullname;
      		$data[] = $skill->open_points  ? $skill->open_points : '-';
      		$data[] = date('d/M/Y', $skill->timecompleted);
      		$tabledata[] = $data;
      	}
      	return $tabledata;
	}
	public function get_total_skills_count($params){
		$skillscountsql = "SELECT c.id, c.fullname, c.open_points, ls.name AS skillname,
                      ll.name as levelname, ls.name, cc.timecompleted 
                      FROM {course_completions} cc
                      JOIN {course} c ON cc.course = c.id
                      JOIN {local_skill} ls ON c.open_skill = ls.id
                      JOIN {local_course_levels} ll ON c.open_level = ll.id
                      WHERE cc.userid = {$this->user->id} AND cc.timecompleted IS NOT NULL
                      ";
      	$skillsacquired = $this->db->get_records_sql($skillscountsql);
      	return count($skillsacquired);
	}

	  ////Using service.php showing data on index page instead of ajax datatables
    public function manageblockskill_content(){
        global $USER;

        // $systemcontext = context_system::instance();

        // $options = array('targetID' => 'manage_blockskill','perPage' => 5, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');
        
        // $options['methodName']='block_myskills_manageblockskill_view';
      
        // $options['templateName']='block_myskills/myskills_view'; 
        // $options = json_encode($options);

        // $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        // $filterdata = json_encode(array());

        // $context = [
        //         'targetID' => 'manage_blockskill',
        //         'options' => $options,
        //         'dataoptions' => $dataoptions,
        //         'filterdata' => $filterdata
        // ];

        $output = '<div class="manage_blockskill" id="manage_blockskill" data-region="manage_blockskill-preview-container">
                            <div data-region="manage_blockskill-count-container"></div>
                            <div data-region="manage_blockskill-list-container" id ="manageblockskillid"></div>
                        </div>';
        return  $output;
        
    }

}
