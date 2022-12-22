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
 * @subpackage local_custom_category
 */
require_once(dirname(__FILE__) . '/../../config.php');
// require_once('lib.php');

class local_custom_category_renderer extends plugin_renderer_base {

    /*
 *  @method display table for showing repositories
 *  @return skill repository table
 */
    public function get_top_action_buttons_custom_category(){
        global $CFG;

        $systemcontext =(new \local_custom_category\lib\accesslib())::get_module_context();
        $data =  "<ul class='course_extended_menu_list'>
                <li>
                    <div class='coursebackup course_extended_menu_itemcontainer'>
                          <a id='extended_menu_syncstats' title='".get_string('addnewcustom_category', 'local_custom_category')."' class='course_extended_menu_itemlink' href='javascript:void(0)' onclick ='(function(e){ require(\"local_custom_category/newcustomcategory\").init({selector:\"createrepositorymodal\", contextid:$systemcontext->id, repositoryid:0}) })(event)'><i class='icon fa fa-plus' aria-hidden='true'></i>
                          </a>
                      </div>
                </li>
            </ul>";
        return $data;
    }
    public function custom_category_content($filter = false){
        global $USER;
        $systemcontext =(new \local_custom_category\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_skills','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');

        $options['methodName']='local_custom_category_custom_category_view';
        $options['templateName']='local_custom_category/custom_category_view';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
            'targetID' => 'manage_skills',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
        ];

        if($filter){
            return  $context;
        }else{
            return  $this->render_from_template('local_costcenter/cardPaginate', $context);
        }
    }
};