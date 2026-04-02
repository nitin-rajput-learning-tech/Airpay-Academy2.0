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
require(__DIR__ .'/../../../config.php');
require_login();
set_time_limit(0);
ini_set('max_execution_time', '0'); // for infinite time of execution
ini_set('memory_limit', '-1');

global $CFG, $OUTPUT, $DB;
echo $OUTPUT->header();
    $userlib = new \local_users\functions\users();
    $return = $userlib->sync_execute();
    function extract($return);
    echo '<div class="critera_error1"><h3 style="text-decoration: underline;">Employee Service sync status</h3>
            <div class=local_users_sync_success>Total '.$userscreated . ' new users added to the system.</div>
            <div class=local_users_sync_success>Total '.$usersupdated . ' users details updated.</div>
            <div class=local_users_sync_error>Total '.$errorscount . ' errors occured in the sync update.</div></div>
            <div class=local_users_sync_warning>Total '.$warningscount . ' warnings occured in the sync update.</div>
            ';
echo $OUTPUT->footer();
