<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
// W1-9 (2026-05-15) — emit program_completed event for SOX audit + W1-5
// evaluation trigger flow. Registers db/events.php (course_completed
// observer) + db/caches.php (program_complete_dedupe).
$plugin->version   = 2026051500;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0';  // W1-9: program_completed event wired
$plugin->dependencies = ['local_airpay_org' => 2026041600];
