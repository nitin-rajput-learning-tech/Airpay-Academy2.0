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
    // ── Classroom listing + status changes (existing) ────────────────────
    'local_airpay_classroom_list_classrooms' => [
        'classname'    => 'local_airpay_classroom\external\list_classrooms',
        'description'  => 'List classrooms for the shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],
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

    // ── Sessions tab (G-02) ──────────────────────────────────────────────
    'local_airpay_classroom_list_sessions' => [
        'classname'    => 'local_airpay_classroom\external\list_classroom_sessions',
        'description'  => 'List sessions for a classroom (Sessions tab datatable)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],
    'local_airpay_classroom_delete_session' => [
        'classname'    => 'local_airpay_classroom\external\delete_session',
        'description'  => 'Delete a session and its attendance records',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:update',
    ],

    // ── Users / roster tab (G-02) ────────────────────────────────────────
    'local_airpay_classroom_list_users' => [
        'classname'    => 'local_airpay_classroom\external\list_classroom_users',
        'description'  => 'List enrolled users for a classroom (Users tab datatable)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],
    'local_airpay_classroom_unenrol_user' => [
        'classname'    => 'local_airpay_classroom\external\unenrol_classroom_user',
        'description'  => 'Remove a user from a classroom roster (cascades attendance)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:update',
    ],

    // ── Attendance (G-02) ────────────────────────────────────────────────
    'local_airpay_classroom_list_attendance' => [
        'classname'    => 'local_airpay_classroom\external\list_session_attendance',
        'description'  => 'List attendance for a session (Attendance UI)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],
    'local_airpay_classroom_mark_attendance' => [
        'classname'    => 'local_airpay_classroom\external\mark_session_attendance',
        'description'  => 'Mark a single user attendance for a session',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:attendance',
    ],
    'local_airpay_classroom_bulk_mark_attendance' => [
        'classname'    => 'local_airpay_classroom\external\bulk_mark_attendance',
        'description'  => 'Bulk-mark attendance for many users at once',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:attendance',
    ],
];
