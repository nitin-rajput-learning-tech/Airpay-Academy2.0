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
 * Web service definitions for local_sentientia_courses.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_courses_list_courses' => [
        'classname'    => 'local_sentientia_courses\external\list_courses',
        'description'  => 'List courses for the shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:view',
    ],
    'local_sentientia_courses_toggle_visibility' => [
        'classname'    => 'local_sentientia_courses\external\toggle_visibility',
        'description'  => 'Show or hide a course',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:visibility',
    ],
    'local_sentientia_courses_delete_course' => [
        'classname'    => 'local_sentientia_courses\external\delete_course',
        'description'  => 'Delete a course',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:delete',
    ],

    // Phase F.2 (2026-05-08) — featured-courses widget admin.
    'local_sentientia_courses_add_featured' => [
        'classname'    => 'local_sentientia_courses\external\add_featured',
        'description'  => 'Pin a course to the featured-courses widget',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:manage',
    ],
    'local_sentientia_courses_remove_featured' => [
        'classname'    => 'local_sentientia_courses\external\remove_featured',
        'description'  => 'Unpin a course from the featured-courses widget',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:manage',
    ],
    'local_sentientia_courses_reorder_featured' => [
        'classname'    => 'local_sentientia_courses\external\reorder_featured',
        'description'  => 'Reorder featured-courses entries',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:manage',
    ],

    // Phase 3 B.2 (2026-05-11) — native enrolment UI.
    'local_sentientia_courses_list_course_enrolments' => [
        'classname'    => 'local_sentientia_courses\external\list_course_enrolments',
        'description'  => 'List users enrolled in a course',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:view',
    ],
    'local_sentientia_courses_enrol_single' => [
        'classname'    => 'local_sentientia_courses\external\enrol_single',
        'description'  => 'Enrol one user in a course by email/empid/username',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:enrol',
    ],
    'local_sentientia_courses_unenrol_single' => [
        'classname'    => 'local_sentientia_courses\external\unenrol_single',
        'description'  => 'Unenrol one user from a course (manual instance)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:enrol',
    ],

    // Sprint C (2026-05-13) — cross-tenant course sharing.
    'local_sentientia_courses_share_course' => [
        'classname'    => 'local_sentientia_courses\external\share_course',
        'description'  => 'Share a course to one or more tenants',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:share_to_tenant',
    ],
    'local_sentientia_courses_unshare_course' => [
        'classname'    => 'local_sentientia_courses\external\unshare_course',
        'description'  => 'Withdraw a course-share from a tenant',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:share_to_tenant',
    ],
    'local_sentientia_courses_list_course_shares' => [
        'classname'    => 'local_sentientia_courses\external\list_course_shares',
        'description'  => 'Get current + historic shares for a course',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:view',
    ],

    // Sprint D (2026-05-13) — pull/request workflow.
    'local_sentientia_courses_request_course' => [
        'classname'    => 'local_sentientia_courses\external\request_course',
        'description'  => 'Request an Airpay course be shared to my tenant',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:request_course',
    ],
    'local_sentientia_courses_approve_request' => [
        'classname'    => 'local_sentientia_courses\external\approve_request',
        'description'  => 'Approve a pending share-request (admin)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:approve_request',
    ],
    'local_sentientia_courses_reject_request' => [
        'classname'    => 'local_sentientia_courses\external\reject_request',
        'description'  => 'Reject a pending share-request (admin)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_courses:approve_request',
    ],
];
