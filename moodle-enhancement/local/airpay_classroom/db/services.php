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

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_classroom_change_status' => [
        'classname'    => 'local_airpay_classroom\external\change_status',
        'description'  => 'Change classroom status (active/cancelled/completed)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:update',
    ],
    'local_airpay_classroom_delete_classroom' => [
        'classname'    => 'local_airpay_classroom\external\delete_classroom',
        'description'  => 'Delete a classroom and all its sessions',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:delete',
    ],
];
