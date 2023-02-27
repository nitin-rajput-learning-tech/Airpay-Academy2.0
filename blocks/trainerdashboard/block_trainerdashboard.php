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
class block_trainerdashboard extends block_base {
    public function init() {
        $this->title = get_string('trainerdashboard', 'block_trainerdashboard');
    }
    public function get_required_javascript() {

        // $this->page->requires->jquery();
        // $this->page->requires->jquery_plugin('ui-css');
        // $this->page->requires->js_call_amd('block_trainerdashboard/trainerdashboard', 'init',array());
    
    }
       
    /**
     * Global Config?
     *
     * @return boolean
     * */
    public function has_config() {
        return true;
    }
    /**
     * Where to add the block
     *
     * @return boolean
     * */
    public function applicable_formats() {
        return array('all' => true);
    }
 
    public function specialization() {

        $context = (new \local_costcenter\lib\accesslib())::get_module_context();

        if(!is_siteadmin() && has_capability('local/classroom:trainer_viewclassroom', $context)) {

            $this->title = isset($this->config->title_trainer) ? format_string($this->config->title_trainer) : format_string(get_string('trainerdashboard', 'block_trainerdashboard'));
        }else{

            $this->title = isset($this->config->title) ? format_string($this->config->title) : format_string(get_string('trainerdashboard', 'block_trainerdashboard'));
        }
	}
    /**
     * More than one instance per page?
     *
     * @return boolean
     * */
    public function instance_allow_multiple() {
        return true;
    }
    public function hide_header() {
        return false;
    }
    

    // The PHP tag and the curly bracket for the class definition 
    // will only be closed after there is another function added in the next section.
    public function get_content() {
    	global $CFG, $DB, $PAGE, $USER, $COURSE;

    	$context = (new \local_costcenter\lib\accesslib())::get_module_context();
        $renderer = $this->page->get_renderer('block_trainerdashboard');

	    if ($this->content !== null) {
	      return $this->content;
	    }
	 
	    $this->content = new stdClass;
        $this->content->footer = '';
        $this->content->text = "";

        $trainerdashboardtype = $this->config->trainerdashboardlist;

        if (isset($this->config->trainerdashboardlist) && $this->config->trainerdashboardlist && (has_capability('block/trainerdashboard:view'.$trainerdashboardtype.'', $context))) {
     
            $instanceid = $this->instance->id;
   
            $this->content->text .= '<div class = "trainerdashboard_header">';

            $this->content->text .= $renderer->get_trainerdashboards($trainerdashboardtype);

            $this->content->text .= "</div>";
 
        } else {
            if (is_siteadmin()) {
                $this->content->text .= get_string('configurationmessage', 'block_trainerdashboard');
            } else {
                $this->content->text .= '';
            }
        }
        return $this->content;
	}
	/**
     * Serialize and store config data
     */
    public function instance_config_save($data, $nolongerused = false) {
        global $DB;
        $config = clone ($data);
        parent::instance_config_save($config, $nolongerused);
    }
    public function content_is_trusted() {
        global $SCRIPT;

        if (!$context = context::instance_by_id($this->instance->parentcontextid, IGNORE_MISSING)) {
            return false;
        }
        //find out if this block is on the profile page
        if ($context->contextlevel == CONTEXT_USER) {
            if ($SCRIPT === '/blocks/trainerdashboard/dashboard.php') {
                // this is exception - page is completely private, nobody else may see content there
                // that is why we allow JS here
                return true;
            } else {
                // no JS on public personal pages, it would be a big security issue
                return false;
            }
        }
        return true;
    }
    /**
     * The block should only be dockable when the title of the block is not empty
     * and when parent allows docking.
     * @return bool
     */
    public function instance_can_be_docked() {
        return false;
    }
    /*
     * Add custom trainerdashboard attributes to aid with theming and styling
     * @return array
    */
    public function trainerdashboard_attributes() {
        global $CFG;
        $attributes = parent::trainerdashboard_attributes();
        if (!empty($CFG->block_trainerdashboard_allowcssclasses)) {
            if (!empty($this->config->classes)) {
                $attributes['class'] .= ' ' . $this->config->classes;
            }
        }
        return $attributes;
    }
}