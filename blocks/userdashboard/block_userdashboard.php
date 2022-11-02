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
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage block_userdashboard
 */


class block_userdashboard extends block_base {
	public function init() {
		$this->title = get_string('userdashboard', 'block_userdashboard');
	}

	function hide_header() {
		return true;
	}

	function instance_allow_multiple() {
		return false;
	}

	public function get_content() {
		if ($this->content !== null) {
			return $this->content;
		}

		$systemcontext = context_system::instance();
		if (is_siteadmin() || !(has_capability('block/userdashboard:view', $systemcontext))) {
			return '';
		}
		global $CFG, $PAGE;
		$this->content = new stdClass;

		$renderer = $PAGE->get_renderer('block_userdashboard');
		$PAGE->requires->js_call_amd('block_userdashboard/userdashboardnav', 'load');
		// $courses = "elearning_courses";
		// $curr_tab = "courses_inprogress";
		// $main_tab_contents = '';
		// $main_tab_contents .= '<div class="col-md-9 col-12 pull-left" id="linked_course_details_info">';
		// $main_tab_contents .= $renderer->dashboard_for_endusers($courses, $curr_tab);
		// $main_tab_contents .= '</div>';

		// $this->content->text = '<div class="">'.$renderer->userdashboard_view().$main_tab_contents.'</div>';
		$this->content->text = '<div class="">'.$renderer->userdashboard_view().'</div>';

		return $this->content;
	}

	public function get_required_javascript() {
	    // $this->page->requires->jquery();
        // $this->page->requires->js_call_amd('block_userdashboard/userdashboardinit', 'init');
        $this->page->requires->js_call_amd('block_userdashboard/userdashboard', 'init');
	}

}
