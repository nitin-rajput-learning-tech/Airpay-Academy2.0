<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Webservice to reload table.
 *
 * @package     local_biz_cart
 * @category    upgrade
 * @copyright   2024 Moodle India <info@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$services = [
    'Wunderbyte biz_cart external' => [
        'functions' => [
            'local_biz_cart_add_item',
            'local_biz_cart_delete_item',
            'local_biz_cart_delete_all_items_from_cart',
            'local_biz_cart_get_biz_cart_items',
            'local_biz_cart_credit_paid_back',
            'local_biz_cart_cancel_purchase',
            'local_biz_cart_get_price',
            'local_biz_cart_confirm_cash_payment',
            'local_biz_cart_quota_consumed',
        ],
        'restrictedusers' => 1,
        'shortname' => 'local_biz_cart_external',
        'enabled' => 1,
    ],
];

$functions = [
    'local_biz_cart_add_item' => [
        'classname' => 'local_biz_cart\external\add_item_to_cart',
        'description' => 'Add an Item to the shopping cart',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_delete_item' => [
        'classname' => 'local_biz_cart\external\delete_item_from_cart',
        'description' => 'Delete Item from cart',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_delete_all_items_from_cart' => [
        'classname' => 'local_biz_cart\external\delete_all_items_from_cart',
        'description' => 'Delete All Items from cart',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_get_biz_cart_items' => [
        'classname' => 'local_biz_cart\external\get_biz_cart_items',
        'description' => 'Get shopping cart items',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_confirm_cash_payment' => [
        'classname' => 'local_biz_cart\external\confirm_cash_payment',
        'description' => 'Confirm cash payment by cashier',
        'type' => 'write',
        'capabilities' => 'local/biz_cart:cashier',
        'ajax' => 1,
    ],
    'local_biz_cart_cancel_purchase' => [
        'classname' => 'local_biz_cart\external\cancel_purchase',
        'description' => 'Cancel purchase',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_get_price' => [
        'classname' => 'local_biz_cart\external\get_price',
        'description' => 'Get price',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_credit_paid_back' => [
        'classname' => 'local_biz_cart\external\credit_paid_back',
        'description' => 'Register paid back credit',
        'type' => 'write',
        'capabilities' => 'local/biz_cart:cashier',
        'ajax' => 1,
    ],
    'local_biz_cart_get_history_items' => [
        'classname' => 'local_biz_cart\external\get_history_items',
        'description' => 'Get History items',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_quota_consumed' => [
        'classname' => 'local_biz_cart\external\get_quota_consumed',
        'description' => 'Return the consumed quota from a given item',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_search_users' => [
        'classname' => 'local_biz_cart\external\search_users',
        'description' => 'Search a list of all users',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_mark_item_for_rebooking' => [
        'classname' => 'local_biz_cart\external\mark_for_rebooking',
        'description' => 'Marks history item for rebooking',
        'type' => 'write',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_get_history_item' => [
        'classname' => 'local_biz_cart\external\get_history_item',
        'description' => 'Gets the latest history item',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => 1,
    ],
    'local_biz_cart_transactions_view' => [
        'classname'   => 'local_biz_cart_external',
        'methodname'  => 'transactions_view',
        'classpath'   => 'local/biz_cart/classes/external.php',
        'description' => 'To get the transaction history for user',
        'type'        => 'read',
        'ajax' => true,
    ],
    'local_biz_cart_transactions_view_for_admin' => [
        'classname'   => 'local_biz_cart_external',
        'methodname'  => 'transactions_view_for_admin',
        'classpath'   => 'local/biz_cart/classes/external.php',
        'description' => 'To get the transaction history for user',
        'type'        => 'read',
        'ajax' => true,
    ],
     'local_biz_cart_view_course_transaction_log' => [
        'classname'   => 'local_biz_cart_external',
        'methodname'  => 'view_course_transaction_log',
        'classpath'   => 'local/biz_cart/classes/external.php',
        'description' => 'To get the transaction history for user',
        'type'        => 'read',
        'ajax' => true,
    ],

     'local_biz_cart_view_course_standard_log' => [
        'classname'   => 'local_biz_cart_external',
        'methodname'  => 'view_course_standard_log',
        'classpath'   => 'local/biz_cart/classes/external.php',
        'description' => 'To get the transaction history for user',
        'type'        => 'read',
        'ajax' => true,
    ],
];
