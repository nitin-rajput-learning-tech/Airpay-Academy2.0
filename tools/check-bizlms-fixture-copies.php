<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * The BizLMS import's owner-signed files must equal the copies the PHPUnit tests read.
 *
 * WHY THIS EXISTS
 * ---------------
 * ADR-032's two owner-signed files live in moodle-enhancement/docs/cutover/:
 *
 *     bizlms-import-decisions.json       the decisions Nitin signed (no count here: it goes stale, and the file has grown)
 *     bizlms-capability-allowlist.json   the capability review's allow-list
 *
 * The plugin is deployed to a Moodle tree on its own, where docs/ does not exist, so a PHPUnit test that reads
 * the file from docs/ silently skips exactly where PHPUnit runs. The tests therefore read a copy under
 * tests/fixtures/bizlms/ in both plugin trees. A copy is only worth reading if it is the file: this gate fails
 * when a copy differs from the signed file (line endings ignored), so the test cannot pass against a file the
 * owner no longer signed.
 *
 * USAGE
 * -----
 *   php tools/check-bizlms-fixture-copies.php
 *
 * To fix a failure, copy the signed file over the fixture in BOTH trees. Never edit a copy.
 */

$root = dirname(__DIR__);
$files = [
    'bizlms-import-decisions.json' => 'bizlms-import-decisions.copy.json',
    'bizlms-capability-allowlist.json' => 'bizlms-capability-allowlist.copy.json',
];
$trees = ['moodle-enhancement/local', 'local'];

$read = static function (string $path): ?string {
    return is_readable($path) ? str_replace("\r\n", "\n", (string) file_get_contents($path)) : null;
};

$failures = 0;
foreach ($files as $signed => $copy) {
    $source = $read($root . '/moodle-enhancement/docs/cutover/' . $signed);
    if ($source === null) {
        fwrite(STDERR, "FAIL moodle-enhancement/docs/cutover/{$signed} is missing\n");
        $failures++;
        continue;
    }
    foreach ($trees as $tree) {
        $relative = "{$tree}/sentientia_platform/tests/fixtures/bizlms/{$copy}";
        $body = $read($root . '/' . $relative);
        if ($body === null) {
            fwrite(STDERR, "FAIL {$relative} is missing\n");
            $failures++;
        } else if ($body !== $source) {
            fwrite(STDERR, "FAIL {$relative} differs from docs/cutover/{$signed}\n");
            $failures++;
        }
    }
}
if ($failures > 0) {
    fwrite(STDERR, "check-bizlms-fixture-copies: {$failures} failure(s). Copy the signed file over the fixture in both trees.\n");
    exit(1);
}
echo "check-bizlms-fixture-copies: OK\n";
exit(0);
