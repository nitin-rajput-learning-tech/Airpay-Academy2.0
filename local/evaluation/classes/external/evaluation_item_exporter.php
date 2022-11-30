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
 * Class for exporting a feedback item (question).
 *
 * @package    local_evaluation
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_evaluation\external;
defined('MOODLE_INTERNAL') || die();

use local_evaluation\feedback;
use core\external\exporter;
use renderer_base;
use core_files\external\stored_file_exporter;

/**
 * Class for exporting a feedback item (question).
 *
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluation_item_exporter extends exporter {

    protected static function define_properties() {
        return array(
            'id' => array(
                'type' => PARAM_INT,
                'description' => get_string('record_id', 'local_evaluation'),
            ),
            'feedback' => array(
                'type' => PARAM_INT,
                'description' => get_string('feedback_instance_records_belongs_to', 'local_evaluation'),
                'default' => 0,
            ),
            'template' => array(
                'type' => PARAM_INT,
                'description' => get_string('if_template_template_id', 'local_evaluation'),
                'default' => 0,
            ),
            'name' => array(
                'type' => PARAM_RAW,
                'description' => get_string('item_name', 'local_evaluation'),
            ),
            'label' => array(
                'type' => PARAM_NOTAGS,
                'description' => get_string('item_label', 'local_evaluation'),
            ),
            'presentation' => array(
                'type' => PARAM_RAW,
                'description' => get_string('text_describing_item_or_answer', 'local_evaluation'),
            ),
            'typ' => array(
                'type' => PARAM_ALPHA,
                'description' => get_string('item_type', 'local_evaluation'),
            ),
            'hasvalue' => array(
                'type' => PARAM_INT,
                'description' => get_string('has_value_or_not', 'local_evaluation'),
                'default' => 0,
            ),
            'position' => array(
                'type' => PARAM_INT,
                'description' => get_string('position_in_the_list_questons', 'local_evaluation'),
                'default' => 0,
            ),
            'required' => array(
                'type' => PARAM_BOOL,
                'description' => get_string('item_required_or_not', 'local_evaluation'),
                'default' => 0,
            ),
            'dependitem' => array(
                'type' => PARAM_INT,
                'description' => get_string('item_id_depend_on', 'local_evaluation'),
                'default' => 0,
            ),
            'dependvalue' => array(
                'type' => PARAM_RAW,
                'description' => get_string('depend_value', 'local_evaluation'),
            ),
            'options' => array(
                'type' => PARAM_ALPHA,
                'description' => get_string('additional_settings_for_item', 'local_evaluation'),
            ),
        );
    }

    protected static function define_related() {
        return array(
            'context' => 'context',
            'itemnumber' => 'int?'
        );
    }

    protected static function define_other_properties() {
        return array(
            'itemfiles' => array(
                'type' => stored_file_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'itemnumber' => array(
                'type' => PARAM_INT,
                'description' => get_string('item_position_number', 'local_evaluation'),
                'null' => NULL_ALLOWED
            ),
            'otherdata' => array(
                'type' => PARAM_RAW,
                'description' => get_string('additional_data_required_by_external_functions', 'local_evaluation'),
                'null' => NULL_ALLOWED
            ),
        );
    }

    protected function get_other_values(renderer_base $output) {
        $context = $this->related['context'];

        $itemobj = evaluation_get_item_class($this->data->typ);
        $values = array(
            'itemfiles' => array(),
            'itemnumber' => $this->related['itemnumber'],
            'otherdata' => $itemobj->get_data_for_external($this->data),
        );

        $fs = get_file_storage();
        $files = array();
        $itemfiles = $fs->get_area_files($context->id, 'local_evaluation', 'item', $this->data->id, 'filename', false);
        if (!empty($itemfiles)) {
            foreach ($itemfiles as $storedfile) {
                $fileexporter = new stored_file_exporter($storedfile, array('context' => $context));
                $files[] = $fileexporter->export($output);
            }
            $values['itemfiles'] = $files;
        }

        return $values;
    }

    /**
     * Get the formatting parameters for the name.
     *
     * @return array
     */
    protected function get_format_parameters_for_name() {
        return [
            'component' => 'local_evaluation',
            'filearea' => 'item',
            'itemid' => $this->data->id
        ];
    }

    /**
     * Get the formatting parameters for the presentation.
     *
     * @return array
     */
    protected function get_format_parameters_for_presentation() {
        return [
            'component' => 'local_evaluation',
            'filearea' => 'item',
            'itemid' => $this->data->id
        ];
    }
}
