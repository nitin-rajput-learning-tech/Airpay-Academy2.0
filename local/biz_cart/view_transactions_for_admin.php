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
 * TODO describe file view_transactions
 *
 * @package    local_biz_cart
 * @copyright  2024 Moodle India <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();

$url = new moodle_url('/local/biz_cart/view_transactions.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading('All Transactions');
$PAGE->requires->css('/local/notifications/css/jquery.dataTables.min.css', true);
$PAGE->requires->js(new moodle_url('/local/notifications/js/jquery.dataTables.min.js'),true);
echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('local_biz_cart');
echo $renderer->view_user_transactions_for_admin();
echo $OUTPUT->footer();
