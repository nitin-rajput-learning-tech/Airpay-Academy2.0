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
 * Moodle hooks for local_biz_cart
 * @package    local_biz_cart
 * @copyright  2024 Moodle India <info@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_biz_cart\biz_cart;

// Define constants.

// First entry in shopping cart history. This means that payment was initiated, but not successfully completed.
define('LOCAL_BIZCART_PAYMENT_PENDING', 0);
// Pending will be switched to aborted, once we can be sure that the payment process will not be continued.
define('LOCAL_BIZCART_PAYMENT_ABORTED', 1);
// Payment was successful.
define('LOCAL_BIZCART_PAYMENT_SUCCESS', 2);
// Canceled payments mean that items - which have already been paid for - are canceled after successful checkout.
define('LOCAL_BIZCART_PAYMENT_CANCELED', 3);

// Payment methods.
define('LOCAL_BIZCART_PAYMENT_METHOD_ONLINE', 0);
// Payment via payment gateway (which is usually connected with a credit card).
define('LOCAL_BIZCART_PAYMENT_METHOD_CASHIER', 1); // Payment at cashier's office (unknown if cash, debit or credit card).
define('LOCAL_BIZCART_PAYMENT_METHOD_CREDITS', 2); // Payment via credits.
define('LOCAL_BIZCART_PAYMENT_METHOD_CASHIER_CASH', 3); // Payment at cashier's office using cash.
define('LOCAL_BIZCART_PAYMENT_METHOD_CASHIER_DEBITCARD', 4); // Payment at cashier's office using a debit card.
define('LOCAL_BIZCART_PAYMENT_METHOD_CASHIER_CREDITCARD', 5); // Payment at cashier's office using a credit card.
define('LOCAL_BIZCART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH', 6); // Credits removed and paid back to user by cash.
define('LOCAL_BIZCART_PAYMENT_METHOD_CASHIER_MANUAL', 7);
// If someone paid, but there was an error, the cashier can re-book someone manually.
define('LOCAL_BIZCART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER', 8);
// Credits removed and paid back to user by (bank) transfer.
define('LOCAL_BIZCART_PAYMENT_METHOD_CREDITS_CORRECTION', 9); // Credits removed and paid back to user by (bank) transfer.

// Cart success params.
define('LOCAL_BIZCART_CARTPARAM_ERROR', -1); // General error.
define('LOCAL_BIZCART_CARTPARAM_ALREADYINCART', 0); // Already in cart.
define('LOCAL_BIZCART_CARTPARAM_SUCCESS', 1); // Item added to cart successfully.
define('LOCAL_BIZCART_CARTPARAM_CARTISFULL', 2); // Item could not be added because cart is full.
define('LOCAL_BIZCART_CARTPARAM_COSTCENTER', 3); // Item could not be added because of different cost center.
define('LOCAL_BIZCART_CARTPARAM_FULLYBOOKED', 4); // Item could not be added because it's already fully booked.
define('LOCAL_BIZCART_CARTPARAM_ALREADYBOOKED', 5); // Item could not be added because it was already booked before.

/**
 * Adds module specific settings to the settings block
 *
 * @param navigation_node $navigation The node to add module settings to
 * @return void
 */
function local_biz_cart_extend_navigation(navigation_node $navigation)
{
    $context = context_system::instance();
    if (has_capability('local/biz_cart:cashier', $context)) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)) {
            $nodehome = $navigation;
        }
        $pluginname = get_string('pluginname', 'local_biz_cart');
        $link = new moodle_url('/local/biz_cart/cashier.php', []);
        $icon = new pix_icon('i/biz_cart', $pluginname, 'local_biz_cart');
        $nodecreatecourse = $nodehome->add($pluginname, $link, navigation_node::NODETYPE_LEAF,
            $pluginname, 'biz_cart_cashier', $icon);
        $nodecreatecourse->showinflatnavigation = true;
    }
}

/**
 * Renders the popup.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_biz_cart_render_navbar_output(\renderer_base $renderer)
{
    global $USER, $CFG;

    // Early bail out conditions.
    // if (!isloggedin() || isguestuser()) {
    //     return '';
    // }

    $output = '';
    $cache = biz_cart::local_biz_cart_get_cache_data($USER->id);
    // // If we have the capability, we show a link to cashier's desk.
    // if (has_capability('local/biz_cart:cashier', context_system::instance())) {
    //     $cache['showcashier'] = true;
    //     $cache['cashierurl'] = new moodle_url('/local/biz_cart/cashier.php');
    // }

    // Convert numbers to strings with 2 fixed decimals right before rendering.
    biz_cart::convert_prices_to_number_format($cache);

    $output .= $renderer->render_from_template('local_biz_cart/biz_cart_popover', $cache);
    return $output;
}

/**
 * Get icon mapping for font-awesome.
 *
 * @return  array
 */
