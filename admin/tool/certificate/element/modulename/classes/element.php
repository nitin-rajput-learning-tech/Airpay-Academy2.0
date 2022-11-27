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
 * @subpackage local_certificates
 */

namespace certificateelement_modulename;

defined('MOODLE_INTERNAL') || die();

class element extends \tool_certificate\element {

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     * @param obj $moduleinfo having information with moduletype and moduleid
     */
    public function render($pdf, $preview, $user, $moduleinfo = false) {
       
        $modulename = \tool_certificate\element_helper::get_modulename($this->get_id(),$user, $moduleinfo);
        \tool_certificate\element_helper::render_content($pdf, $this,  $modulename);
    }

    public function get_moduleinfo(){
        global $DB;

        $modinfo = new \stdClass();

        $modinfo->modulename = $this->modulename;
        $modinfo->moduletype = $this->moduletype;
        $modinfo->moduleid = $this->moduleid;
        
       
        return $modinfo;

    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        global $COURSE;

        $modulename = \tool_certificate\element_helper::get_modulename($this->get_id(), 2, $this->get_moduleinfo());
        return \tool_certificate\element_helper::render_html_content($this, $modulename);
    }
}
