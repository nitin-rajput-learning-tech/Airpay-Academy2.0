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
 * @package Bizlms 
 * @subpackage local_ratings
 */
namespace local_ratings\output;
use html_writer;
use user_course_details;
class renderer extends \plugin_renderer_base {
	public function view_reviews($filter = False){
		$itemid = optional_param('itemid', 0, PARAM_INT);
		$commentarea = optional_param('commentarea', 0, PARAM_TEXT);
        $systemcontext = \context_system::instance();

        $options = array('targetID' => 'view_reviews','perPage' => 8, 'cardClass' => 'col-md-12 col-sm-6', 'viewType' => 'card' );
        $options['methodName'] = 'local_ratings_display_ratings_content';
        $options['templateName'] = 'local_ratings/review_list';
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoption = array('contextid' => $systemcontext->id);
        $dataoption['itemid'] = $itemid;
        $dataoption['commentarea'] = $commentarea;
        $dataoptions = json_encode($dataoption);
        $context = [
                'targetID' => 'view_reviews',
                'options' => $options,
                'dataoptions' => $dataoptions,
                'filterdata' => $filterdata
        ];
        if($filter){
            return  $context;
        } else {
        	$pagination = $this->render_from_template('local_costcenter/cardPaginate', $context);
        	$data = $this->view_item($itemid, $commentarea, $pagination);
            return $data;
        }
	}
	public function view_item($itemid, $commentarea, $pagination){
		global $CFG, $DB;
		require_once($CFG->dirroot.'/local/ratings/lib.php');
		
		$rating = display_rating($itemid, $commentarea);
    	$like = display_like_unlike($itemid, $commentarea);
    	$review = display_comment($itemid, $commentarea, False);

    	$detail = '';
    	$detail .= html_writer::start_tag('p', array('class'=>'rating m-0'));
    	$detail .= html_writer::tag('span', $rating, array('class'=>'ml-15 rating detail_value'));
        $detail .= html_writer::end_tag('p');

        $detail .= html_writer::start_tag('span', array('class'=>'like'));
        $detail .= html_writer::tag('span', $like, array('class'=>'ml-15 like detail_value'));

        $detail .= html_writer::start_tag('span', array('class'=>'course_review'));
        $detail .= html_writer::tag('span', $review, array('class'=>'ml-15 review detail_value'));
        $detail .= html_writer::end_tag('span');
        $data = array('detail' => $detail, 'pagination' => $pagination);
        switch($commentarea){
        	case 'local_courses':
                require_once($CFG->dirroot.'/local/courses/includes.php');
                $includes = new user_course_details();
        		$course_record = get_course($itemid);
        		$data['itemlogoimgurl'] = $includes->course_summary_files($course_record);
        		$data['itemname'] = $course_record->fullname;
    		break;
    		case 'local_classroom':
        		
    			$classroom = $DB->get_record('local_classroom', array('id' => $itemid), 'id, name, classroomlogo');
        		$data['itemlogoimgurl'] = (new \local_classroom\classroom)->classroom_logo($classroom->classroomlogo);
                if($data['itemlogoimgurl'] == 0){
                    require_once($CFG->dirroot.'/local/includes.php');
                    $includes = new user_course_details();
                    $data['itemlogoimgurl'] = $includes->get_classes_summary_files($classroom);
                }
        		$data['itemname'] = $classroom->name;
    		break;
    		case 'local_certification':
    			$certification = $DB->get_record('local_certification', array('id' => $itemid), 'id, name, certificationlogo');
        		$data['itemlogoimgurl'] = (new \local_certification\certification)->certification_logo($certification->certificationlogo);
                if($data['itemlogoimgurl'] == 0){
                    require_once($CFG->dirroot.'/local/includes.php');
                    $includes = new user_course_details();
                    $data['itemlogoimgurl'] = $includes->get_classes_summary_files($certification);
                }
        		$data['itemname'] = $certification->name;
    		break;
    		case 'local_program':
            	require_once($CFG->dirroot . '/local/includes.php');
            	$includes = new user_course_details();
            	$program = $DB->get_record('local_program', array('id' => $itemid), 'id, name, programlogo');
        		if ($program->programlogo > 0) {
            		$data['itemlogoimgurl'] = (new \local_program\program)->program_logo($program->programlogo);
        			if ($data['itemlogoimgurl'] == false) {
                		$data['itemlogoimgurl'] = $includes->get_classes_summary_files($program);
            		}
        		} else {
                	$data['itemlogoimgurl'] = $includes->get_classes_summary_files($program);	
        		} 
        		$data['itemname'] = $program->name;
    		break;
    		case 'local_learningplan':
    			$learningplan = $DB->get_record('local_learningplan', array('id' => $itemid),'id, name');
    			$learningplan_lib = new \local_learningplan\lib\lib();
        		$data['itemlogoimgurl'] = $learningplan_lib->get_learningplansummaryfile($learningplan->id);
        		$data['itemname'] = $learningplan->name;
    		break;
        }
        return $this->render_from_template('local_ratings/view_item', $data);
	}
    public function render_ratings_data($modulename, $moduleid, $ratingvalue = null, $ratingWidth = 18){
        global $DB;
        if(is_null($ratingvalue)){
            $modulerating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_id' => $moduleid, 'module_area' => $modulename));
        }else{
            $modulerating = $ratingvalue;
        }
        $additionalSpace = 11;
        $ratewidth = $ratingWidth;
        $starcount = range(1,5);
        $containerWidth = $ratewidth*count($starcount) + $additionalSpace;
        $modulerating = $modulerating * 20 + $modulerating/2 ;
        return $this->render_from_template('local_ratings/average_ratings_display', array('modulerating' => $modulerating, 'starcount' => $starcount, 'ratewidth' => $ratewidth, 'containerWidth' => $containerWidth));
    }
    public function render_like_info($modulename, $moduleid, $likecount = NULL){
        if(is_null($likecount)){
            global $DB;
            $likes = $DB->get_field('local_ratings_likes', 'module_like', array('module_area' => $modulename, 'module_id' => $moduleid));
            $modulelikes = $likes ? $likes : '0';
        }else{
            $modulelikes = $likecount;
        }
        $likeicon = html_writer::tag('i', '', array('class' => 'fa fa-like'));
        return html_writer::tag('span', $likeicon.' '.$modulelikes, array('class' => 'module_likes_container'));

    }
    public function render_rating_element($modulename, $moduleid, $ratingvalue = null, $ratingWidth = 18){
        global $DB;
        if(is_null($ratingvalue)){
            $modulerating = $DB->get_field('local_ratings_likes', 'module_rating', array('module_id' => $moduleid, 'module_area' => $modulename));
        }else{
            $modulerating = $ratingvalue;
        }
        $containerid = 'ratings_enable_'.$modulename.'_'.$moduleid;
        $options = json_encode(['max' => 5,
                        'rgbOn' => '#efce2e',
                        'rgbOff' => '#9c9b97',
                        'rgbSelection' => '#efce2e',
                        'indicator' => 'fa-star',
                        'fontsize' => '18px'
                ]);
        // return '<span id="'.$containerid.'" onload = "(function(e){ require(\'local_ratings/ratings\').init(\'#'.$containerid.'\', \''.$options.'\') })(event)"></span>';
        // return html_writer::tag('span', 'test', array('data-stars' => $modulerating, 'id' => $containerid,'onload' => '(function(e){ require("local_ratings/ratings").init("#'.$containerid.'", \''.$options.'\') })(event)'));
        return html_writer::tag('span', '', array('class' =>  'rating_enable_wrapper','data-stars' => $modulerating, 'id' => $containerid/*, 'data-options' => $options*/));
    }
}