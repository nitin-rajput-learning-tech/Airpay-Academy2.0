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
 * Checkout page for Cashiers
 *
 * @package         local_biz_cart
 * @author          Moodle India
 * @copyright       2024 Moodle India <info@moodle.com>
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use local_biz_cart\biz_cart;
use local_biz_cart\form\dynamic_select_users;
use local_biz_cart\output\cashier;

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/local/biz_cart/lib.php';

require_login();
$context = context_system::instance();
// Only cashiers can visit this page.
require_capability('local/biz_cart:cashier', $context);

// Get the id of the page to be displayed.
$userid = optional_param('userid', null, PARAM_INT);

// If there is no user, we unset the buy for user variable and delete the cart for active user.
if (!$userid) {
    biz_cart::buy_for_user(0);
    biz_cart::delete_all_items_from_cart($USER->id);
} else {
    biz_cart::buy_for_user($userid);
}

// We use our output class, but only need the generated array.
$cashier = new cashier($userid, true);
$data = $cashier->returnaslist();

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/biz_cart/cashier.php");
$PAGE->set_title(get_string('cashier', 'local_biz_cart'));
$PAGE->set_heading(get_string('cashier', 'local_biz_cart'));

// Set the page layout.
$PAGE->set_pagelayout('base');

// Output the header.
echo $OUTPUT->header();

$context = context_system::instance();
if (has_capability('local/biz_cart:cashier', $context)) {
    $data['additonalcashiersection'] = format_text(get_config('local_biz_cart', 'additonalcashiersection'));
}

$data['userid'] = $userid;
$data['wwwroot'] = $CFG->wwwroot;

$selectuserform = new dynamic_select_users();
$data['selectuserform'] = $selectuserform->render();

// We only allow manual booking, if the user has the capability to do this.
if (has_capability('local/biz_cart:cashiermanualrebook', $context)
    && get_config('local_biz_cart', 'manualrebookingisallowed')) {
    $data['allowmanualrebooking'] = true;
}

// We only allow cash transfer if the user has the capability to do this.
if (has_capability('local/biz_cart:cashtransfer', $context)) {
    $data['allowcashtransfer'] = true;
}

// We need a param to check in the css if the version is minimum 4.2.
if ($CFG->version >= 2023042400) {
    $data['moodleversionminfourtwo'] = 'moodleversionminfourtwo';
}

// Convert numbers to strings with 2 fixed decimals right before rendering.
biz_cart::convert_prices_to_number_format($data);

echo $OUTPUT->render_from_template('local_biz_cart/cashier', $data);

// Now output the footer.
echo $OUTPUT->footer();
