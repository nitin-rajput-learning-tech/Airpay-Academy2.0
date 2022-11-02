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
 * @subpackage local_request
 */

namespace local_request\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use renderable;

/**
 * Renderer class for learning plans
 *
 * @package    local_competency
 * @copyright  2015 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     *
     * @param manage_competency_frameworks_page $page
     *
     * @return string html for the page
     */
    // public function render_requestview(\local_request\output\requestview $page) {
    //     $data = $page->export_for_template($this);

    //     return parent::render_from_template('local_request/requestview', $data);
    // }
    public function render_requestview($filter, $courseid=null,$component=null){
        global $USER;
        $systemcontext = \context_system::instance();
        $options = array('targetID' => 'request_view','perPage' => 12, 'cardClass' => 'col-lg-3 col-md-4 col-sm-6 col-12', 'viewType' => 'card');

        $options['methodName']='local_request_manage_view';
        $options['templateName']='local_request/requestview';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id, 'courseid' => $courseid, 'component' => $component));
        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'request_view',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }

} // end of class
