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
 * @package   Bizlms
 * @subpackage  trending_modules
 * @author eabyas  <info@eabyas.in>
**/
class block_trending_modules_edit_form extends block_edit_form {

    protected function specific_definition($mform) {

        // Load defaults.
        $blockconfig = get_config('block_trending_modules');

        // Fields for editing HTML block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_block_title', get_string('custom_block_title', 'block_trending_modules'));
        $mform->setType('config_block_title', PARAM_TEXT);
        if(isset($blockconfig->block_title))
            $mform->setDefault('config_block_title', $blockconfig->block_title);

        // $types = array('trending_modules' => get_string('trending_modules', 'block_trending_modules'),
        //     'suggested_modules' => get_string('suggested_modules', 'block_trending_modules'),
        //     'both' => get_string('all')
        // );
        // $mform->addElement('select', 'config_modules_type', get_string('custom_modules_toshow', 'block_trending_modules'), $types);
        // if(isset($blockconfig->modules_type))
        //     $mform->setDefault('config_modules_type',  $blockconfig->modules_type);
        // print_object($blockconfig);
        $mform->addElement('hidden', 'config_modules_type', 'trending_modules');
        $mform->setType('config_modules_type', PARAM_TEXT);

        $mform->addElement('text', 'config_rating', get_string('ratings_from', 'block_trending_modules'), $types);
        $mform->setType('config_rating', PARAM_TEXT);
        $mform->addHelpButton('config_rating', 'ratings_from', 'block_trending_modules');
        $mform->addRule('config_rating', null, 'numeric', null, 'client');
        $mform->addRule('config_rating', get_string('minrating_validation', 'block_trending_modules'), 'rangelength', array(1,5), 'client');
        $mform->hideIf('config_rating', 'config_modules_type', 'eq', 'suggested_modules');


        $mform->addElement('text', 'config_minenrollments', get_string('min_enrollments', 'block_trending_modules'), $types);
        $mform->setType('config_minenrollments', PARAM_TEXT);
        $mform->addHelpButton('config_minenrollments', 'min_enrollments', 'block_trending_modules');
        $mform->addRule('config_minenrollments', null, 'numeric', null, 'client');
        $mform->addRule('config_minenrollments', null, 'nonzero', null, 'client');
        $mform->hideIf('config_minenrollments', 'config_modules_type', 'eq', 'suggested_modules');

        $mform->addElement('text', 'config_mincompletions', get_string('min_completions', 'block_trending_modules'), $types);
        $mform->setType('config_mincompletions', PARAM_TEXT);
        $mform->addHelpButton('config_mincompletions', 'min_completions', 'block_trending_modules');
        $mform->addRule('config_mincompletions', null, 'numeric', null, 'client');
        $mform->addRule('config_mincompletions', null, 'nonzero', null, 'client');
        $mform->hideIf('config_mincompletions', 'config_modules_type', 'eq', 'suggested_modules');
    }
}
