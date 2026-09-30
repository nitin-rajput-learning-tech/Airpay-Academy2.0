<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_roles.
 *
 * Per CLAUDE.md section 5 every new user-visible feature ships behind a default-OFF flag whose OFF state matches
 * today's behaviour.
 *
 * @package local_sentientia_roles
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.roles.org_assignments' => [
        'default'     => false,
        'description' => 'Role holders at organisation level (ADR-032 org_roles, 2026-09-30). When OFF (default),
                          the role-holder list (list_role_assignments) shows only assignments at the system
                          context, as before. When ON, it also lists assignments at the course category of each
                          organisation, which is where BizLMS org roles live, marked with their organisation and
                          read-only here. A caller who is not cross-tenant sees only the holders in their own
                          tenant, at organisations inside their own tenant. The flag controls the list only: the
                          assignments themselves exist whatever the flag says.',
    ],

];
