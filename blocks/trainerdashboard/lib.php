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
defined('MOODLE_INTERNAL') || die();
/*
* Author Rizwana
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function block_trainerdashboard_leftmenunode(){
    $context = (new \local_costcenter\lib\accesslib())::get_module_context();

    if (has_capability('local/classroom:trainer_viewclassroom', $context) && !is_siteadmin()) {
        $header = get_string('mytrainerdashboard', 'block_trainerdashboard');
    }else{
        $header = get_string('trainerdashboard', 'block_trainerdashboard');
    }
    $trainerdashboardnode = '';
    if(is_siteadmin() || has_capability('block/trainerdashboard:viewtrainerslist', $context)) {
        $trainerdashboardnode .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browseclassrooms', 'class'=>'pull-left user_nav_div browseclassrooms'));
            $trainerdashboard_url = new moodle_url('/blocks/trainerdashboard/dashboard.php');
            $trainerdashboard_icon = '<span class="classroom_icon_wrap"></span>';
            $trainerdashboard = html_writer::link($trainerdashboard_url, $trainerdashboard_icon.'<i class="fa fa-user-o" aria-hidden="true" aria-label=""></i><span class="user_navigation_link_text">'.$header.'</span>',array('class'=>'user_navigation_link'));
            $trainerdashboardnode .= $trainerdashboard;
        $trainerdashboardnode .= html_writer::end_tag('li');
    }

    return array('25' => $trainerdashboardnode);
}
function block_trainerdashboard_output_fragment_trainerslist($args){
    global $DB, $CFG, $PAGE, $OUTPUT;
    $args = (object) $args;
    $context = (new \local_costcenter\lib\accesslib())::get_module_context();

    $options = array('targetID' => 'classrooms_tabdata','perPage' => 5, 'cardClass' => 'col-md-6 col-12', 'viewType' => 'card');

    $options['templateName']='block_trainerdashboard/viewclassrooms';
    $options['methodName']='block_trainerdashboard_get_classrooms';
    $options = json_encode($options);

    $dataoptions = json_encode(array('tabname' =>'classrooms','classroomid' => $args->id,'triggertype'=>$args->triggertype));

    $filterdata = json_encode(array());

    $context = [
            'targetID' => 'classrooms_tabdata',
            'options' => $options,
            'dataoptions' => $dataoptions,
            'filterdata' => $filterdata
    ];

    return  $OUTPUT->render_from_template('local_costcenter/cardPaginate', $context);
}
function block_trainerdashboard_output_fragment_trainerdashboardchart($args){
    global $DB, $CFG, $PAGE, $OUTPUT;
    $args = (object) $args;

    
    $context = (new \local_costcenter\lib\accesslib())::get_module_context();
    $context = $args->context;

    $trainings = json_decode($args->chartdata);
 
    $totaltrainings = new \core\chart_series('Total Trainings', [$trainings->totaltrainings]);
    $completedtrainings = new \core\chart_series('Completed Trainings', [$trainings->conductedtrainings]);
    $upcomingtrainings = new \core\chart_series('Upcoming Trainings', [$trainings->upcomingtrainings]);
    $userscovered = new \core\chart_series('Users Covered', [$trainings->totaluserscovered]);
    $chart = new \core\chart_bar();
    $chart->set_title('Trainings Chart');
    $chart->add_series($totaltrainings);
    $chart->add_series($completedtrainings);
    $chart->add_series($upcomingtrainings);
    $chart->add_series($userscovered);

    // Customise X axis.
    $xaxis = new \core\chart_axis();
    $xaxis->set_stepsize(10);
    $chart->set_xaxis($xaxis);
     
    // Customise Y axis.
    $yaxis = new \core\chart_axis();
    $yaxis->set_stepsize(50);
    $chart->set_yaxis($yaxis);


    $o = '';
    ob_start();
    $o .= $OUTPUT->render_chart($chart, false);;
    $o .= ob_get_contents();
    ob_end_clean();

    return $o;
}
