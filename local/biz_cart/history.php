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
 * Pages main view page.
 *
 * @package         local_biz_cart
 * @author          Moodle India
 * @copyright       2024 Moodle India <info@moodle.com>
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use local_biz_cart\biz_cart;

require_once dirname(dirname(dirname(__FILE__))) . '/config.php';
require_once $CFG->dirroot . '/local/biz_cart/lib.php';

require_login();

global $USER;
// Get the id of the page to be displayed.
$success = optional_param('success', null, PARAM_INT);

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/biz_cart/checkout.php");
$PAGE->set_title(get_string('yourcart', 'local_biz_cart'));
$PAGE->set_heading(get_string('yourcart', 'local_biz_cart'));

// Set the page layout.

$PAGE->set_pagelayout('standard');

// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;
$data = biz_cart::local_biz_cart_get_cache_data($userid);
$data["mail"] = $USER->email;
$data["name"] = $USER->firstname . $USER->lastname;
if (isset($success)) {
    if ($success) {
        $data['success'] = 1;
    } else {
        $data['failed'] = 1;
    }
}
$data['additonalcashiersection'] = get_config('local_biz_cart', 'additonalcashiersection');

$test = get_users(true, '', true, [], '', '', '', '', $recordsperpage = 21);

// Convert numbers to strings with 2 fixed decimals right before rendering.
biz_cart::convert_prices_to_number_format($data);

echo $OUTPUT->render_from_template('local_biz_cart/checkout', $data);
// Now output the footer.
echo $OUTPUT->footer();
