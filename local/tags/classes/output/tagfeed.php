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
 * Contains class local_tags\output\tagfeed
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tags\output;

use templatable;
use renderer_base;
use stdClass;

/**
 * Class to display feed of tagged items
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tagfeed implements templatable {

    /** @var array */
    protected $items;

    /**
     * Constructor
     *
     * Usually the most convenient way is to call constructor without arguments and
     * add items later using add() method.
     *
     * @param array $items
     */
    public function __construct($items = array(), $component = 'tagtable') {
        $this->items = array();
        $this->tableid = $component;
        if ($items) {
            foreach ($items as $item) {
                $item = (array)$item + array('img' => '', 'heading' => '', 'details' => '');
                $this->add($item['img'], $item['heading'], $item['details']);
            }
        }
    }

    /**
     * Adds one item to the tagfeed
     *
     * @param string $img HTML code representing image (or image wrapped in a link), note that
     *               local_tags/tagfeed template expects image to be 35x35 px
     * @param string $heading HTML for item heading
     * @param string $details HTML for item details (keep short)
     */
    public function add($img, $heading, $details = '') {
        $this->items[] = array('img' => $img, 'heading' => $heading, 'details' => $details);
        
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        return array('items' => $this->items, 'tableid'=>$this->tableid);
    }
}
