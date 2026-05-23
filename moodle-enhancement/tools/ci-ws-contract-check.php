<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Standalone WS-contract gate for CI — no Moodle bootstrap required.
 *
 * Background — ADR-009 + Bug #6 / #10 / #12 (2026-05-22 audit):
 * The shared theme_airpayux/datatable AMD client POSTs the contract
 * {search, sort, sortdir, page, perpage, filters} to every WS it
 * consumes. Moodle's strict external_function_parameters validator
 * rejects unknown keys, so every consumer must declare all 6 with
 * VALUE_DEFAULT.
 *
 * Why standalone? The Moodle-aware version of this audit lives at
 * `theme/airpayux/classes/ws_contract_scanner.php` and runs via:
 *   - theme/airpayux/tests/ws_contract_test.php (PHPUnit gate)
 *   - theme/airpayux/cli/ws_contract_audit.php  (CLI smoke)
 * Both require a fully bootstrapped Moodle (config.php + autoloader)
 * because they call `class_exists()` on the WS classname and reflect
 * its execute_parameters() return shape.
 *
 * In GitHub Actions we don't have a Moodle install. Spinning one up
 * for every PR costs ~5-15 min of init time — the existing ci.yml
 * explicitly opts out for that reason.
 *
 * This script does the same audit by STATIC ANALYSIS of the repo:
 *   1. Walk `moodle-enhancement/local/airpay_*` (or `local/airpay_*`)
 *      mustache files for `data-region="airpay-datatable"` +
 *      `data-ws-name="<name>"` pairs.
 *   2. Look up the WS name in `local/airpay_X/db/services.php` (for
 *      each X plugin) to find its classname (parsed by `include`).
 *   3. Open the corresponding external/<classname>.php file under
 *      the plugin's classes/ folder.
 *   4. Regex-search for each REQUIRED_CONTRACT_KEYS inside
 *      execute_parameters() body. Missing keys = failure.
 *
 * No DB. No Moodle. Pure PHP 8.2. Runs in <2s on a clean ubuntu
 * runner.
 *
 * Usage:
 *   php moodle-enhancement/tools/ci-ws-contract-check.php
 *   php moodle-enhancement/tools/ci-ws-contract-check.php --json
 *
 * Exit codes (matches theme/airpayux/cli/ws_contract_audit.php):
 *   0   All contracts satisfied
 *   1   One or more failures
 *   2   Zero consumers found (config error)
 *
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

// CLI guard — refuse to run via HTTP just in case anyone web-serves
// the moodle-enhancement/ folder.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(3);
}

// Define MOODLE_INTERNAL so we can include() Moodle plugin
// db/services.php files (every one starts with
// `defined('MOODLE_INTERNAL') || die()`). We're not running Moodle —
// we're just reading the $functions array — so a stub constant is
// sufficient.
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}

// ── Configuration ──────────────────────────────────────────────────
const REQUIRED_CONTRACT_KEYS = [
    'search',
    'sort',
    'sortdir',
    'page',
    'perpage',
    'filters',
];

// Search paths — relative to repo root. The CI checks out the whole
// repo so both layouts exist; we walk whichever has files.
$jsonOutput = in_array('--json', $argv, true);
$repoRoot = realpath(__DIR__ . '/../../');
if ($repoRoot === false) {
    fwrite(STDERR, "Cannot resolve repo root\n");
    exit(3);
}

// ── 1. Find every datatable consumer ──────────────────────────────
$consumers = scan_consumers($repoRoot);

