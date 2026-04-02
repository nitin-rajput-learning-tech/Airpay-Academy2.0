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
 * Contains class local_tags_areas_table
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Table with the list of available tag areas for "Manage tags" page.
 *
 * @package   local_tags
 * @copyright 2019 eAbyas <eAbyas.in>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_tags_areas_table extends html_table {

    /**
     * Constructor
     *
     * @param string|moodle_url $pageurl
     */
    public function __construct($pageurl) {
        global $OUTPUT;
        parent::__construct();

        $this->attributes['class'] = 'generaltable tag-areas-table';

        $this->head = array(
            get_string('tagareaname', 'local_tags'),
            get_string('component', 'local_tags'),
            get_string('tagareaenabled', 'local_tags'),
            get_string('tagcollection', 'local_tags'),
            get_string('showstandard', 'local_tags') .
                $OUTPUT->help_icon('showstandard', 'local_tags')
        );

        $this->data = array();
        $this->rowclasses = array();

        $tagareas = local_tags_area::get_areas();
        $tagcollections = local_tags_collection::get_collections_menu(true);
        $tagcollectionsall = local_tags_collection::get_collections_menu();

        $standardchoices = array(
            local_tags_tag::BOTH_STANDARD_AND_NOT => get_string('standardsuggest', 'local_tags'),
            local_tags_tag::STANDARD_ONLY => get_string('standardforce', 'local_tags'),
            local_tags_tag::HIDE_STANDARD => get_string('standardhide', 'local_tags')
        );

        foreach ($tagareas as $itemtype => $it) {
            foreach ($it as $component => $record) {
                $areaname = local_tags_area::display_name($record->component, $record->itemtype);

                $tmpl = new \local_tags\output\tagareaenabled($record);
                $enabled = $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));

                $tmpl = new \local_tags\output\tagareacollection($record);
                $collectionselect = $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));

                $tmpl = new \local_tags\output\tagareashowstandard($record);
                $showstandardselect = $OUTPUT->render_from_template('core/inplace_editable', $tmpl->export_for_template($OUTPUT));

                $this->data[] = array(
                    $areaname,
                    ($record->component === 'core' || preg_match('/^core/', $record->component)) ?
                        get_string('coresystem') : get_string('pluginname', $record->component),
                    $enabled,
                    $collectionselect,
                    $showstandardselect
                );
                $this->rowclasses[] = $record->enabled ? '' : 'dimmed_text';
            }
        }
    }
}
