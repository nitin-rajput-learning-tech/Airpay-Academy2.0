<?php
// This file is part of eAbyas
//
// Copyright eAbyas Info Solutons Pvt Ltd, India
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @author eabyas  <info@eabyas.in>
 * @package BizLMS
 * @subpackage local_users
 */


define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/local/costcenter/lib.php');
$page = optional_param('page', 0, PARAM_INT);
$sort = optional_param('sort', 'name', PARAM_ALPHANUM);
$dir = optional_param('dir', 'ASC', PARAM_ALPHA);
$extrasql = optional_param('extrasql', '', PARAM_RAW);
$params = optional_param('params', '', PARAM_RAW);
$perpage = 10;
$params = json_decode($params, true);
global $CFG, $USER;
$myuser = new local_users\functions\users();
$costcenter = new costcenter();
$categorycontext = (new \local_users\lib\accesslib())::get_module_context();
require_login();
if (!has_capability('local/users:manage', $categorycontext) ) {
    throw new moodle_exception('You dont have a permission to view this page.');
}

$context = (new \local_users\lib\accesslib())::get_module_context();
$extracolumns = get_extra_user_fields($context);
$columns = array_merge(array('firstname', 'lastname'), $extracolumns,
    array('username', 'idnumber', 'costcenter', 'role', 'lastaccess'));
foreach ($columns as $column) {
    if ($column == 'costcenter') {
        $string[$column] = get_string('costcenterid', 'local_costcenter');
    } else {
        $string[$column] = get_user_field_name($column);
    }

    if ($sort != $column) {
        $columnicon = "";
        if ($column == "lastaccess") {
            $columndir = "DESC";
        } else {
            $columndir = "ASC";
        }
    } else {
        $columndir = $dir == "ASC" ? "DESC" : "ASC";
        if ($column == "lastaccess") {
            $columnicon = ($dir == "ASC") ? "sort_desc" : "sort_asc";
        } else {
            $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
        }
        $columnicon = "<img class='iconsort' src=\"" . $OUTPUT->image_url('t/' . $columnicon) . "\" alt=\"\" />";
    }
    if (!($column == 'costcenter' || $column == 'role')) {
        $$column = "<a href=\"index.php?sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
    } else {
        $$column = $string[$column];
    }
}

$override = new \stdClass();
$override->firstname = 'firstname';
$override->lastname = 'lastname';
$fullnamelanguage = get_string('fullnamedisplay', '', $override);
if (($CFG->fullnamedisplay == 'firstname lastname') || ( $CFG->fullnamedisplay == 'firstname') ||
    ( $CFG->fullnamedisplay == 'language' && $fullnamelanguage == 'firstname lastname' )) {
    $fullnamedisplay = "$firstname / $lastname";
    if ($sort == "name") { // If sort has already been set to something else then ignore.
        $sort = "firstname";
    }
} else {
    $fullnamedisplay = "$lastname / $firstname";
    if ($sort == "name") { // This should give the desired sorting based on fullnamedisplay.
        $sort = "lastname";
    }
}
$usercount = $myuser->get_usercount();
$usersearchcount = $myuser->get_usercount($extrasql, $params);

$users = $myuser->get_users_listing($sort, $dir, 0, $usercount, $extrasql, $params, $context);
$baseurl = new moodle_url('/local/users/index.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage));
if (!$users) {
    $match = array();
    $table = new html_table();
    $data = array();
} else {
    $table = new html_table();
    $table->id = "usertable";
    $table->head[] = $fullnamedisplay;
    foreach ($extracolumns as $field) {
        $table->head[] = ${$field};
    }
    $table->head[] = get_string('login', 'local_users');
    $table->head[] = get_string('idnumber');
    $table->head[] = $costcenter;
    $table->head[] = $lastaccess;
    $data = array();
    foreach ($users as $user) {
        $line = array();
        $line[] = fullname($user, true);
        $line[] = $user->email;
        $line[] = $user->username;
        $line[] = $user->idnumber;
        $line[] = $myuser->get_costcenternames($user);
        $line[] = ($user->lastaccess) ? format_time(time() - $user->lastaccess) : get_string('never');
        if ($user->suspended) {
            foreach ($line as $k => $v) {
                $line[$k] = html_writer::tag('span', $v, array('class' => 'usersuspended'));
            }
        }
        if (has_capability('local/users:manage', $categorycontext)) {
                $data[] = $line;
        }
    }
}
$table->id = "usertable";
$table->size = array('20%', '20%', '15%', '15%', '20%', '10%');
$table->align = array('left', 'left', 'left', 'left', 'left', 'left');
$table->width = '100%';
$table->data = $data;
 require_once($CFG->libdir . '/csvlib.class.php');
    $matrix = array();
    $filename = 'report';
if (!empty($table->head)) {
        $countcols = count($table->head);
        $keys = array_keys($table->head);
        $lastkey = end($keys);
    foreach ($table->head as $key => $heading) {
        $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(\local_costcenter\lib::
            strip_tags_custom(nl2br($heading))));
    }
}
if (!empty($table->data)) {
    foreach ($table->data as $rkey => $row) {
        foreach ($row as $key => $item) {
            $matrix[$rkey + 1][$key] = str_replace("\n", ' ',
                htmlspecialchars_decode(\local_costcenter\lib::strip_tags_custom(nl2br($item))));
        }
    }
}
    $csvexport = new csv_export_writer();
    $csvexport->set_filename($filename);
foreach ($matrix as $ri => $col) {
        $csvexport->add_data($col);
}
    $csvexport->download_file();
    exit;
