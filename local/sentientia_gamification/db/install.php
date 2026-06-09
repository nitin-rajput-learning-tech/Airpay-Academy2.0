<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_gamification_install() {
    // Seed default badges.
    require_once(__DIR__ . '/../lib.php');
    local_sentientia_gamification_seed_badges();
}
