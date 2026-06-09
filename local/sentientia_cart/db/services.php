<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_cart_add_item' => [
        'classname'    => 'local_sentientia_cart\external\add_item',
        'description'  => 'Add a course to the current user cart',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:purchase',
    ],
    'local_sentientia_cart_remove_item' => [
        'classname'    => 'local_sentientia_cart\external\remove_item',
        'description'  => 'Remove a course from the current user cart',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:purchase',
    ],
    'local_sentientia_cart_get_cart' => [
        'classname'    => 'local_sentientia_cart\external\get_cart',
        'description'  => 'Get current cart contents + totals',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:view',
    ],
    'local_sentientia_cart_checkout' => [
        'classname'    => 'local_sentientia_cart\external\checkout',
        'description'  => 'Convert cart to order, initiate payment',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:purchase',
    ],
    'local_sentientia_cart_list_orders' => [
        'classname'    => 'local_sentientia_cart\external\list_orders',
        'description'  => 'List own orders (or all orders for admins)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:view',
    ],
    'local_sentientia_cart_get_order' => [
        'classname'    => 'local_sentientia_cart\external\get_order',
        'description'  => 'Get order detail by order number',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:view',
    ],
    'local_sentientia_cart_refund' => [
        'classname'    => 'local_sentientia_cart\external\refund_order',
        'description'  => 'Issue a full or partial refund (admin)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:refund',
    ],
    'local_sentientia_cart_set_price' => [
        'classname'    => 'local_sentientia_cart\external\set_course_price',
        'description'  => 'Set / unset price for a course (admin)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:manageprices',
    ],
    'local_sentientia_cart_daily_sums' => [
        'classname'    => 'local_sentientia_cart\external\daily_sums',
        'description'  => 'Daily payment sums report for finance',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_cart:viewallorders',
    ],
];
