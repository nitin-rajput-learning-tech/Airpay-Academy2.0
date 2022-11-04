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
 * Contains class local_tags\output\tagareashowstandard
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tags\output;

use context_system;
use lang_string;
use local_tags_tag;
use local_tags_area;

/**
 * Class to display tag area show standard control
 *
 * @package   local_tags
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagareashowstandard extends \core\output\inplace_editable {

    /**
     * Constructor.
     *
     * @param \stdClass $tagarea
     */
    public function __construct($tagarea) {
        $editable = has_capability('moodle/tag:manage', (new \local_tags\lib\accesslib())::get_module_context());
        $edithint = new lang_string('editisstandard', 'local_tags');
        $value = $tagarea->showstandard;
        $areaname = local_tags_area::display_name($tagarea->component, $tagarea->itemtype);
        $editlabel = new lang_string('changeshowstandard', 'local_tags', $areaname);

        parent::__construct('local_tags', 'tagareashowstandard', $tagarea->id, $editable,
                null, $value, $edithint, $editlabel);

        $standardchoices = array(
            local_tags_tag::BOTH_STANDARD_AND_NOT => get_string('standardsuggest', 'tag'),
            local_tags_tag::STANDARD_ONLY => get_string('standardforce', 'tag'),
            local_tags_tag::HIDE_STANDARD => get_string('standardhide', 'tag')
        );
        $this->set_type_select($standardchoices);
    }

    /**
     * Updates the value in database and returns itself, called from inplace_editable callback
     *
     * @param int $itemid
     * @param mixed $newvalue
     * @return \self
     */
    public static function update($itemid, $newvalue) {
        global $DB;
        require_capability('moodle/tag:manage', (new \local_tags\lib\accesslib())::get_module_context());
        $tagarea = $DB->get_record('tag_area', array('id' => $itemid), '*', MUST_EXIST);
        $newvalue = clean_param($newvalue, PARAM_INT);
        $data = array('showstandard' => $newvalue);
        local_tags_area::update($tagarea, $data);
        $tagarea->showstandard = $newvalue;
        $tmpl = new self($tagarea);
        return $tmpl;
    }
}
