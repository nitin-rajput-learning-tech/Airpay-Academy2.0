<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Web service definitions for local_airpay_ratings.
 *
 * Registered on plugin upgrade — version bump in version.php triggers Moodle
 * to re-scan this file. After upgrade, services appear under
 * Site Administration → Plugins → Web services → External services.
 */
$functions = [

    'local_airpay_ratings_submit_rating' => [
        'classname'    => '\\local_airpay_ratings\\external\\submit_rating',
        'description'  => 'Submit (insert or update) a star rating for an item.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/airpay_ratings:rate',
        'loginrequired' => true,
    ],

];
