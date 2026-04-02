<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * 
 *
 * @package   block_achievements
 * @copyright 2017 eAbyas info solutions
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_achievements extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_achievements');
    }
    function hide_header() {
		return true;
	}
	
	
    function get_content() {
        global $CFG, $USER, $PAGE,$DB;
        $renderer = $PAGE->get_renderer('block_achievements');
		
        if ($this->content !== NULL)
            return $this->content;

        if (!is_object($this->content)) 
        $this->content = new stdClass;
		
		if(!is_siteadmin()){
			$this->content->text[] = $renderer->my_achievements_tabs();
		}else{
			$this->content->text[] = '';
		}
        $this->content->text = implode('<br>', $this->content->text);
        return $this->content;
    }
	public function get_required_javascript() {
        global $USER,$PAGE;
       	$PAGE->requires->jquery();
	 	$PAGE->requires->js('/blocks/achievements/js/jquery-ui.min.js',true);
		$PAGE->requires->jquery_plugin('ui');
	 	$PAGE->requires->jquery_plugin('ui-css');
		
	   	$this->page->requires->js_call_amd('block_achievements/certifications', 'achinfotable', array($USER->id));
    }
}
