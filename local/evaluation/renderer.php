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
 * @subpackage local_evaluation
 */



defined('MOODLE_INTERNAL') || die();

class evaluations implements renderable {
    
     public function __construct($id, $evaluationstructure) {
        $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
        $this->evaluationstructure = $evaluationstructure;
        $this->id = $id;
        $this->context = $systemcontext;
     }
}

/**
 * The renderer for the evaluation module.
 *
 * @copyright  2019 eabyas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_evaluation_renderer extends plugin_renderer_base  {
    
  protected function render_evaluations(evaluations $renderable) {
    return $this->display($renderable->id, $renderable->context, $renderable->evaluationstructure);
  }


  public function get_evaluation_top_buttons($id, $context, $record){
    global $PAGE;
    $buttons = array();
    if((is_siteadmin() OR has_capability('local/evaluation:edititems', $context))){
      if(substr($PAGE->url->out_as_local_url(), 0, strpos($PAGE->url->out_as_local_url(), '?')) == '/local/evaluation/eval_view.php'){
        if(is_siteadmin() || has_capability('local/evaluation:edititems', $context)){
            $exporturl = new moodle_url('/local/evaluation/export.php?action=exportfile&id='.$id);
            $backupimg = html_writer::tag('i', '', array('class' => 'icon fa fa-download','title' => 'download','role'=>'img','aria-label'=>'download'));
            $buttons[] = html_writer::start_tag('li', array('title' => get_string('export_questions', 'local_evaluation'))).
                html_writer::link($exporturl, $backupimg, array('class' => 'course_extended_menu_itemlink')).
                html_writer::end_tag('li');
            $importurl = new moodle_url('/local/evaluation/import.php', array('id'=>$id));
            
            $importimg = html_writer::tag('i', '', array('class' => 'icon fa fa-upload','title' => 'upload','role'=>'img','aria-label'=>'upload'));
            $buttons[] = html_writer::start_tag('li', array('title' => get_string('import_questions', 'local_evaluation'))).
                    html_writer::link($importurl, $importimg, array('class' => 'course_extended_menu_itemlink')).
                    html_writer::end_tag('li');
        }
      }
    }
    if ( (is_siteadmin() OR has_capability('local/evaluation:edititems', $context) ) AND $record->instance == 0  ) {
      $editimg = html_writer::tag('i', '', array('class' => 'icon fa fa-pencil','title' => 'edit','role'=>'img','aria-label'=>'edit'));
      $buttons[] =  html_writer::start_tag('li', array('')).
          html_writer::link("javascript:void(0)",$editimg, array('class'=>'course_extended_menu_itemlink', 'data-action'=>"createevaluationmodal", 'data-value'=>$record->id)).
          html_writer::end_tag('li');

      // if (has_capability('local/evaluation:viewreports', $context))
      // $buttons[] = html_writer::start_tag('li', array('')).
      //     html_writer::link(new moodle_url('/local/evaluation/analysis.php', array('id' => $record->id, 'sesskey' => sesskey())), $this->pix_icon('i/grades', get_string('overview', 'local_evaluation'), 'moodle', array('class' => 'iconsmall ', 'title' => '')),  array('class'=>'course_extended_menu_itemlink')).
      //     html_writer::end_tag('li');
      
      if (has_capability('local/evaluation:viewanalysepage', $context))
      $buttons[] = html_writer::start_tag('li', array('')).
            html_writer::link(new moodle_url('/local/evaluation/show_entries.php', array('id' => $record->id, 'sesskey' => sesskey())), $this->pix_icon('i/preview', get_string('responses', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '')),  array('class'=>'course_extended_menu_itemlink')).
            html_writer::end_tag('li');

      if (has_capability('local/evaluation:enroll_users', $context))
      $buttons[] = html_writer::start_tag('li', array('')).
            html_writer::link(new moodle_url('/local/evaluation/users_assign.php', array('id' => $record->id, 'sesskey' => sesskey())), $this->pix_icon('i/assignroles', get_string('assignusers', 'local_evaluation'), 'moodle', array('class' => 'iconsmall', 'title' => '', 'target'=>'_blank')),  array('class'=>'course_extended_menu_itemlink')).
            html_writer::end_tag('li');
      // check for deletion
      if (is_siteadmin() || has_capability('local/evaluation:delete', $context)) {
       $candelete = check_evaluationdeletion($record->id);
       $buttons[] = html_writer::start_tag('li', array('')). 
       html_writer::link(
       "javascript:void(0)",
       $this->pix_icon('i/delete', get_string('delete'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
       array('id' => 'deleteconfirm' . $record->id . '', 'class'=>'course_extended_menu_itemcontainer course_extended_menu_itemlink', 'onclick' => '(
           function(e){
           require("local_evaluation/newevaluation").deleteevaluation("' . $record->id . '")
           })(event)')).
       html_writer::end_tag('li');
      }
    }
    return $buttons;
  }
  
  /**
  * display single Feedback
  *

  * @param int $id feedback id
  * @param string $context system context
  * @return object contains entire feedback structure
  */
     
  function display($id, $context, $evaluationstructure) {
    global $DB, $USER, $OUTPUT, $PAGE, $CFG;        
    $data = '';        
    $line = array();
    $record = $DB->get_record('local_evaluations', array('id'=>$id));
    $costcenterpathconcatsql = (new \local_evaluation\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='open_path');
    $attendcount = $DB->count_records_sql('SELECT count(ou.id) from {local_evaluation_users} ou, {user} u where u.id = ou.userid AND u.deleted = 0 '.$costcenterpathconcatsql.' AND u.suspended = 0 AND ou.evaluationid=?', array($record->id));
    $completedevaluationcount = intval(evaluation_get_completeds_group_count($record));
    $buttons = $this->get_evaluation_top_buttons($id, $context, $record);
 
    $buttons = implode('',$buttons);

    if($record->timeopen==0 AND $record->timeclose==0) {
        $dates= get_string('open', 'local_evaluation');
    } elseif(!empty($record->timeopen) AND empty($record->timeclose)) {
        $dates = 'From '. \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timeopen);
    } elseif (empty($record->timeopen) AND !empty($record->timeclose)) {
        $dates = 'Ends on '. \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timeclose);
    } else {
        $dates = \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timeopen).  ' to '  . \local_costcenter\lib::get_userdate("d/m/Y H:i", $record->timeclose);
    }
    if ($record->instance == 0)
    $evaltype = ($record->type == 1)? get_string('feedback', 'local_evaluation'):get_string('survey', 'local_evaluation');
    else
    $evaltype = get_string('feedback', 'local_evaluation');
    
    $draftitemid = file_get_submitted_draft_itemid('intro');
    $editoroptions = evaluation_get_editor_options();
    if (is_siteadmin() OR has_capability('local/evaluation:edititems', $context)) {
         $has_evalcap = true;
         $line['has_evalcap'] = $has_evalcap;
         $line['evalname'] = $record->name;
         $line['evalid'] = $record->id;
         $line['description'] = $record->intro;
         $line['schedule'] = $dates;
         $line['evaltype'] = $evaltype;
         $line['enrolled'] = html_writer::link("javascript:void(0)",$attendcount, array('onclick'=>'(
                 function(e){
         require("local_evaluation/newevaluation").enrolledusers("' . $record->id . '","1", "'.$context->id.'", "'.$record->name.'")
         })(event)'));
         $line['completed'] = html_writer::link("javascript:void(0)",$completedevaluationcount, array('onclick'=>'(
                 function(e){
         require("local_evaluation/newevaluation").enrolledusers("' . $record->id . '","2", "'.$context->id.'", "'.$record->name.'")
         })(event)'));;
         $line['actions'] = '<ul class="course_extended_menu_list"><li>'.$buttons.'</li></ul>';

    }
    
    $data .= $this->render_from_template('local_evaluation/evalview', $line);
  
    $data .= html_writer:: start_tag('div', array('id' => 'evaluationtabs', 'class' => 'nav nav-tabs col-md-12 col-sm-12 col-xs-12'));
    $data .= html_writer:: start_tag('ul', array('id' => 'ul_refferals'));
    //Questions tab info
    $tab1url = new moodle_url('#edit', array());
    $tab1link = html_writer:: link($tab1url, get_string('edit_items', 'local_evaluation'), array());
    $data .= html_writer:: tag('li', $tab1link, array());
    //templates tab info
    $tab2url = new moodle_url('#tempaltes', array());
    $tab2link = html_writer:: link($tab2url, get_string('templates', 'local_evaluation'), array());
    $data .= html_writer:: tag('li', $tab2link, array());
    $data .= html_writer:: end_tag('ul');

    //Pending tab info
    $questions_tabcontent = $this->editquestions($id,$context,$evaluationstructure);
    $data .= html_writer:: tag('div', $questions_tabcontent, array('id' => 'edit', 'class' => 'coursesgrid_search'));
    //Approved tab info
    $tmepaltes_tabcontent = $this->templates($id,$context);
    $data .= html_writer:: tag('div', $tmepaltes_tabcontent, array('id' => 'tempaltes', 'class' => 'coursesgrid_search'));
    $data .= html_writer:: end_tag('div');

    $data .= html_writer::script("
        $( '#evaluationtabs' ).tabs();
    ");

    return $data;
  }

  /**
  * Returns questions in a Feedback
  *

  * @param int $id feedback id
  * @param string $context system context
  * @return object contains entire feedback structure
  */
  function editquestions($id, $context, $evaluationstructure) {
      global $DB, $CFG, $OUTPUT, $USER, $PAGE;
      $output = "";
      if(is_siteadmin() || has_capability('local/evaluation:create_update_question', $context)){
          $output .= html_writer::start_tag('li', array('')).
                html_writer::link('javascript:void(0)', $this->pix_icon('i/addblock', get_string('le_createnewquestion', 'local_evaluation'), 'moodle', array('class' => 'iconsmall  pull-right', 'title' => '', 'target'=>'_blank')), array('data-fg' => 'c', 'data-method' => 'addnew_question', 'data-plugin' => 'local_evaluation', 'class' => 'course_extended_menu_itemlink', 'data-id' => $id)).
                html_writer::end_tag('li');
        }
      if ($DB->record_exists('local_evaluation_item', array('evaluation'=>$id))) {
           $output .= '<div class="evaluation_header_wrapper">
                            <div class="evaluation_question_heading_label">'.get_string('question','local_evaluation').'</div>
                            <div class="ratings_wrapper">
                                   <div class="evaluation_question_heading_ratings">'.get_string('line_values','local_evaluation').'</div>
                            </div>
                       </div>';
           $form = new local_evaluation_complete_form(local_evaluation_complete_form::MODE_EDIT, $evaluationstructure, 'evaluation_edit_form');
           $output.= '<div id="evaluation_dragarea" class="p-15 mb-15">'; // The container for the dragging area.
           $output.=$form->render();
           
           
           $cancreatetemplates = has_capability('local/evaluation:createprivatetemplate', $context) ||
                 has_capability('local/evaluation:createpublictemplate', $context);
      
           $create_template_form = new evaluation_edit_create_template_form(null, array('id' => $id));
           if ($cancreatetemplates) {
               $deleteurl = new moodle_url('/local/evaluation/delete_template.php', array('id' => $id));
               $output .= $create_template_form->render();
           } else {
               $output .= '&nbsp;';
           }
           $output.= '</div>';
      }
      else{
        $output .= '<div class = "evaluation">
                       <div class = "evaluation_heading">'.get_string('questions','local_evaluation').'</div>
                       </div>';
      }
           return $output;
  }

  /**
  * Returns tempaltes in a Feedback
  *

  * @param int $id feedback id
  * @param string $context system context
  */
  function templates($id,$context) {
      global $DB, $CFG, $OUTPUT, $USER, $PAGE;
      $output = "";
      //The use_template-form
      $cancreatetemplates = has_capability('local/evaluation:createprivatetemplate', $context) ||
          has_capability('local/evaluation:createpublictemplate', $context);
      
      $allemplates = evaluation_get_template_list('all');
      $output.= '<p>'.get_string('template_eval', 'local_evaluation').'</p>';
      $output.= '<lable for="template">Select template</lable><select class="custom-select ml-15" name="template" id="id_templateid" value='.$id.'>
      <option data-ignore="" value="" selected="">Choose...</option>';
      foreach($allemplates as $template) {
          $output.= "<option value=$template->id>$template->name</option>";
      }
      $output.= '</select>';
      $output .= '<hr>';
      $output .= '<div id="displaytempalteform"></div>';
      if ($cancreatetemplates && has_capability('local/evaluation:deletetemplate', $context)) {
          $deleteurl = new moodle_url('/local/evaluation/delete_template.php', array('id' => $id));
          $output .= '<p><a href="'.$deleteurl->out().'">'.
               get_string('delete_templates', 'local_evaluation').
               '</a></p>';
      } else {
          $output .= '&nbsp;';
      }

      
      return $output;

  }
  /**
   * Display the avialable feedbacks list
   *
   * @return string The text to render
  */
  public function get_evaluations($filter = false, $view_type = 'card') {
      $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
       $status = optional_param('status', '', PARAM_RAW);
      
        $costcenterid = optional_param('costcenterid', '', PARAM_INT);
        $departmentid = optional_param('departmentid', '', PARAM_INT);
      // change the display according to moodle 3.6
      $stable = new stdClass();
      $stable->thead = true;
      $stable->start = 0;
      $stable->length = -1;
      $stable->search = '';
      $stable->pagetype ='page';

       $templateName = 'local_evaluation/evaluations_list';
       $cardClass = 'col-lg-3 col-md-6 col-12';
       $perpage = 12;
        if($view_type=='table'){
            $templateName = 'local_evaluation/evaluations_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
       } 


      $options = array('targetID' => 'manage_feedbacks','perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type);
      $options['methodName']='local_evaluation_evaluationview';
      $options['templateName']= $templateName;
      $options = json_encode($options);
      $filterdata = json_encode(array('status'=>$status,'organizations'=>$costcenterid,'departments'=>$departmentid));
      $dataoptions = json_encode(array('contextid' => $systemcontext->id,'status'=>$status,'organizations'=>$costcenterid,'departments'=>$departmentid));
      $context = [
              'targetID' => 'manage_feedbacks',
              'options' => $options,
              'dataoptions' => $dataoptions,
              'filterdata' => $filterdata
      ];
      if($filter){
          return  $context;
      } else {
          return  $this->render_from_template('local_costcenter/cardPaginate', $context);
      }       
  }

  /**
     * Renders html to print list of feedbacks tagged with particular tag
     *
     * @param int $tagid id of the tag
     * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
     *             are displayed on the page and the per-page limit may be bigger
     * @param int $fromctx context id where the link was displayed, may be used by callbacks
     *            to display items in the same context first
     * @param int $ctx context id where to search for records
     * @param bool $rec search in subcontexts as well
     * @param array $displayoptions
     * @return string empty string if no courses are marked with this tag or rendered list of courses
     */
  public function tagged_evaluations($tagid, $exclusivemode, $ctx, $rec, $displayoptions, $count = 0, $sort='') {
    global $CFG, $DB, $USER;
    $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
    if ($count > 0)
    $sql =" select count(c.id) from {local_evaluations} c ";
    else
    $sql =" select c.* from {local_evaluations} c ";

    $where = " where c.id IN (SELECT t.itemid FROM {tag_instance} t WHERE t.tagid = :tagid AND t.itemtype = :itemtype AND t.component = :component)";
    
    
    if (is_siteadmin())
    $where .= " AND 1=1 ";
    elseif (has_capability('local/evaluation:edititems',$systemcontext))
    $where .= dep_sql($systemcontext); // get records department wise
    else
    $where .= " AND c.id IN (select evaluationid from {local_evaluation_users} where userid = $USER->id)";
  
    $joinsql = $groupby = $orderby = '';
    if (!empty($sort)) {
      switch($sort) {
        case 'highrate':
        if ($DB->get_manager()->table_exists('local_rating')) {
          $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_evaluation' ";
          $groupby .= " group by c.id ";
          $orderby .= " order by AVG(rating) desc ";
        }        
        break;
        case 'lowrate':  
        if ($DB->get_manager()->table_exists('local_rating')) {  
          $joinsql .= " LEFT JOIN {local_rating} as r ON r.moduleid = c.id AND r.ratearea = 'local_evaluation' ";
          $groupby .= " group by c.id ";
          $orderby .= " order by AVG(rating) asc ";
        }
        break;
        case 'latest':
        $orderby .= " order by c.timemodified desc ";
        break;
        case 'oldest':
        $orderby .= " order by c.timemodified asc ";
        break;
        default:
        $orderby .= " order by c.timemodified desc ";
        break;
        }
    }

    $params = array('tagid' => $tagid, 'itemtype' => 'evaluation', 'component' => 'local_evaluation');

    if ($count > 0) {
      $records = $DB->count_records_sql($sql.$where, $params);
      return $records;
    } else {
      $records = $DB->get_records_sql($sql.$joinsql.$where.$groupby.$orderby, $params);
    }
    $tagfeed = new local_tags\output\tagfeed(array(), 'evaluations');
    $img = $this->output->pix_icon('i/course', '');
    foreach ($records as $key => $value) {
      # code...
      $url = $CFG->wwwroot.'/local/evaluation/complete.php?id='.$value->id.'';
      $imgwithlink = html_writer::link($url, $img);
      $modulename = html_writer::link($url, $value->name);
      $evaldetails = get_evaluation_details($value->id, $sort);
      $details = $this->render_from_template('local_evaluation/tagview', $evaldetails);
      $tagfeed->add($imgwithlink, $modulename, $details);
    }
    return $this->output->render_from_template('local_tags/tagfeed', $tagfeed->export_for_template($this->output));
  }
    public function get_userdashboard_evaluation($tab, $filter = false,$view_type='card'){
        $systemcontext = (new \local_evaluation\lib\accesslib())::get_module_context();
        
        $templateName = 'local_evaluation/userdashboard_paginated';
        $cardClass = 'col-md-6 col-12';
        $perpage = 6;
        if($view_type=='table'){
            $templateName = 'local_evaluation/userdashboard_paginated_catalog_list';
            $cardClass = 'tableformat';
            $perpage = 20;
        } 

        $options = array('targetID' => 'dashboard_evaluation', 'perPage' => $perpage, 'cardClass' => $cardClass, 'viewType' => $view_type);
        $options['methodName'] = 'local_evaluation_userdashboard_content_paginated';
        $options['templateName'] = $templateName;
        $options['filter'] = $tab;
        $options = json_encode($options);
        $filterdata = json_encode(array());
        $dataoptions = json_encode(array('contextid' => $systemcontext->id));
        $context = [
                'targetID' => 'dashboard_evaluation',
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
}
