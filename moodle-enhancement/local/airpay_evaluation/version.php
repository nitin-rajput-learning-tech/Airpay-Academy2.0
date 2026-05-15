<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_evaluation';
// W1-5 + W1-9 (2026-05-15) — observer + trigger queue + scheduled task.
// W1-9 update: events.php now listens for `classroom_completed` (renamed
// from `session_completed`) — bump version to force Moodle to re-read.
$plugin->version   = 2026051501;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.1';  // W1-5 + W1-9: event name aligned
