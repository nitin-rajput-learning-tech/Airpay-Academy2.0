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
 * @subpackage  my_event_calendar
 * @author eabyas  <info@eabyas.in>
**/
 
global $CFG,$PAGE, $USER;
require_once("{$CFG->libdir}/formslib.php");

class block_my_event_calendar extends block_base {
    public function init() {
        $this->title = get_string('blocktitle','block_my_event_calendar');
    }
	public function get_content() {
        global $CFG, $USER, $COURSE, $PAGE, $DB;
		if ($this->content !== null) {
            return $this->content;
        }
		$context = context_system::instance();
		
		// $PAGE->requires->js_call_amd('local_classroom/classroom','load', array());
        
        $this->content = new stdClass;
        $this->content->text = '';		
		$this->content->text .= "<div class='col-12 pull-left p-0'>
			<div id='calendar' class='col-lg-12 col-xl-6 col-md-12 col-12 pull-left'></div>
			<div class='col-lg-12 col-xl-6 col-md-12 col-12 pull-left events_content'>
			<p id='eventInfo'><div id='pop_desc'></div></p></div>
			
		</div>";

        return $this->content;
    }
	
	public function get_required_javascript() {
		global $PAGE;
		$this->page->requires->jquery();
		$this->page->requires->js('/blocks/my_event_calendar/js/moment.min.js', true);
		$this->page->requires->strings_for_js(['january','february','march','april','may', 'june', 'july', 'august', 'september', 'october', 'november' ,'december'], 'block_my_event_calendar');
		$this->page->requires->js('/blocks/my_event_calendar/js/fullcalendar.min.js', true);
		$this->page->requires->js('/blocks/my_event_calendar/js/custom.min.js');
		$this->page->requires->js('/blocks/my_event_calendar/js/jquery.dataTables.min.js', true);
		$this->page->requires->js_call_amd('block_my_event_calendar/event_popup', 'load');
		$this->page->requires->strings_for_js(array('no_events_sheduled','sun','mon','tue','wed','thu','fri','sat'), 'block_my_event_calendar');
		$plugins = \block_my_event_calendar\calendarlib::event_calendar_plugin_details(array('classroom'));
		if($plugins['classroom']){
			$this->page->requires->js_call_amd('local_classroom/classroom','load', array());
		}
        $this->page->requires->css('/blocks/my_event_calendar/css/fullcalendar.min.css');
	}
}
