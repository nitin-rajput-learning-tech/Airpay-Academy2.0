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
 * @subpackage  trending_courses
 * @author eabyas  <info@eabyas.in>
**/
 
global $CFG,$PAGE, $USER;
require_once("{$CFG->libdir}/formslib.php");

class block_trending_modules extends block_base {
	public function init() {
        $this->title = get_string('pluginname','block_trending_modules');
    }
    public function specialization() {
        if (isset($this->config->block_title)) {
            $this->title = format_string($this->config->block_title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('pluginname','block_trending_modules');
        }
    }
    public function get_content() {
    	global $OUTPUT, $PAGE;

    	if ($this->content !== null) {
            return $this->content;
        }
        if (is_siteadmin() || !has_capability('block/trending_modules:view', \context_system::instance())){
            return;
        }
        $this->content = new stdClass();
        // $userpreference = get_user_preferences('force_dontshow_trending_modules');
        // if(is_null($userpreference) OR $userpreference == 0){
        //     $showpopup = get_user_preferences('show_trending_modules');
        //     if($showpopup){
        //         $PAGE->requires->js_call_amd('block_trending_modules/trending_modules', 'display_popup');
        //         set_user_preference('show_trending_modules', 0);
        //     }
        // }
        // $renderer = $PAGE->get_renderer('block_trending_modules');
        // $checkbox = $renderer->show_preference_setting_user();
    	
        $lib = new block_trending_modules\lib();
    	$args = new stdClass();
        $args->config = $this->config;
        $total_modules = $lib->get_total_modules_count($args);
    	$args->limitfrom = 0;
		$args->limitnum = 3;
        $args->rateWidth = 12;
    	$data = $lib->user_trending_modules($args);
        
        $enableviewmore = $total_modules > 3 ? True : False;
        $this->content->text = "<div class='pull-right text-right' id='trending_module_search'>
            <label class='search_module_label'>".get_string('search','block_trending_modules')." : </label>
            <input id='filter_trending_modules' type='text' name='search_module' data-target='#trending_modules_content' aria-label='search_module' data-navigator='.block_trending_modules_navigator'/>
            </div>";
        $viewmore_link = (new moodle_url('/blocks/trending_modules/index.php', ['instanceid' => $this->instance->id]))->out();
    	$this->content->text .= $OUTPUT->render_from_template('block_trending_modules/block_content', array('records'=> $data, 'enableviewmore' => $enableviewmore, /*'checkbox' => $checkbox,*/ 'enableDesc' => False, 'left_arrow_enable' => False, 'right_arrow_enable' => $enableviewmore, 'total_modules' => $total_modules, 'viewmore_link' => $viewmore_link));

        $this->content->footer = '';
        return $this->content;
    }

    public function has_config() {
        return true;
    }
    /**
     * Do we allow multiple instances on the same page
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }
    /**
     * Serialize and store config data
     */
    public function instance_config_save($data, $nolongerused = false) {
        $config = clone($data);
        parent::instance_config_save($config, $nolongerused);
    }
}
