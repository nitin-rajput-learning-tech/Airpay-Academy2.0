<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_org_delete_org' => [
        'classname'    => 'local_sentientia_org\external\delete_org',
        'description'  => 'Delete an organisation node (refuses if tenant, has descendants, or has users)',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_org:manage',
    ],
    'local_sentientia_org_toggle_visibility' => [
        'classname'    => 'local_sentientia_org\external\toggle_visibility',
        'description'  => 'Toggle an organisation between active and hidden',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_org:manage',
    ],
    // 2026-05-15 parity audit: feeds the hierarchy cascade filter on
    // admin list pages. One AJAX call per cascade level
    // (Org → Dept → Sub-dept → L4 → L5). The same WS is reused by
    // Manage Users, Manage Courses, Manage Programs, Classroom, etc.
    'local_sentientia_org_list_children' => [
        'classname'    => 'local_sentientia_org\external\list_children',
        'description'  => 'List child org nodes under a parent (cascade filter)',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/sentientia_users:view',
    ],
];
