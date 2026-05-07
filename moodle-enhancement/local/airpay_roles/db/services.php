<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_roles_list_roles' => [
        'classname'   => 'local_airpay_roles\external\list_roles',
        'methodname'  => 'execute',
        'description' => 'List roles with capability counts, assignment counts, archetype.',
        'type'        => 'read',
        'capabilities' => 'local/airpay_roles:view',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_airpay_roles_get_role_caps' => [
        'classname'   => 'local_airpay_roles\external\get_role_caps',
        'methodname'  => 'execute',
        'description' => 'Get capability list for a role with current permission and inherited defaults.',
        'type'        => 'read',
        'capabilities' => 'local/airpay_roles:view',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_airpay_roles_update_capability' => [
        'classname'   => 'local_airpay_roles\external\update_capability',
        'methodname'  => 'execute',
        'description' => 'Set a capability permission on a role; writes audit log entry.',
        'type'        => 'write',
        'capabilities' => 'local/airpay_roles:manage',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'local_airpay_roles_list_audit' => [
        'classname'   => 'local_airpay_roles\external\list_audit',
        'methodname'  => 'execute',
        'description' => 'List audit log entries with filters (role, capability, user, date).',
        'type'        => 'read',
        'capabilities' => 'local/airpay_roles:audit',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
