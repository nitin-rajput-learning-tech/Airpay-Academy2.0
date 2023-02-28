<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This trainerdashboard is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This trainerdashboard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this trainerdashboard.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author eabyas  <info@eabyas.in>
 * @package Bizlms 
 * @subpackage block_trainerdashboard
 */
class block_trainerdashboard_edit_form extends block_edit_form {
 
    protected function specific_definition($mform) {

    	global $CFG, $DB, $PAGE;

        $context = (new \local_costcenter\lib\accesslib())::get_module_context();
 
        // Section header title according to language file.
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));
 
        // A sample string variable with a default value.
        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_trainerdashboard'));
        $mform->setDefault('config_title',get_string('trainerdashboard', 'block_trainerdashboard'));
        $mform->setType('config_title', PARAM_RAW);   


         // A sample string variable with a default value.
        $mform->addElement('text', 'config_title_trainer', get_string('config_title_trainer', 'block_trainerdashboard'));
        $mform->setDefault('config_title_trainer',get_string('trainerdashboard', 'block_trainerdashboard'));
        $mform->setType('config_title_trainer', PARAM_RAW);  



        $trainerdashboards = array();
        $trainerdashboards[0] = 'Select Trainer Dashboard';

        if((has_capability('block/trainerdashboard:viewtrainerslist', $context))) {
           
            $trainerdashboards[block_trainerdashboard_manager::TRAINERLIST] = 'List the trainer Details.';
        }

        if((has_capability('block/trainerdashboard:viewconductedtrainings', $context))) {

            $trainerdashboards[block_trainerdashboard_manager::CONDUCTEDTRAININGS] = 'Count of Training conducted in last 3 to 6Months and their stats details ';
        }

        if((has_capability('block/trainerdashboard:viewtrainermanhours', $context))) {

            $trainerdashboards[block_trainerdashboard_manager::TRAINERMANHOURS] = 'Trainer wise Manhours spend list ';
        }

        // if((has_capability('block/trainerdashboard:viewdepttrainingavg', $context))) {

        //     $trainerdashboards[block_trainerdashboard_manager::DEPTTRAININGAVG] = 'Department wise training averages';
        // }

        if((has_capability('block/trainerdashboard:viewupcomingtrainings', $context))) {

            $trainerdashboards[block_trainerdashboard_manager::UPCOMINGTRAININGS] = 'Next 3 to 6monts Scheduled training list and their stats';
        }


        $mform->addElement('select', 'config_trainerdashboardlist', get_string('listofdashboards', 'block_trainerdashboard'), $trainerdashboards); 
 
    }
    public function set_data($defaults) {

        if (!$this->block->user_can_edit() && !empty($this->block->config->title)) {
            // If a title has been set but the user cannot edit it format it nicely.
            $title = $this->block->config->title;
            $defaults->config_title = format_string($title, true, $this->page->context);
            // Remove the title from the config so that parent::set_data doesn't set it.
            unset($this->block->config->title);
        }
        parent::set_data($defaults);
        // Restore $text.
        if (!isset($this->block->config)) {
            $this->block->config = new stdClass();
        }
        if (isset($title)) {
            // Reset the preserved title.
            $this->block->config->title = $title;
        }
    }
}