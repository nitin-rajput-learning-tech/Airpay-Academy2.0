<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Capability definitions for local_sentientia_ratings.
 *
 * W1-3 (2026-05-15) — added `:rate` for the write endpoint. Every
 * authenticated user (`user` archetype) plus learners, teachers, and
 * managers can submit ratings. Guests cannot.
 */
$capabilities = [

    'local/sentientia_ratings:rate' => [
        'riskbitmask'  => 0,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
            // 'guest' intentionally omitted — anonymous users cannot rate.
        ],
    ],

];
