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
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */
class block_trending_modules_renderer extends plugin_renderer_base {   
	public function trending_modules_content($instanceid, $filter = false){
        global $USER;

        $systemcontext = context_system::instance();

        $options = array('targetID' => 'trending_modules','perPage' => 8, 'cardClass' => 'col-md-6 col-12', 'viewType' => 'table');
        
        $options['methodName']='block_trending_modules_display_paginated';
        $options['templateName']='block_trending_modules/trending_module_paginated'; 
        $options = json_encode($options);

        $dataoptions = json_encode(array('instanceid' => $instanceid));
        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'trending_modules',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('block_trending_modules/trending_content', $context);
        }
    }
    public function display_additional_filters($filterparams){
        // return $this->render_from_template('block_trending_modules/additional_filters', $filterparams);
    }
    public function show_preference_setting_user(){
        global $PAGE;
        $PAGE->requires->js_call_amd('block_trending_modules/trending_modules', 'update_preference');
        $preference = get_user_preferences('force_dontshow_trending_modules');
        $params = array('type' => 'checkbox', 'class' => 'update_trending_preference', 'data-value' => $preference);
        if($preference){
            $params['checked'] = 'checked';
            $checked = 'checked = checked';
        }else {
            $checked = ' ';
        }
        
        return $html = '<div class="trending-checkbox">
                        <label class="trend-checkbox-label pull-left d-inline-block mr-2">
                            <input type="checkbox" class = "update_trending_preference" data-value = '.$preference.' '.$checked.'>
                            <span class="trend-checkbox-custom trend-rectangular"></span>
                        </label>
                        <span class = "e_d_lbl pull-left">'.get_string('show_hide_popup','block_trending_modules').'</span>
                    </div>';
        // return html_writer::tag('input', $html, $params);

    }
    public function get_trending_modules_button($moduletype, $moduleid, $btnelement, $tags = NULL){
        global $DB;
        if(is_null($tags)){
            $moduletags = $DB->get_fieldset_sql("SELECT ti.tagid FROM {tag_instance} AS ti WHERE ti.component LIKE :component AND ti.itemid = :itemid ", array('component' =>$moduletype, 'itemid' =>$moduleid));
            $tags = implode(',', $moduletags);
        }
        return html_writer::tag('span', $btnelement, array('data-tags' => $tags, 'class' => 'show_suggested_modules', 'data-moduletype' => $moduletype, 'data-show_suggestions' => False));
    }
}