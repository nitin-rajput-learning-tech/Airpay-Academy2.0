<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Stage B rehearsal kit: read one value out of a JSON file (the baseline, an import report), so the shell scripts need
 * no jq.
 *
 *   php json_get.php FILE meta.decisions_hash        a scalar prints as text (true/false for booleans)
 *   php json_get.php FILE legacy --count             the number of elements of an array or object
 *   php json_get.php FILE counts --json              an array or object prints as JSON
 *
 * The path is dot-separated; a segment that is a digit string indexes a list. Exit: 0 printed, 3 the path is absent
 * (or null), 4 the file cannot be read as JSON.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    exit('This tool runs from the command line only.');
}

$file = $argv[1] ?? '';
$path = $argv[2] ?? '';
$flag = $argv[3] ?? '';
if ($file === '' || $path === '') {
    fwrite(STDERR, "Usage: php json_get.php FILE dotted.path [--count | --json]\n");
    exit(4);
}
$raw = @file_get_contents($file);
$data = $raw === false ? null : json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "Cannot read {$file} as JSON.\n");
    exit(4);
}
$node = $data;
foreach (explode('.', $path) as $segment) {
    if (!is_array($node) || !array_key_exists($segment, $node)) {
        exit(3);
    }
    $node = $node[$segment];
}
if ($node === null) {
    exit(3);
}
if ($flag === '--count') {
    echo is_array($node) ? count($node) : 1, "\n";
    exit(0);
}
if (is_array($node) || $flag === '--json') {
    echo json_encode($node, JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}
echo $node === true ? 'true' : ($node === false ? 'false' : (string) $node), "\n";
exit(0);
