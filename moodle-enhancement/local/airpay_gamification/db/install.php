<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_airpay_gamification_install() {
    // Seed default badges.
    require_once(__DIR__ . '/../lib.php');
    local_airpay_gamification_seed_badges();
}
