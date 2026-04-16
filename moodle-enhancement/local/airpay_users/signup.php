<?php
// Airpay User Registration — redirects to BizLMS or core signup during transition.

require_once(__DIR__ . '/../../config.php');

// If BizLMS signup exists, redirect there.
if (file_exists($CFG->dirroot . '/local/users/signup.php')) {
    redirect(new moodle_url('/local/users/signup.php'));
}

// Otherwise redirect to Moodle core signup.
redirect(new moodle_url('/login/signup.php'));
