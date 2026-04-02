<?php
namespace local_program\task;
class programcompletion extends \core\task\scheduled_task {
	public function get_name() {
        return get_string('taskprogramcompletion', 'local_program');
    }
    public function execute(){
    	$programcompletion = new \local_program\local\completion();
    	$programcompletion->program_course_completion_task();
    }
}