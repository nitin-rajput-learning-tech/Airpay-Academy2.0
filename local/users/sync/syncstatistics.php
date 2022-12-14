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
 * @package   local
 * @subpackage  users
 * @author eabyas  <info@eabyas.in>
 **/
require_once(dirname(__FILE__) . '/../../../config.php');
global $DB, $PAGE, $CFG, $OUTPUT;

$PAGE->requires->js_call_amd('local_users/datatablesamd', 'load', array());

$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
$PAGE->set_context($categorycontext);

$PAGE->set_url('/local/users/sync/syncstatistics.php');
$PAGE->set_pagelayout('standard');
$strheading = get_string('sync_stats', 'local_users');
$PAGE->set_title($strheading);

require_login();
$PAGE->navbar->add(get_string('pluginname', 'local_users'), new moodle_url('/local/users/index.php'));
$PAGE->navbar->add($strheading);
$PAGE->set_heading(get_string('syncstatistics', 'local_users'));
echo $OUTPUT->header();

if (!(has_capability('local/users:create', $categorycontext) || is_siteadmin())) {
    echo print_error('nopermission');
}
echo html_writer::link(new moodle_url('/local/users/index.php'),  get_string('back', 'local_users'), array('id' => 'sync_data'));

$userrenderer = $PAGE->get_renderer('local_users');
echo $userrenderer->display_sync_statics();
echo $OUTPUT->footer();
