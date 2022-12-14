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
 * @subpackage local_myteam
 */


defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . '/formslib.php');
// Count users display with staus of that moduletype//
/**
 * @param  [type] $args       object of sending from service
 */
function local_myteam_output_fragment_users_display_modulewise($args){
        global $CFG, $USER, $PAGE, $DB,$OUTPUT;
        $args = (object) $args;
        $userid = $args->id;
        $systemcontext =(new \local_costcenter\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'display_moduledata_'.$args->moduletype,'perPage' => 5, 'cardClass' => 'tableformat', 'viewType' => 'table');
        $options['methodName']='local_myteam_myteamdisplaymodule_view';
        $options['templateName']='local_myteam/myteamdisplaymoduleview'; 
        $options = json_encode($options);
        $dataoptions = json_encode(array('userid' =>$userid,'moduletype' => $args->moduletype,'contextid' => $systemcontext->id));
        
        $filterdata = json_encode(array());
        $context = [
                'targetID' => 'display_moduledata_'.$args->moduletype,
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        //print_r($context);exit;

        return  $OUTPUT->render_from_template('local_costcenter/cardPaginate', $context);
}
//ended by sharath here

function local_myteam_leftmenunode(){
    global $USER, $DB;
    $systemcontext = (new \local_costcenter\lib\accesslib())::get_module_context();
    $myteamnode = '';
    $is_supervisor = $DB->record_exists('user', array('open_supervisorid' => $USER->id));
     if($is_supervisor) {
        $myteamnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_myteam', 'class'=>'pull-left user_nav_div myteam'));
            $myteam_url = new moodle_url('/local/myteam/team.php');
            $myteam = html_writer::link($myteam_url, '<i class="fa fa-users" aria-hidden="true"></i><span class="user_navigation_link_text">'.get_string('manage_myteam','local_myteam').'</span>',array('class'=>'user_navigation_link'));
            $myteamnode .= $myteam;
        $myteamnode .= html_writer::end_tag('li');
    }
    return array('2' => $myteamnode);
}

