<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sentientia_roles_list_roles' => [
        'classname'   => 'local_sentientia_roles\external\list_roles',
        'methodname'  => 'execute',
        'description' => 'List roles with capability counts, assignment counts, archetype.',
        'type'        => 'read',
        'capabilities' => 'local/sentientia_roles:view',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_roles_get_role_caps' => [
        'classname'   => 'local_sentientia_roles\external\get_role_caps',
        'methodname'  => 'execute',
        'description' => 'Get capability list for a role with current permission and inherited defaults.',
        'type'        => 'read',
        'capabilities' => 'local/sentientia_roles:view',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_roles_update_capability' => [
        'classname'   => 'local_sentientia_roles\external\update_capability',
        'methodname'  => 'execute',
        'description' => 'Set a capability permission on a role; writes audit log entry.',
        'type'        => 'write',
        'capabilities' => 'local/sentientia_roles:manage',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_roles_list_audit' => [
        'classname'   => 'local_sentientia_roles\external\list_audit',
        'methodname'  => 'execute',
        'description' => 'List audit log entries with filters (role, capability, user, date).',
        'type'        => 'read',
        'capabilities' => 'local/sentientia_roles:audit',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    // Phase 2 (v1.1) — bulk caps + role assignments tab.
    'local_sentientia_roles_bulk_update_capability' => [
        'classname'   => 'local_sentientia_roles\external\bulk_update_capability',
        'methodname'  => 'execute',
        'description' => 'Apply same capability + permission to N roles in one batch.',
        'type'        => 'write',
        'capabilities' => 'local/sentientia_roles:manage',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_roles_list_assignments' => [
        'classname'   => 'local_sentientia_roles\external\list_role_assignments',
        'methodname'  => 'execute',
        'description' => 'List user assignments for a role at the system context.',
        'type'        => 'read',
        'capabilities' => 'local/sentientia_roles:view',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_roles_assign_user' => [
        'classname'   => 'local_sentientia_roles\external\assign_user',
        'methodname'  => 'execute',
        'description' => 'Assign a user to a role at system context (audited).',
        'type'        => 'write',
        'capabilities' => 'local/sentientia_roles:assign',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_sentientia_roles_unassign_user' => [
        'classname'   => 'local_sentientia_roles\external\unassign_user',
        'methodname'  => 'execute',
        'description' => 'Remove a user\'s role assignment (audited).',
        'type'        => 'write',
        'capabilities' => 'local/sentientia_roles:assign',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
