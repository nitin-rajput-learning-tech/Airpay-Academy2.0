<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hard check for a Moodle 5.3 tree (2026-10-22): Moodle's composer vendor/ is present and its libraries load.
 *
 * The 5.3 base is a source tree; Moodle 5.3 loads about 20 third-party libraries from <root>/vendor that core no longer
 * bundles in public/lib. Packages built before 2026-10-22 shipped without vendor/, and on UAT every authenticated
 * /api/rest call died on the missing League OAuth2 ScopeTrait (HTTP 500). The rehearsal's web smoke only fetched
 * anonymous files, so nothing caught it. Run from the tree root with the full config:
 *
 *   php tools/uat/check_vendor53.php            (cwd = the Moodle 5.3 tree root, config.php present)
 *
 * Prints one line per probe and exits 0 when every probe passes, 1 otherwise. Read-only.
 */

define('CLI_SCRIPT', true);
require(getcwd() . '/config.php');

global $CFG;
$fails = 0;
$probe = function (string $what, bool $ok) use (&$fails): void {
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . PHP_EOL;
    if (!$ok) {
        $fails++;
    }
};

$root = $CFG->root ?? dirname($CFG->dirroot);
$probe('vendor/autoload.php present', is_file($root . '/vendor/autoload.php'));
$probe('vendor/composer/installed.json present (the environment page reads it)', is_file($root . '/vendor/composer/installed.json'));
// The libraries 5.3 core uses at runtime and no longer bundles: one representative symbol each.
$symbols = [
    'League\OAuth2\Server\Entities\Traits\ScopeTrait' => 'trait',   // REST API scopes (every authenticated /api/rest call)
    'Aws\Sdk' => 'class',                                             // AWS SDK (S3, AI providers)
    'ZipStream\ZipStream' => 'class',                                 // zip downloads
    'Michelf\Markdown' => 'class',                                    // the Markdown text format
    'Symfony\Component\Console\Application' => 'class',               // the 5.3 CLI
];
foreach ($symbols as $name => $kind) {
    $exists = $kind === 'trait' ? trait_exists($name) : class_exists($name);
    $probe("{$kind} {$name} loads", $exists);
}
$probe('no dev packages (PHPUnit absent from vendor/)', !is_dir($root . '/vendor/phpunit'));

exit($fails === 0 ? 0 : 1);
