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
 * Class external
 *
 * @package    local_biz_cart
 * @copyright  2024 Moodle India <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_biz_cart_external extends external_api{
           //////For displaying on index page//////////
      public static function transactions_view_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function transactions_view(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global $OUTPUT, $CFG, $DB,$USER,$PAGE;
        require_once($CFG->dirroot . '/local/biz_cart/lib.php');
        require_login();
        $PAGE->set_url('/local/biz_cart/view_transactions.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::transactions_view_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
       
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $result_skill = get_user_transactions($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data = $result_skill['data'];
        
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  transactions_view_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'total number of records in result set'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    
                                    'courseid'=>new external_value(PARAM_INT, 'courseid of order', VALUE_OPTIONAL),
                                    'coursename'=>new external_value(PARAM_RAW, 'coursename', VALUE_OPTIONAL),
                                    'userid'=>new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL),
                                    'userfullname'=>new external_value(PARAM_RAW, 'user fullname', VALUE_OPTIONAL),
                                    'transactioncode' => new external_value(PARAM_RAW, 'airpay transactionid', VALUE_OPTIONAL),
                                    'orderid'=>new external_value(PARAM_RAW, 'order id', VALUE_OPTIONAL),
                                    'invoicedate' => new external_value(PARAM_RAW, 'order date', VALUE_OPTIONAL),
                                    'amount' => new external_value(PARAM_RAW, 'order amount', VALUE_OPTIONAL),
                                    'status' => new external_value(PARAM_RAW, 'order status', VALUE_OPTIONAL),
                                    'completed' => new external_value(PARAM_RAW, 'order completed status', VALUE_OPTIONAL),
                                    
                                ), 'individual records', VALUE_OPTIONAL
                            ), 'records info', VALUE_OPTIONAL
                        )
        ]);
    }

    public static function transactions_view_for_admin_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function transactions_view_for_admin(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global  $CFG, $PAGE;
        require_once($CFG->dirroot . '/local/biz_cart/lib.php');
        require_login();
        $PAGE->set_url('/local/biz_cart/view_transactions.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::transactions_view_for_admin_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
       
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $result_skill = get_user_transactions_for_admin($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  transactions_view_for_admin_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'total number of records in result set'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    
                                    'courseid'=>new external_value(PARAM_INT, 'courseid of order', VALUE_OPTIONAL),
                                    'coursename'=>new external_value(PARAM_RAW, 'coursename', VALUE_OPTIONAL),
                                    'userid'=>new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL),
                                    'userfullname'=>new external_value(PARAM_RAW, 'user fullname', VALUE_OPTIONAL),
                                    'transactioncode' => new external_value(PARAM_RAW, 'airpay transactionid', VALUE_OPTIONAL),
                                    'orderid'=>new external_value(PARAM_RAW, 'order id', VALUE_OPTIONAL),
                                    'invoicedate' => new external_value(PARAM_RAW, 'order date', VALUE_OPTIONAL),
                                    'amount' => new external_value(PARAM_RAW, 'order amount', VALUE_OPTIONAL),
                                    'status' => new external_value(PARAM_RAW, 'order status', VALUE_OPTIONAL),
				'completed' => new external_value(PARAM_RAW, 'order completed status', VALUE_OPTIONAL),
                                    
                                ), 'individual records', VALUE_OPTIONAL
                            ), 'records info', VALUE_OPTIONAL
                        )
        ]);
    }
        public static function view_course_transaction_log_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function view_course_transaction_log(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global  $CFG, $PAGE;
        require_once($CFG->dirroot . '/local/biz_cart/lib.php');
        require_login();
        $PAGE->set_url('/local/biz_cart/view_transactions.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::view_course_transaction_log_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
       
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $result_skill = get_course_transaction_log($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        $courselink = new moodle_url('/local/biz_cart/standard_log.php', ['courseid' => $filtervalues->courses]);
        $courselink = $courselink->out();
        return [
            'courselink' => $courselink,
            'totalcount' => $totalcount,
            'records' =>$data,
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  view_course_transaction_log_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'total number of records in result set'),
            'courselink' => new external_value(PARAM_RAW, 'courselink'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'courseid'=>new external_value(PARAM_INT, 'courseid of order', VALUE_OPTIONAL),
                                    'coursename'=>new external_value(PARAM_RAW, 'coursename', VALUE_OPTIONAL),
                                    'message'=>new external_value(PARAM_RAW, 'coursename', VALUE_OPTIONAL),
                                    'userid'=>new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL),
                                    'userfullname'=>new external_value(PARAM_RAW, 'user fullname', VALUE_OPTIONAL),
                                    'orderid'=>new external_value(PARAM_RAW, 'order id', VALUE_OPTIONAL),
                                    'paymentarea'=>new external_value(PARAM_RAW, 'courseid of order', VALUE_OPTIONAL),
                                    'order_state'=>new external_value(PARAM_RAW, 'courseid of order', VALUE_OPTIONAL),
                                    'orderdate' => new external_value(PARAM_RAW, 'order date', VALUE_OPTIONAL),
                                    
                                ), 'individual records', VALUE_OPTIONAL
                            ), 'records info', VALUE_OPTIONAL
                        )
        ]);
    }
           public static function view_course_standard_log_parameters() {
        return new external_function_parameters([
                'options' => new external_value(PARAM_RAW, 'The paging data for the service'),
                'dataoptions' => new external_value(PARAM_RAW, 'The data for the service'),
                'offset' => new external_value(PARAM_INT, 'Number of items to skip from the begging of the result set',
                    VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Maximum number of results to return',
                    VALUE_DEFAULT, 0),
                'contextid' => new external_value(PARAM_INT, 'contextid'),
                'filterdata' => new external_value(PARAM_RAW, 'The data for the service'),
            ]);
    }

    /**
     * Gets the list of users based on the login user
     *
     * @param int $options need to give options targetid,viewtype,perpage,cardclass
     * @param int $dataoptions need to give data which you need to get records
     * @param int $limit Maximum number of results to return
     * @param int $offset Number of items to skip from the beginning of the result set.
     * @param int $filterdata need to pass filterdata.
     * @return array The list of users and total users count.
     */
    public static function view_course_standard_log(
        $options,
        $dataoptions,
        $offset = 0,
        $limit = 0,
        $contextid,
        $filterdata
    ) {
        global  $CFG, $PAGE;
        require_once($CFG->dirroot . '/local/biz_cart/lib.php');
        require_login();
        $PAGE->set_url('/local/biz_cart/view_transactions.php', array());
        $PAGE->set_context($contextid);
        // Parameter validation.
        $params = self::validate_parameters(
            self::view_course_transaction_log_parameters(),
            [
                'options' => $options,
                'dataoptions' => $dataoptions,
                'offset' => $offset,
                'limit' => $limit,
                'contextid' => $contextid,
                'filterdata' => $filterdata
            ]
        );
        
        $offset = $params['offset'];
        $limit = $params['limit'];
        $decodedata = json_decode($params['dataoptions']);
        $filtervalues = json_decode($filterdata);

        $stable = new \stdClass();
        $stable->thead = true;
       
        $stable->thead = false;
        $stable->start = $offset;
        $stable->length = $limit;
        $result_skill = get_course_standard_log($stable,$filtervalues);
        $totalcount = $result_skill['count'];
        $data=$result_skill['data'];
        return [
            'totalcount' => $totalcount,
            'records' =>$data,
        ];

    }

    /**
     * Returns description of method result value.
     */ 
    public static function  view_course_standard_log_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'total number of records in result set'),
            'records' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'courseid'=>new external_value(PARAM_INT, 'courseid of order', VALUE_OPTIONAL),
                                    'coursename'=>new external_value(PARAM_RAW, 'coursename', VALUE_OPTIONAL),
                                    'eventname'=>new external_value(PARAM_RAW, 'coursename', VALUE_OPTIONAL),
                                    'userid'=>new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL),
                                    'userfullname'=>new external_value(PARAM_RAW, 'user fullname', VALUE_OPTIONAL),
                                    'action'=>new external_value(PARAM_RAW, 'order id', VALUE_OPTIONAL),
                                    'eventdate' => new external_value(PARAM_RAW, 'order date', VALUE_OPTIONAL),
                                    
                                ), 'individual records', VALUE_OPTIONAL
                            ), 'records info', VALUE_OPTIONAL
                        )
        ]);
    }
}
