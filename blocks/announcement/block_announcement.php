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
 * @subpackage blocks_announcement
 */
require_once(dirname(__FILE__) . '/../../config.php');
global $DB, $CFG, $OUTPUT, $PAGE;
//$PAGE->requires->css('/blocks/technical_support/font-awesome/css/font-awesome.min.css');
require_once($CFG->dirroot . '/blocks/announcement/lib.php');
class block_announcement extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_announcement');
    }

    public function get_content() {
        global $CFG,$PAGE;
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = array();
        $systemcontext = (new \block_announcement\lib\accesslib())::get_module_context();
        // $PAGE->set_context($systemcontext);
        $renderer = $PAGE->get_renderer('block_announcement');
       
        $this->content->text = $renderer->announcements_view(1,3);
        // $this->content->text = 'shivani';

        return $this->content;
    }

}
