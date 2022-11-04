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
 * Contains class local_tags\output\tagareacollection
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tags\output;

use context_system;
use lang_string;
use local_tags_area;

/**
 * Class to display collection select for the tag area
 *
 * @package   local_tags
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagareacollection extends \core\output\inplace_editable {

    /**
     * Constructor.
     *
     * @param \stdClass $tagarea
     */
    public function __construct($tagarea) {
        if (!empty($tagarea->locked)) {
            // If the tag collection for the current tag area is locked, display the
            // name of the collection without possibility to edit it.
            $tagcoll = \local_tags_collection::get_by_id($tagarea->tagcollid);
            parent::__construct('local_tags', 'tagareacollection', $tagarea->id, false,
                \local_tags_collection::display_name($tagcoll), $tagarea->tagcollid);
            return;
        }

        $tagcollections = \local_tags_collection::get_collections_menu(true);
        $editable = (count($tagcollections) > 1) &&
                has_capability('moodle/tag:manage', (new \local_tags\lib\accesslib())::get_module_context());
        $areaname = local_tags_area::display_name($tagarea->component, $tagarea->itemtype);
        $edithint = new lang_string('edittagcollection', 'local_tags');
        $editlabel = new lang_string('changetagcoll', 'local_tags', $areaname);
        $value = $tagarea->tagcollid;

        parent::__construct('local_tags', 'tagareacollection', $tagarea->id, $editable,
                null, $value, $edithint, $editlabel);
        $this->set_type_select($tagcollections);
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
        $tagcollections = \local_tags_collection::get_collections_menu(true);
        if (!array_key_exists($newvalue, $tagcollections)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        $data = array('tagcollid' => $newvalue);
        local_tags_area::update($tagarea, $data);
        $tagarea->tagcollid = $newvalue;
        $tmpl = new self($tagarea);
        return $tmpl;
    }
}
