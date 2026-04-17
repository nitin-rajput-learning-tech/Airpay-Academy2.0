<?php
// Redirect: /my/dashboard.php no longer exists in Moodle 5.1.
// Dashboard is now at /my/index.php.
require_once(__DIR__ . '/../config.php');
redirect(new moodle_url('/my/'));
