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
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program. If not, see <http://www.gnu.org/licenses/>.
*
* @author eabyas <info@eabyas.in>
* @package BizLMS
* @subpackage block_quick_navigation
*/

defined('MOODLE_INTERNAL') || die();
use \block_quick_navigation\output\quick_links as quick_links;

class block_quick_navigation extends block_base {

	function init() {
		$this->title = get_string('pluginname', 'block_quick_navigation');
	}

	function instance_allow_multiple() {
		return false;
	}

	function hide_header() {
		return true;
	}
	function get_content() {
		
		if ($this->content !== NULL) {
			return $this->content;
		}

		$this->content = new stdClass();
		$systemcontext = context_system::instance();
		if(is_siteadmin() || has_capability('block/quick_navigation:viewquicknavigation', $systemcontext)){
			$this->page->requires->js_call_amd('block_quick_navigation/blocklist_count', 'init', array());
			$quick_links = new quick_links();

			$this->content->text = $quick_links->display_quick_navigation_links();
		}else{
			$this->content->text = '';
		}
		$this->content->footer = '';
		return $this->content;
	}
}
