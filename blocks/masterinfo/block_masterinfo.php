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
 * Block for displayed logged in user's course completion status.
 * Displays overall, and individual criteria status for logged in user.
 *
 * @package    block_completionstatus
 * @copyright  2009-2012 Catalyst IT Ltd
 * @author     Aaron Barnes <aaronb@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use \block_masterinfo\output\quick_links as quick_links;

class block_masterinfo extends block_base {
	
	function init() {
        $this->title = get_string('pluginname', 'block_masterinfo');
    }
    function get_content() {
    	global $CFG, $DB,$USER;
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
		$systemcontext = \local_costcenter\lib\accesslib::get_module_context();
		if(is_siteadmin() || has_capability('block/masterinfo:addinstance', $systemcontext)){
			$quick_links = new quick_links();
			$this->content->text = $quick_links->display_masterinfo_links();
		}else{
			$this->content->text = '';
		}
		$this->content->footer = '';
		return $this->content;

	}
}