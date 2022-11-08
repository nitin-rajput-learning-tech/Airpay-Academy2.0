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
 * Contains class local_tags\output\tagareaenabled
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tags\output;

use context_system;

/**
 * Class to display tag area enabled control
 *
 * @package   local_tags
 * @copyright 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagareaenabled extends \core\output\inplace_editable {

    /**
     * Constructor.
     *
     * @param \stdClass $tagarea
     */
    public function __construct($tagarea) {
        $editable = has_capability('moodle/tag:manage', (new \local_tags\lib\accesslib())::get_module_context());
        $value = $tagarea->enabled ? 1 : 0;

        parent::__construct('local_tags', 'tagareaenable', $tagarea->id, $editable, '', $value);
        $this->set_type_toggle();
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        if ($this->value) {
            $this->edithint = get_string('disable');
            $this->displayvalue = $output->pix_icon('i/hide', get_string('disable'));
        } else {
            $this->edithint = get_string('enable');
            $this->displayvalue = $output->pix_icon('i/show', get_string('enable'));
        }

        return parent::export_for_template($output);
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
        $newvalue = $newvalue ? 1 : 0;
        $data = array('enabled' => $newvalue);
        \local_tags_area::update($tagarea, $data);
        $tagarea->enabled = $newvalue;
        $tmpl = new self($tagarea);
        return $tmpl;
    }
}