if (empty($consumers)) {
    if ($jsonOutput) {
        echo json_encode([
            'ok' => false,
            'consumers' => [],
            'failures' => [],
            'reason' => 'zero_consumers',
        ], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "WARNING: zero airpay-datatable consumers found.\n";
        echo "Either the scanner regex broke or no plugins use the\n";
        echo "shared datatable any more.\n";
    }
    exit(2);
}

// ── 2. Build wsname → classfile map from services.php files ───────
$classmap = build_classmap($repoRoot);

// ── 3. For each consumer, check its WS class for the 6 keys ───────
$failures = [];
$skipped = [];
foreach ($consumers as $wsname => $sources) {
    if (!isset($classmap[$wsname])) {
        $skipped[$wsname] = 'no_services_entry';
        continue;
    }
    $classfile = $classmap[$wsname]['file'];
    if (!is_readable($classfile)) {
        $skipped[$wsname] = 'class_file_missing:' . basename($classfile);
        continue;
    }
    $missing = missing_keys_in_class_file($classfile);
    if ($missing === null) {
        $skipped[$wsname] = 'no_execute_parameters_method';
        continue;
    }
    if (!empty($missing)) {
        $failures[$wsname] = $missing;
    }
}

// ── 4. Report + exit ──────────────────────────────────────────────
$result = [
    'ok' => empty($failures),
    'consumers' => $consumers,
    'failures' => $failures,
    'skipped' => $skipped,
];

if ($jsonOutput) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($result['ok'] ? 0 : 1);
}

// Human-readable output.
echo "\n=== ws_contract CI gate (standalone static analysis) ===\n\n";
echo sprintf("Consumers found:  %d\n", count($consumers));
echo sprintf("Failures:         %d\n", count($failures));
echo sprintf("Skipped:          %d\n", count($skipped));
echo "\n";

if (!empty($failures)) {
    echo "FAILURES:\n\n";
    foreach ($failures as $wsname => $missing) {
        echo "  {$wsname}\n";
        echo "    missing contract keys: " . implode(', ', $missing) . "\n";
        if (isset($consumers[$wsname])) {
            foreach ($consumers[$wsname] as $src) {
                echo "    used by: {$src}\n";
            }
        }
        if (isset($classmap[$wsname])) {
            $rel = str_replace($repoRoot . DIRECTORY_SEPARATOR, '',
                $classmap[$wsname]['file']);
            $rel = str_replace('\\', '/', $rel);
            echo "    class file: {$rel}\n";
        }
        echo "\n";
    }
    echo "Fix pattern: see local/airpay_request/classes/external/list_mine.php\n";
    echo "for the canonical execute_parameters() declaring all 6 keys with\n";
    echo "VALUE_DEFAULT.\n\n";
    exit(1);
}

if (!empty($skipped)) {
    echo "SKIPPED (informational — not failures):\n";
    foreach ($skipped as $wsname => $reason) {
        echo "  {$wsname}  ({$reason})\n";
    }
    echo "\n";
}

echo "RESULT: ALL PASS\n\n";
exit(0);

// ──────────────────────────────────────────────────────────────────
// Helper functions
// ──────────────────────────────────────────────────────────────────

/**
 * Walk every airpay_* plugin's mustache files for data-region pairs.
 *
 * @return array<string, list<string>>  wsname => [source file rel paths]
 */
