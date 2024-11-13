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
 * Plugin event observers are registered here.
 *
 * @package local_biz_cart
 * @copyright 2024 Moodle India <info@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_biz_cart\output;

use plugin_renderer_base;
use templatable;

/**
 * Renderer class.
 * @package local_biz_cart
 */
class renderer extends plugin_renderer_base
{

    /**
     * Render add to cart button
     *
     * @param templatable $button
     * @return string|bool
     */
    public function render_button(templatable $button)
    {
        $data = $button->export_for_template($this);
        return $this->render_from_template('local_biz_cart/addtocartdb', $data);
    }

    /**
     * Render history card.
     *
     * @param templatable $data
     * @return string|bool
     */
    public function render_history_card(templatable $data)
    {
        $data = $data->export_for_template($this);
        return $this->render_from_template('local_biz_cart/history_card', $data);
    }

   

        ////Using service.php showing data on index page instead of ajax datatables
    public function view_user_transactions($filter = false){
        global $USER;

          $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
          

        $options = array('targetID' => 'manage_transactions','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');
        $options['methodName']='local_biz_cart_transactions_view';
        $options['templateName']='local_biz_cart/transaction_details';
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array());

        $context = [
                'targetID' => 'manage_transactions',
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
    public function view_user_transactions_for_admin($filter = false){
        global $USER;
        $systemcontext =(new \local_notifications\lib\accesslib())::get_module_context();
        $options = array('targetID' => 'manage_transactions_foradmin','perPage' => 10, 'cardClass' => 'w_oneintwo', 'viewType' => 'table');
        $options['methodName']='local_biz_cart_transactions_view_for_admin';
        $options['templateName']='local_biz_cart/transaction_details_for_admin'; 
       
        $options = json_encode($options);

        $dataoptions = json_encode(array('userid' =>$USER->id,'contextid' => $systemcontext->id));
        $filterdata = json_encode(array($filter));
        $context = [
                'targetID' => 'manage_transactions_foradmin',
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
    // function get_user_transactions_for_admin(){
    //     global $DB, $OUTPUT;
    //     $transactions = $DB->get_records('paygw_airpay' ,[]);
    //     foreach ($transactions as $transaction){
    //         $user = \core_user::get_user($transaction->userid);
    //         $userfullname = $user->firstname . ' ' . $user->lastname;
    //         if($transaction->component == 'local_biz_cart'){
    //             $carthistory = $DB->get_records('local_biz_cart_history',['identifier' => $transaction->itemid]);
    //                 foreach($carthistory as $cartitem){
    //                     $coursedetails = [];
    //                     $coursedetails['courseid'] = $cartitem->itemid;
    //                     $coursedetails['coursename'] = $cartitem->itemname;
    //                     $coursedetails['userid'] = $cartitem->userid;
    //                     $coursedetails['userfullname'] = $userfullname;
    //                     $coursedetails['orderid'] = $transaction->ap_orderid;
    //                     $coursedetails['transactioncode'] = $transaction->paymentid;
    //                     $coursedetails['invoicedate'] = date('d/m/Y',$transaction->timecreated);
    //                     $coursedetails['amount'] = $cartitem->price .' INR';
    //                     $coursedetails['status'] = $transaction->status == 2 ? 'Completed' : 'Failed';
    //                     $data[] = $coursedetails;
    //              } 
    //         }else{
    //             $enrolinstance = $DB->get_record('enrol', ['id' => $transaction->itemid]);
    //             $coursename = $DB->get_field('course', 'fullname', ['id' => $enrolinstance->courseid]);
    //             $coursedetails = [];
    //             $coursedetails['courseid'] = $enrolinstance->courseid;
    //             $coursedetails['coursename'] = $coursename;
    //             $coursedetails['orderid'] = $transaction->ap_orderid;
    //             $coursedetails['transactioncode'] = $transaction->paymentid;
    //             $coursedetails['userid'] = $transaction->userid;
    //             $coursedetails['userfullname'] = $userfullname;
    //             $coursedetails['invoicedate'] = date('d/m/Y',$transaction->timecreated);;
    //             $coursedetails['amount'] = number_format($enrolinstance->cost, 2) .' INR';
    //             $coursedetails['status'] = $transaction->status == 2 ? 'Completed' : 'Failed';
    //             $data[] = $coursedetails;
    //     }
    // }
    // $records['records'] = $data;
    // echo $OUTPUT->render_from_template('local_biz_cart/transaction_details_for_admin', $records);
    // }

}
