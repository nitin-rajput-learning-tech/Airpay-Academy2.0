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
 * @package Bizlms 
 * @subpackage local_program
 */
namespace local_program\output;
defined('MOODLE_INTERNAL') || die;
use context_system;
use renderable;
use renderer_base;
use stdClass;
use templatable;

class program implements renderable, templatable {
    /**
     * [__construct description]
     * @method __construct
     */
    public function __construct() {
        $this->context = context_system::instance();
        $this->plugintype = 'local';
        $this->plugin_name = 'program';
    }
    /**
     * [export_for_template description]
     * @method export_for_template
     * @param  renderer_base       $output [description]
     * @return [type]                      [description]
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->contextid = $this->context->id;
        $data->plugintype = $this->plugintype;
        $data->plugin_name = $this->plugin_name;
        $data->creataprogram = ((has_capability('local/program:manageprogram',
            context_system::instance()) && has_capability('local/program:createprogram',
            context_system::instance())) || is_siteadmin()) ? true : false;
        return $data;
    }
}