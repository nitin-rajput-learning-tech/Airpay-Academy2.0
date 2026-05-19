<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_evaluation';
// W1-5 + W1-9 + P1 #17 + P1 #18 (2026-05-16) — observer + trigger
// queue + scheduled task + availability window + pulse mode + numeric
// + multi-select multichoice question types.
$plugin->version   = 2026051902;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.9.0';  // +P1 #18 question types