function local_biz_cart_get_fontawesome_icon_map()
{
    return [
        'local_biz_cart:i/biz_cart' => 'fa-shopping-cart',
        'local_biz_cart:t/selected' => 'fa-check',
        'local_biz_cart:t/subscribed' => 'fa-envelope-o',
        'local_biz_cart:t/unsubscribed' => 'fa-envelope-open-o',
        'local_biz_cart:t/star' => 'fa-star',
    ];
}

/**
 *  Callback checking permissions and preparing the file for serving plugin files, see File API.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_biz_cart_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = [])
{
    // Check the contextlevel is as expected - if your plugin is a block.
    // We need context course if wee like to acces template files.
    if (!in_array($context->contextlevel, [CONTEXT_SYSTEM])) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'local_biz_cart_receiptimage') {
        return false;
    }
    // Make sure the user is logged in and has access to the module.

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        // Var $args is empty => the path is '/'.
        $filepath = '/';
    } else {
        // Var $args contains elements of the filepath.
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_biz_cart', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 0, 0, true, $options);
}

/*
* Author Sachin
* Displays a node in left side menu
* @return  [type] string  link for the leftmenu
*/
function local_biz_cart_leftmenunode(){
    global $DB, $USER;
	$categorycontext = (new \local_courses\lib\accesslib())::get_module_context();
    $transactionnodes = '';
    if(!has_capability('local/courses:create', $categorycontext)) {
        $transactionnodes .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browsecourses', 'class'=>'pull-left user_nav_div browsecourses'));
            $page_url = new moodle_url('/local/biz_cart/view_transactions.php');
            $transactions = html_writer::link($page_url, '<i class="fa fa-money"></i><span class="user_navigation_link_text">'.get_string('my_transactions','local_biz_cart').'</span>',array('class'=>'user_navigation_link'));
            $transactionnodes .= $transactions;
        $transactionnodes .= html_writer::end_tag('li');
    } else {
        $transactionnodes .= html_writer::start_tag('li', array('id'=> 'id_leftmenu_browsecourses', 'class'=>'pull-left user_nav_div browsecourses'));
            $page_url = new moodle_url('/local/biz_cart/view_transactions_for_admin.php');
            $transactions = html_writer::link($page_url, '<i class="fa fa-money"></i><span class="user_navigation_link_text">'.get_string('view_transactions','local_biz_cart').'</span>',array('class'=>'user_navigation_link'));
            $transactionnodes .= $transactions;
        $transactionnodes .= html_writer::end_tag('li');
    }

    return array('6' => $transactionnodes);
}

 function get_user_transactions($tablelimits, $filtervalues){
        global $USER, $DB;
        $count = $DB->count_records_sql("SELECT COUNT(id) FROM {paygw_airpay} WHERE  userid = $USER->id AND  status = 2" ,[]);
        $transactions = $DB->get_records_sql("SELECT * FROM {paygw_airpay} WHERE  userid = $USER->id AND  status = 2" ,[],$tablelimits->start, $tablelimits->length);
        $data = array();
        if(!empty($transactions)) {
            foreach ($transactions as $transaction) {
                if($transaction->component == 'local_biz_cart'){
                    $carthistory = $DB->get_records('local_biz_cart_history',['identifier' => $transaction->itemid]);
                        foreach($carthistory as $cartitem){
                            $coursedetails = [];
                            $coursedetails['courseid'] = $cartitem->itemid;
                            $coursedetails['coursename'] = $cartitem->itemname;
                            $coursedetails['transactioncode'] = $transaction->paymentid;
                            $coursedetails['invoicedate'] = date('d/m/Y',$transaction->timecreated);
                            $coursedetails['amount'] = $cartitem->price .' INR';
                            $coursedetails['status'] = $transaction->status == 2 ? 'Completed' : 'Not Completed';
                            $data[] = $coursedetails;
                     } 
                }else{
                    $enrolinstance = $DB->get_record('enrol', ['id' => $transaction->itemid]);
                    $coursename = $DB->get_field('course', 'fullname', ['id' => $enrolinstance->courseid]);
                    $coursedetails = [];
                    $coursedetails['courseid'] = $enrolinstance->coursid;
                    $coursedetails['coursename'] = $coursename;
                    $coursedetails['transactioncode'] = $transaction->paymentid;
                    $coursedetails['invoicedate'] = date('d/m/Y',$transaction->timecreated);;
                    $coursedetails['amount'] = number_format($enrolinstance->cost, 2) .' INR';
                    $coursedetails['status'] = $transaction->status == 2 ? 'Completed' : 'Not Completed';
                    $data[] = $coursedetails;
                }
            }
        } else {
            $nodata = true;
            $pagination = false;
        }
        return ['count' => $count, 'nodata' => $nodata, 'data' => $data];
    }

    function get_user_transactions_for_admin($tablelimits, $filtervalues) {
        global $DB, $OUTPUT, $USER;
        
        $categorycontext = (new \local_users\lib\accesslib())::get_module_context();
        $costcenterpathconcatsql = (new \local_users\lib\accesslib())::get_costcenter_path_field_concatsql($columnname='u.open_path');
        $countsql = "SELECT COUNT(p.id)";
        $selectsql = "SELECT p.*, u.firstname, u.lastname";
        $fromsql = " FROM {paygw_airpay} p JOIN {user} u ON u.id = p.userid";
        $wheresql = " WHERE 1=1 ORDER BY p.id DESC";
        $params = [];

        if (!is_siteadmin($USER)) {
            $wheresql .= $costcenterpathconcatsql;
        }

        // Additional filter example for search query.
        if (!empty($filtervalues->search_query)) {
            $wheresql .= " AND (u.username LIKE :searchquery OR CONCAT(u.firstname, ' ', u.lastname) LIKE :searchquery)";
            $params['searchquery'] = '%' . $filtervalues->search_query . '%';
        }

        // Pagination
        $limitfrom = $tablelimits->start;
        $limitnum = $tablelimits->length;

        // Get the count of filtered records.
        $count = $DB->count_records_sql($countsql . $fromsql . $wheresql, $params);

        // Retrieve the transaction records.
        $transactions = $DB->get_records_sql($selectsql . $fromsql . $wheresql, $params, $limitfrom, $limitnum);
        $data = array();
        foreach ($transactions as $transaction) {
            $user = \core_user::get_user($transaction->userid);
            $userfullname = $user->firstname . ' ' . $user->lastname;
            if($transaction->component == 'local_biz_cart'){
                $carthistory = $DB->get_records('local_biz_cart_history',['identifier' => $transaction->itemid]);
                foreach($carthistory as $cartitem){
                    $coursedetails = [];
                    $coursedetails['courseid'] = $cartitem->itemid;
                    $coursedetails['coursename'] = $cartitem->itemname;
                    $coursedetails['userid'] = $cartitem->userid;
                    $coursedetails['userfullname'] = $userfullname;
                    $coursedetails['orderid'] = $transaction->ap_orderid;
                    $coursedetails['transactioncode'] = $transaction->paymentid;
                    $coursedetails['invoicedate'] = date('d/m/Y',$transaction->timecreated);
                    $coursedetails['amount'] = $cartitem->price .' INR';
                    $coursedetails['status'] = $transaction->status == 2 ? 'Completed' : 'Failed';
                    $data[] = $coursedetails;
                } 
            } else {
                $enrolinstance = $DB->get_record('enrol', ['id' => $transaction->itemid]);
                $coursename = $DB->get_field('course', 'fullname', ['id' => $enrolinstance->courseid]);
                $coursedetails = [];
                $coursedetails['courseid'] = $enrolinstance->courseid;
                $coursedetails['coursename'] = $coursename;
                $coursedetails['orderid'] = $transaction->ap_orderid;
                $coursedetails['transactioncode'] = $transaction->paymentid;
                $coursedetails['userid'] = $transaction->userid;
                $coursedetails['userfullname'] = $userfullname;
                $coursedetails['invoicedate'] = date('d/m/Y',$transaction->timecreated);;
                $coursedetails['amount'] = number_format($enrolinstance->cost, 2) .' INR';
                $coursedetails['status'] = $transaction->status == 2 ? 'Completed' : 'Failed';
                $data[] = $coursedetails;
            }
        }
        return ['count' => $count, 'data' => $data];
    }