function scan_consumers(string $repoRoot): array {
    $consumers = [];
    foreach (find_plugin_dirs($repoRoot) as $plugindir) {
        foreach (find_files($plugindir, '.mustache') as $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            // Find every data-region="airpay-datatable" occurrence.
            if (!preg_match_all(
                    '/data-region\s*=\s*"airpay-datatable"/i',
                    $content, $hits, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($hits[0] as $hit) {
                $offset = $hit[1];
                // Look in 400-char window around the match for data-ws-name.
                $window = substr($content, max(0, $offset - 400), 800);
                if (preg_match(
                        '/data-ws-name\s*=\s*"([a-z0-9_]+)"/i',
                        $window, $wsm)) {
                    $wsname = $wsm[1];
                    $rel = str_replace(
                        $repoRoot . DIRECTORY_SEPARATOR, '', $path);
                    $rel = str_replace('\\', '/', $rel);
                    $consumers[$wsname][] = $rel;
                    $consumers[$wsname] = array_values(
                        array_unique($consumers[$wsname]));
                }
            }
        }
    }
    ksort($consumers);
    return $consumers;
}

/**
 * Build wsname => ['classname' => X, 'file' => absolute path].
 *
 * Loads every plugin's db/services.php in an isolated scope and reads
 * its $functions array. Resolves the WS classname to its file by
 * convention: plugin/classes/external/basename.php.
 */
function build_classmap(string $repoRoot): array {
    $classmap = [];
    foreach (find_plugin_dirs($repoRoot) as $plugindir) {
        $servicesfile = $plugindir . '/db/services.php';
        if (!is_readable($servicesfile)) {
            continue;
        }
        $functions = load_services_file($servicesfile);
        foreach ($functions as $wsname => $info) {
            if (empty($info['classname'])) {
                continue;
            }
            $classname = $info['classname'];
            // Resolve classname → file path. Moodle convention is
            // <plugin>/classes/<rest_of_namespace>.php where the
            // namespace is the plugin name. For airpay plugins the
            // class is typically `local_airpay_X\external\<name>`
            // which lives at `local/airpay_X/classes/external/<name>.php`.
            $parts = explode('\\', $classname);
            if (count($parts) < 2) {
                continue;
            }
            // First segment = plugin (`local_airpay_X`) — strip and walk.
            $pluginname = array_shift($parts);
            // Verify plugin name matches the directory we're in.
            $plugindirbase = basename($plugindir);
            if ($pluginname !== 'local_' . $plugindirbase
                    && $pluginname !== $plugindirbase) {
                // Class lives in a different plugin (rare); fall back
                // to a fuzzy search across all plugin dirs.
                continue;
            }
            $classfile = $plugindir . '/classes/'
                . implode('/', $parts) . '.php';
            $classmap[$wsname] = [
                'classname' => $classname,
                'file' => $classfile,
            ];
        }
    }
    return $classmap;
}

/**
 * Static-analysis check: does the class file declare all
 * REQUIRED_CONTRACT_KEYS inside execute_parameters()?
 *
 * Returns:
 *   null              → execute_parameters() not found in file
 *   []                → all required keys present
 *   [missing keys]    → specific keys missing
 */
function missing_keys_in_class_file(string $file): ?array {
    $content = file_get_contents($file);
    if ($content === false) {
        return null;
    }
    // Find the execute_parameters() method body. Method name is
    // conventionally execute_parameters; PHP signature can wrap.
    if (!preg_match(
            '/function\s+execute_parameters\s*\(\s*\)\s*:?\s*[^{]*\{/i',
            $content, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $start = $m[0][1] + strlen($m[0][0]);
    // Find the matching closing brace by counting braces.
    $depth = 1;
    $i = $start;
    while ($i < strlen($content) && $depth > 0) {
        $ch = $content[$i];
        if ($ch === '{') {
            $depth++;
        } else if ($ch === '}') {
            $depth--;
        }
        $i++;
    }
    $body = substr($content, $start, $i - $start);

    // Each required key must appear as a quoted string key in the
    // array. Pattern: 'search' => or "search" =>.
    $missing = [];
    foreach (REQUIRED_CONTRACT_KEYS as $key) {
        $pattern = "/['\"]" . preg_quote($key, '/') . "['\"]\s*=>/";
        if (!preg_match($pattern, $body)) {
            $missing[] = $key;
        }
    }
    return $missing;
}

/**
 * Load a Moodle plugin db/services.php in an isolated scope.
 */
function load_services_file(string $path): array {
    $functions = [];
    include $path;
    return is_array($functions) ? $functions : [];
}

/**
 * Find every `airpay_X` plugin directory (handles both layouts:
 * `moodle-enhancement/local/airpay_X` and `local/airpay_X`).
 *
 * @return list<string>
 */
function find_plugin_dirs(string $repoRoot): array {
    $dirs = [];
    foreach (['moodle-enhancement/local', 'local'] as $base) {
        $localdir = $repoRoot . '/' . $base;
        if (!is_dir($localdir)) {
            continue;
        }
        foreach (scandir($localdir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!str_starts_with($entry, 'airpay_')) {
                continue;
            }
            $fullpath = $localdir . '/' . $entry;
            if (is_dir($fullpath)) {
                $dirs[] = $fullpath;
            }
        }
    }
    return $dirs;
}

/**
 * Find every file with given suffix under $dir, recursive.
 *
 * @return list<string>
 */
function find_files(string $dir, string $suffix): array {
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    $iter = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator(
            $dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        if ($file->isFile()
                && str_ends_with($file->getFilename(), $suffix)) {
            $out[] = $file->getPathname();
        }
    }
    return $out;
}
