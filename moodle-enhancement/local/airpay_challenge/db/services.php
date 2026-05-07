<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_airpay_challenge_list_challenges' => [
        'classname'    => 'local_airpay_challenge\external\list_challenges',
        'methodname'   => 'execute',
        'description'  => 'Paginated list of challenges (active by default).',
        'type'         => 'read',
        'capabilities' => 'local/airpay_challenge:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_get_challenge' => [
        'classname'    => 'local_airpay_challenge\external\get_challenge',
        'methodname'   => 'execute',
        'description'  => 'Get one challenge with caller progress.',
        'type'         => 'read',
        'capabilities' => 'local/airpay_challenge:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_create_challenge' => [
        'classname'    => 'local_airpay_challenge\external\create_challenge',
        'methodname'   => 'execute',
        'description'  => 'Create a new challenge.',
        'type'         => 'write',
        'capabilities' => 'local/airpay_challenge:manage',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_update_challenge' => [
        'classname'    => 'local_airpay_challenge\external\update_challenge',
        'methodname'   => 'execute',
        'description'  => 'Update an existing challenge.',
        'type'         => 'write',
        'capabilities' => 'local/airpay_challenge:manage',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_delete_challenge' => [
        'classname'    => 'local_airpay_challenge\external\delete_challenge',
        'methodname'   => 'execute',
        'description'  => 'Delete a challenge + its attempts + leaderboard rows.',
        'type'         => 'write',
        'capabilities' => 'local/airpay_challenge:manage',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_join_challenge' => [
        'classname'    => 'local_airpay_challenge\external\join_challenge',
        'methodname'   => 'execute',
        'description'  => 'Join (enrol) the caller into a challenge.',
        'type'         => 'write',
        'capabilities' => 'local/airpay_challenge:participate',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_leave_challenge' => [
        'classname'    => 'local_airpay_challenge\external\leave_challenge',
        'methodname'   => 'execute',
        'description'  => 'Leave a challenge (deletes the caller\'s attempt row).',
        'type'         => 'write',
        'capabilities' => 'local/airpay_challenge:participate',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_airpay_challenge_get_leaderboard' => [
        'classname'    => 'local_airpay_challenge\external\get_leaderboard',
        'methodname'   => 'execute',
        'description'  => 'Top-N leaderboard (per challenge or aggregate, tenant-scoped).',
        'type'         => 'read',
        'capabilities' => 'local/airpay_challenge:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],
];
