<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_notifications
 * @copyright  2018 sreenivas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_notifications\output;
defined('MOODLE_INTERNAL') || die();
use plugin_renderer_base as mainbase;
use context_system;
use html_table;
use html_writer;
use plugin_renderer_base;
use moodle_url;
use stdClass;
use single_button;
use local_notifications\local\notification_master;

if(file_exists($CFG->dirroot . '/local/costcenter/lib.php')){
  require_once($CFG->dirroot . '/local/costcenter/lib.php');
}
/**
 * The renderer for the notifications module.
 *
 * @copyright  2018 sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends mainbase  {
    
     protected function render_notifications(notifications $renderable) {
          $data = $this->display($renderable->id, $renderable->context);
          $content = ['notificationslist' => $data,
                      'contextid' => $renderable->context,
                      'notificationid'=>$renderable->id
                      ];
          return $this->render_from_template('local_notifications/notifications', $content);
     }
     function display($id, $context) {
          global $DB, $OUTPUT, $PAGE,$USER;
          $lib = new notifications();
          $costcenter = new \costcenter();
          //$systemcontext = \context_system::instance();
          $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
         
       if(is_siteadmin()){
            $sql = "SELECT ni.id, nt.name, nt.shortname, ni.subject, costcenterid, lc.fullname as deptname, ni.active
                FROM {local_notification_info} ni
                JOIN {local_notification_type} nt ON ni.notificationid = nt.id
                JOIN {local_costcenter} lc ON ni.costcenterid = lc.id ORDER BY ni.id DESC";
        } elseif(!is_siteadmin()  && has_capability('local/costcenter:manage_ownorganization', $systemcontext)){
            // $costcenter = $DB->get_field_sql("SELECT u.open_costcenterid from {user} u where u.id = {$USER->id}");
            $costcenter = $USER->open_costcenterid;
            $sql = "SELECT ni.id, nt.name, nt.shortname, ni.subject, costcenterid, lc.fullname as deptname, ni.active
                FROM {local_notification_info} ni
                JOIN {local_notification_type} nt ON ni.notificationid = nt.id
                JOIN {local_costcenter} lc ON ni.costcenterid = lc.id where ni.costcenterid in ($costcenter) ORDER BY ni.id DESC";
        } else {
//          print_error('You dont have permissions to view this page.');
             print_error(get_string('dont_have_permission_view_page', 'local_notifications'));
              die();  
        }
        $notifications_info = $DB->get_records_sql($sql);
        if($notifications_info){
            $data = array();
                       
            foreach($notifications_info as $each_notification){
                $row = array();
                $row[] = $each_notification->name;
                $row[] = $each_notification->shortname;
                $row[] = $each_notification->subject;
                $row[] = $DB->get_field('local_costcenter', 'fullname', array('id'=>$each_notification->costcenterid));
                
               
                
                $editurl = new \moodle_url('/local/notifications/index.php', array('id'=>$each_notification->id));
                $deleteurl = new \moodle_url('/local/notifications/index.php', array('deleteid'=>$each_notification->id));
                                
                $actions = array();
                
                $actions[] = \html_writer::link('javascript:void(0)', $OUTPUT->pix_icon('t/edit', get_string('edit'), 'moodle', array('class' => 'iconsmall', 'title' => '')), array('data-action' => 'createnotificationmodal', 'class'=>'createnotificationmodal', 'data-value'=>$each_notification->id, 'class' => '', 'onclick' =>'(function(e){ require("local_notifications/notifications").init({selector:"createnotificationmodal", context:'.$systemcontext->id.', id:'.$each_notification->id.', form_status:0}) })(event)','style'=>'cursor:pointer' , 'title' => 'edit'));    

                $actions[] = \html_writer::link(
						"javascript:void(0)",
						$OUTPUT->pix_icon('i/delete', get_string('delete'), 'moodle', array('class' => 'iconsmall', 'title' => '')),
						array('id' => 'deleteconfirm' . $each_notification->id . '', 'onclick' => '(
							  function(e){
				require("local_notifications/custom").deletenotification("' . $each_notification->id . '")
				})(event)'));
                $row[] = implode(' &nbsp;', $actions);
                $data[] = $row;
            }
            $table = new \html_table();
            $table->id = 'notification_info';
            $table->size = array('25%', '15%', '25%', '25%', '10%');
            $table->head = array(get_string('notification_type', 'local_notifications'),
                                 get_string('code', 'local_notifications'),
                                 get_string('subject', 'local_notifications'),
                                 get_string('organization', 'local_users'),
                                 //get_string('courses_ilts', 'local_notifications'),
                                 get_string('actions'));
            $table->data = $data;
            $notfn_types_table = \html_writer::table($table);
            $notfn_types = \html_writer::tag('div',$notfn_types_table,array('class'=>'notification_overflow'));
        }else{
            $notfn_types = \html_writer::tag('h5', get_string('no_records', 'local_notifications'), array());
        }
        
        return $notfn_types;
     }

     /**
     * [render_form_status description]
     * @method render_form_status
     * @param  \local_notifications\output\form_status $page [description]
     * @return [type]                                    [description]
     */
    public function render_form_status(\local_notifications\output\form_status $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_notifications/form_status', $data);
    }


     ////Using service.php showing data on index page instead of ajax datatables
    public function managenotifications_content($filter = false){
        global $USER;

          $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
          //$systemcontext = context_system::instance();

        $options = array('targetID' => 'manage_notifications','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');
        
        $options['methodName']='local_notifications_managenotifications_view';
        $options['templateName']='local_notifications/notifications_view'; 
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'manage_notifications',
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





   /*********************Email Status(Shilpa)************************/
        public function view_email_status() {
            global $OUTPUT, $CFG, $DB;
            $notimaster = new notification_master;
            $selorgdetails = $notimaster->getALLOrganizationDetails();

            $output='<ul class="course_extended_menu_list">
          <li>
          <div class="coursebackup course_extended_menu_itemcontainer">
            <a href="'.$CFG->wwwroot.'/local/notifications/index.php" title="'.get_string('back').'" class="course_extended_menu_itemlink">
              <i class="icon fa fa-reply"></i>
            </a>
        </div>
        </li>
        </ul>';
            
            //------Adding Custom Search Filters --------//
            $output.= "<table class='email-filter-table'>
                 <tr>";
                 if(is_siteadmin() || has_capability('local/costcenter:manage_multiorganizations', (new \local_notifications\lib\accesslib())::get_module_context())){
            $output.= "<td class='d-xs-block'>
              <select class='custom_noti_filter custom-select form-control-danger' id='1'>
                       <option value=''>".get_string('select_organization', 'local_notifications')."</option>";
                      foreach ($selorgdetails as $key => $value) {
                        # code...
                          $output.= "<option value=".$value->id.">".$value->fullname."</option>";
                      }
                   
                     $output.= "</select>
                   </td>";
                 }
              $output .= "<td class='d-xs-block'>
                     <select class='custom_noti_filter custom-select form-control-danger' id='2' style='margin-left: 30px;'>
                       <option value=''>".get_string('select_status', 'local_notifications')."</option>
                       <option value='1'>".get_string('option_sent', 'local_notifications')."</option>
                       <option value='0'>".get_string('option_not_sent', 'local_notifications')."</option>
                     </select>
                   </td>
                 </tr>
               </table>";
             //------Adding Custom Search Filters Done --------//

            $output .='<table  id = "noti_list" cellpadding="30" cellspacing="10" style="width:100%;">
                <thead>
                <tr>
                <th>'.get_string('organization', 'local_notifications').'</th>
                <th>'.get_string('send_from', 'local_notifications').'</th>
                <th>'.get_string('send_to', 'local_notifications').'</th>
                <th>'.get_string('type', 'local_notifications').'</th>
                <th>'.get_string('created_date', 'local_notifications').'</th>
                <th>'.get_string('sent_date', 'local_notifications').'</th>
                <th>'.get_string('status').'</th>
                <th>'.get_string('action').'</th>
                </tr>
                </thead>';   
             $output .='</table>';


        $output .= html_writer::script("$(document).ready(function() {
          //$.fn.dataTable.ext.errMode = 'none';
          $.fn.dataTable.ext.errMode = 'throw';
          var oTable = $('#noti_list').DataTable( {
              'bInfo' : false,
              'bLengthChange': false,
              'language': {
                      'paginate': {
                          'next': '>',
                          'previous': '<'
                      }
              },
              'pageLength': 10,
              'processing': true,
              'serverSide': true,
              'ajax': M.cfg.wwwroot + '/local/notifications/email_status_filters.php',
      
          });
          $('.dataTables_filter').css('display','none');
          $('.custom_noti_filter').click(function() {
              var i =$(this).attr('id');  // getting column index
              var v =$(this).val();  // getting search input value
              oTable.columns(i).search(v).draw();

          });
                });
          ");
    
        return $output;
          
        
        }


        /////Calling this function to view details of email status///////
         public function view_email_status_details($id) {
            global $OUTPUT, $CFG, $DB;

           

            $notimaster = new notification_master;

            $notidetails = $notimaster->getNotificationInfoById($id);
            $sender_name= $notimaster->getSenderDetails($notidetails->from_userid);
            $receiver_name= $notimaster->getReceiverDetails($notidetails->to_userid);
         
             $return .="<h4>From:".$sender_name."</h4>";
             $return .="<h4>To:".$receiver_name."</h4>";
             $return .="<h4>Subject: ".$notidetails->subject."</h4>";
             $return .="<h4>Content: ".$notidetails->emailbody."</h4>";
   
            return $return;
  
        }
 /*********************Email Status(Shilpa)************************/

}
