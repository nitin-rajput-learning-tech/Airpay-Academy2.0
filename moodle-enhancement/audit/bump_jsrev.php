<?php
define('CLI_SCRIPT', true);
require_once('C:/xampp/htdocs/moodle5/public/config.php');

// Bump JS revision so all requirejs URLs get a new cachekey.
purge_other_caches(); // Pre-Moodle 5: purge_caches()
\core\session\manager::gc(); // Clear session cache

// Bump rev for JS, CSS, templates
set_config('jsrev', time());
set_config('themerev', time());
set_config('templaterev', time());

echo "JS rev bumped to: " . get_config('core', 'jsrev') . "\n";
echo "Theme rev bumped to: " . get_config('core', 'themerev') . "\n";
