<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_skills_list_skills' => [
        'classname'    => 'local_sentientia_skills\external\list_skills',
        'description'  => 'List skills for shared datatable',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_delete_skill' => [
        'classname'    => 'local_sentientia_skills\external\delete_skill',
        'description'  => 'Delete a skill (and all role/course/user mappings)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_delete_category' => [
        'classname'    => 'local_sentientia_skills\external\delete_category',
        'description'  => 'Delete a skill category (only if no skills reference it)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],

    // Phase A — skill-level definitions
    'local_sentientia_skills_get_skill_levels' => [
        'classname'    => 'local_sentientia_skills\external\get_skill_levels',
        'description'  => 'Get level-1..max_level definitions for one skill',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_save_skill_level' => [
        'classname'    => 'local_sentientia_skills\external\save_skill_level',
        'description'  => 'Upsert one skill-level definition',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],

    // Phase A — designation-skill matrix
    'local_sentientia_skills_list_designation_skills' => [
        'classname'    => 'local_sentientia_skills\external\list_designation_skills',
        'description'  => 'Required-skill rows for a given designation',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_save_designation_skill' => [
        'classname'    => 'local_sentientia_skills\external\save_designation_skill',
        'description'  => 'Upsert one designation-skill requirement',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_delete_designation_skill' => [
        'classname'    => 'local_sentientia_skills\external\delete_designation_skill',
        'description'  => 'Remove one designation-skill row',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_copy_designation' => [
        'classname'    => 'local_sentientia_skills\external\copy_designation',
        'description'  => 'Copy all required-skill rows from one designation to another',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],

    // Phase A.2 (2026-05-08) — course-skill mapping admin
    'local_sentientia_skills_list_course_skills' => [
        'classname'    => 'local_sentientia_skills\external\list_course_skills',
        'description'  => 'Skills mapped to a course (with target level)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_save_course_skill' => [
        'classname'    => 'local_sentientia_skills\external\save_course_skill',
        'description'  => 'Upsert one course-skill mapping',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_delete_course_skill' => [
        'classname'    => 'local_sentientia_skills\external\delete_course_skill',
        'description'  => 'Delete one course-skill mapping',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],
    'local_sentientia_skills_search_courses' => [
        'classname'    => 'local_sentientia_skills\external\search_courses',
        'description'  => 'Lookup courses by name/shortname for the picker',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:manage',
    ],

    // P1 #25 (2026-05-20) — learner self-rate.
    // Capability check is done inside the endpoint because it varies
    // by `userid` parameter (self → :self_rate cap, backfill → :manage cap),
    // so we don't whitelist a single cap here.
    'local_sentientia_skills_self_rate_skill' => [
        'classname'    => 'local_sentientia_skills\external\self_rate_skill',
        'description'  => 'Learner self-attests a level (1..max_level) for a skill.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_skills:self_rate',
    ],
];
