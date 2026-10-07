<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Stage B rehearsal kit: read the literal settings of a Moodle config.php WITHOUT running it.
 *
 * The kit has to know which database, host and wwwroot a config.php points at before any Moodle code is loaded (a
 * wrong config must be refused, not executed), and a restored 4.1.2 database cannot be opened through Moodle 4.5 or
 * 5.x before the upgrade. The reader is the same `config` class cli/source_baseline.php uses to read config.php as
 * data: it understands `$CFG->name = <literal>;` (strings, numbers, booleans, arrays, __DIR__, getenv('X')) and
 * ignores everything else (the guard code, require_once).
 *
 *   php config_probe.php FILE --json          every literal setting, as JSON
 *   php config_probe.php FILE --get=dbname    one setting (true/false/text; arrays as JSON); exit 3 when absent
 *   php config_probe.php FILE --strings       every string value, one per line, arrays flattened (for host scans)
 *
 * The environment variable SOURCE_BASELINE_PHP must name cli/source_baseline.php (the kit exports it).
 * Exit: 0 done, 3 setting absent or not a literal, 4 cannot read.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    exit('This tool runs from the command line only.');
}

$file = $argv[1] ?? '';
$mode = $argv[2] ?? '--json';
if ($file === '' || $file[0] === '-') {
    fwrite(STDERR, "Usage: php config_probe.php FILE [--json | --get=NAME | --strings]\n");
    exit(4);
}
$lib = getenv('SOURCE_BASELINE_PHP');
if ($lib === false || $lib === '' || !is_file($lib)) {
    fwrite(STDERR, "SOURCE_BASELINE_PHP is not set to cli/source_baseline.php.\n");
    exit(4);
}

define('SENTIENTIA_PARITY_LIBRARY_ONLY', true);
require $lib;

try {
    $settings = \local_sentientia_platform\parity\config::settings($file);
} catch (\Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(4);
}

/**
 * Text of one value for the shell: booleans as true/false, arrays as JSON.
 *
 * @param mixed $value
 * @return string
 */
function config_probe_text($value): string {
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if ($value === null) {
        return '';
    }
    if (is_array($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES);
    }
    return (string) $value;
}

/**
 * Every string leaf of a value.
 *
 * @param mixed $value
 * @param string[] $out
 * @return void
 */
function config_probe_strings($value, array &$out): void {
    if (is_array($value)) {
        foreach ($value as $item) {
            config_probe_strings($item, $out);
        }
    } else if (is_string($value) && $value !== '') {
        $out[] = $value;
    }
}

if ($mode === '--json') {
    echo json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
if (strpos($mode, '--get=') === 0) {
    $name = substr($mode, 6);
    if (!array_key_exists($name, $settings)) {
        exit(3);
    }
    echo config_probe_text($settings[$name]), "\n";
    exit(0);
}
if ($mode === '--strings') {
    $strings = [];
    config_probe_strings($settings, $strings);
    foreach ($strings as $line) {
        echo str_replace(["\r", "\n"], ' ', $line), "\n";
    }
    exit(0);
}
fwrite(STDERR, "Unknown mode {$mode}.\n");
exit(4);
