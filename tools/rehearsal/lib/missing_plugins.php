<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Stage B rehearsal kit: list the plugins the database knows that are missing from disk.
 *
 * After a hop with the BizLMS code off disk (migration plan 0: the decision of 2026-09-30) the BizLMS plugins must show
 * as "missing from disk" and nothing else may: their tables stay for the ADR-032 importers, and uninstalling a missing
 * plugin would drop them. This is the check the first local rehearsal ran inline (it found 50 on the April 2026 copy),
 * made into a file so the kit does not carry PHP inside shell quoting.
 *
 *   php missing_plugins.php /absolute/path/to/config.php
 *
 * Prints one component per line (sorted, blank components left out) and `total N` last. Read-only. It loads Moodle, so
 * the config.php must be the one of the code tree being checked (it can be run before the hop's upgrade).
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    exit('This tool runs from the command line only.');
}
$configpath = $argv[1] ?? '';
if ($configpath === '' || basename($configpath) !== 'config.php' || !is_readable($configpath)) {
    fwrite(STDERR, "Usage: php missing_plugins.php /absolute/path/to/config.php\n");
    exit(4);
}

define('CLI_SCRIPT', true);
require($configpath);
require_once($CFG->libdir . '/clilib.php');

$missing = [];
foreach (core_plugin_manager::instance()->get_plugins() as $plugins) {
    foreach ($plugins as $plugin) {
        if ($plugin->get_status() === core_plugin_manager::PLUGIN_STATUS_MISSING && $plugin->component !== '') {
            $missing[$plugin->component] = true;
        }
    }
}
$names = array_keys($missing);
sort($names);
foreach ($names as $name) {
    echo $name, "\n";
}
echo 'total ', count($names), "\n";
exit(0);
