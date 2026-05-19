<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_evaluation';
// W1-5 + W1-9 + P1 #17 (2026-05-16) — observer + trigger queue +
// scheduled task + time-bounded availability window + pulse-mode
// (multiple_submit) for repeat-survey workflows.
$plugin->version   = 2026051901;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.0';  // +P1 #17 window + pulse mode
