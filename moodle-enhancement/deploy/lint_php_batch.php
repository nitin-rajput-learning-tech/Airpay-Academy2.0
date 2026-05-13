<?php
/**
 * Batch PHP syntax linter.
 *
 * Reads a list of PHP file paths (one per line) on STDIN and reports
 * which ones have syntax errors. Runs as a SINGLE PHP process —
 * much faster than spawning `php -l` per file on Windows, where
 * process creation can cost 100-500ms each (so 729 files × 200ms
 * = 2.5 minutes just paying the fork-tax, before any actual work).
 *
 * Uses token_get_all() with TOKEN_PARSE which performs the full
 * parser pass (i.e. exactly what `php -l` does) but stays inside
 * the host process.
 *
 * Output:
 *   FAIL: <abs_path> :: <error message>
 *
 * Exit code:
 *   0  if every file parses cleanly
 *   1  if any file fails
 *   2  if invoked incorrectly (no stdin / bad arg)
 *
 * Usage:
 *   find ... | php moodle-enhancement/deploy/lint_php_batch.php
 *
 * Owner: Head of L&D, Airpay Academy CI gate.
 */

declare(strict_types=1);

/**
 * Translate MSYS-style paths to Windows paths.
 *
 * git-bash on Windows emits `/d/Claude Local/...` from find, but
 * native PHP needs `D:/Claude Local/...`. The mapping is:
 *   /<letter>/<rest>   →   <LETTER>:/<rest>
 *
 * On non-Windows hosts this is a no-op (we leave the path as-is).
 */
function airpay_translate_path(string $p): string {
    if (DIRECTORY_SEPARATOR !== '\\') {
        return $p;  // Linux / macOS: paths already correct
    }
    if (preg_match('#^/([a-zA-Z])/(.*)$#', $p, $m)) {
        return strtoupper($m[1]) . ':/' . $m[2];
    }
    return $p;
}

// Read file list from stdin (one path per line). The find pipeline
// in pre_deploy_validate.sh feeds us here.
$lines = [];
while (($line = fgets(STDIN)) !== false) {
    $trimmed = trim($line);
    if ($trimmed === '' || $trimmed[0] === '#') {
        continue;
    }
    $lines[] = airpay_translate_path($trimmed);
}

if (empty($lines)) {
    fwrite(STDERR, "lint_php_batch.php: no file paths received on stdin\n");
    exit(2);
}

$total    = count($lines);
$failed   = 0;
$start_ms = (int) (microtime(true) * 1000);

foreach ($lines as $path) {
    if (!is_file($path)) {
        echo "FAIL: $path :: file not found\n";
        $failed++;
        continue;
    }
    $code = @file_get_contents($path);
    if ($code === false) {
        echo "FAIL: $path :: unreadable\n";
        $failed++;
        continue;
    }
    try {
        // TOKEN_PARSE runs the parser and throws ParseError on bad
        // syntax — equivalent to what `php -l` does, minus process
        // spawn.
        token_get_all($code, TOKEN_PARSE);
    } catch (\ParseError $e) {
        $msg = $e->getMessage() . ' on line ' . $e->getLine();
        echo "FAIL: $path :: $msg\n";
        $failed++;
    } catch (\Error $e) {
        // Compile-time errors that PHP raises before reaching
        // userland — also surface them.
        echo "FAIL: $path :: " . $e->getMessage() . "\n";
        $failed++;
    }
}

$elapsed_ms = (int) (microtime(true) * 1000) - $start_ms;
$elapsed_s  = number_format($elapsed_ms / 1000, 2);

fwrite(STDERR, sprintf(
    "lint_php_batch: scanned %d files in %ss (%d failed)\n",
    $total, $elapsed_s, $failed
));

exit($failed > 0 ? 1 : 0);
