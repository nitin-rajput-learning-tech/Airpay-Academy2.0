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

    // Phase 3 B.4 (2026-05-11) — waiting list when classroom hits capacity.
    'local_airpay_classroom_waitlist_join' => [
        'classname'    => 'local_airpay_classroom\external\waitlist_join',
        'description'  => 'Join the waiting list for a classroom',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],
    'local_airpay_classroom_waitlist_leave' => [
        'classname'    => 'local_airpay_classroom\external\waitlist_leave',
        'description'  => 'Leave the waiting list',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],
    'local_airpay_classroom_list_waitlist' => [
        'classname'    => 'local_airpay_classroom\external\list_waitlist',
        'description'  => 'List the waiting list for a classroom',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:view',
    ],

    // P1 #13 (2026-05-16) — target-audience bulk enrol (mirrors W2 #8 +
    // P1 #9 in airpay_learningpath / airpay_programs).
    'local_airpay_classroom_preview_audience' => [
        'classname'    => 'local_airpay_classroom\external\preview_audience',
        'description'  => 'Preview users matching a target-audience filter before bulk-enrolling',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:enrol',
    ],
    'local_airpay_classroom_bulk_enrol_by_audience' => [
        'classname'    => 'local_airpay_classroom\external\bulk_enrol_by_audience',
        'description'  => 'Resolve a target-audience filter and bulk-enrol all matching users into a classroom',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_classroom:enrol',
    ],
];
