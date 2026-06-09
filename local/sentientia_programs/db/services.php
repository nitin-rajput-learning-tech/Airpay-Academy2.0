<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    // ── Program listing + status changes (existing) ──────────────────────
    'local_sentientia_programs_list_programs' => [
        'classname'    => 'local_sentientia_programs\external\list_programs',
        'description'  => 'List programs for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:view',
    ],
    'local_sentientia_programs_change_status' => [
        'classname'    => 'local_sentientia_programs\external\change_status',
        'description'  => 'Change program status (draft/active/archived)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:update',
    ],
    'local_sentientia_programs_delete_program' => [
        'classname'    => 'local_sentientia_programs\external\delete_program',
        'description'  => 'Delete a certification program',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:delete',
    ],

    // ── Levels tab (G-03) ────────────────────────────────────────────────
    'local_sentientia_programs_list_levels' => [
        'classname'    => 'local_sentientia_programs\external\list_program_levels',
        'description'  => 'List levels for a program (Levels tab datatable)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:view',
    ],
    'local_sentientia_programs_delete_level' => [
        'classname'    => 'local_sentientia_programs\external\delete_level',
        'description'  => 'Delete a level (cascades to course assignments)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:update',
    ],
    'local_sentientia_programs_reorder_levels' => [
        'classname'    => 'local_sentientia_programs\external\reorder_levels',
        'description'  => 'Reorder levels in a program',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:update',
    ],

    // ── Courses-per-level (G-03) ─────────────────────────────────────────
    'local_sentientia_programs_list_level_courses' => [
        'classname'    => 'local_sentientia_programs\external\list_level_courses',
        'description'  => 'List courses assigned to a level',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:view',
    ],
    'local_sentientia_programs_unassign_level_course' => [
        'classname'    => 'local_sentientia_programs\external\unassign_level_course',
        'description'  => 'Remove a course from a level',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:update',
    ],

    // ── Program enrolment / Users tab (G-03) ─────────────────────────────
    'local_sentientia_programs_list_users' => [
        'classname'    => 'local_sentientia_programs\external\list_program_users',
        'description'  => 'List enrolled users for a program (Users tab datatable)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:view',
    ],
    'local_sentientia_programs_unenrol_user' => [
        'classname'    => 'local_sentientia_programs\external\unenrol_program_user',
        'description'  => 'Unenrol a user from a program',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:enrol',
    ],

    // P1 #9 (2026-05-16) — target-audience bulk enrol (mirrors W2 #8 in
    // sentientia_learningpath).
    'local_sentientia_programs_preview_audience' => [
        'classname'    => 'local_sentientia_programs\external\preview_audience',
        'description'  => 'Preview users matching a target-audience filter before bulk-enrolling',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:enrol',
    ],
    'local_sentientia_programs_bulk_enrol_by_audience' => [
        'classname'    => 'local_sentientia_programs\external\bulk_enrol_by_audience',
        'description'  => 'Resolve a target-audience filter and bulk-enrol all matching users into a program',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_programs:enrol',
    ],
];
