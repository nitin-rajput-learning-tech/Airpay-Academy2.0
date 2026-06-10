<?php
// scan_stale_theme_refs.php  (ADR-027 Gate 0 — sibling of scan_mustache_comment_leaks.php)
//
// Flags stale `theme_airpayux` references in RUNTIME code after the
// theme_airpayux -> theme_sentientia de-brand. This catches bug-class #2 from
// the 2026-06-09 audit: an AMD module published under a stale define name
// (e.g. require(['theme_airpayux/foo'])) silently NO-OPs — `window.require`
// still exists, so the render-smoke gate (Gate 1) cannot see it. A static
// pre-commit/CI check is the right net for this class.
//
// What is flagged: the literal `theme_airpayux` appearing in a scanned file.
// What is EXEMPT (not flagged):
//   - the legacy theme dir itself  (theme/airpayux/** — allowed to self-name)
//   - tooling/docs                 (moodle-enhancement/**, .git, node_modules,
//                                    vendor) — these legitimately mention the
//                                    old name in checkers/changelogs.
// Scanned file types: .php .js .mustache .scss .json (where AMD/component refs live).
//
// Usage: php scan_stale_theme_refs.php <path> [<path> ...]
//   <path> may be a FILE (pre-commit hook on staged files) or a DIRECTORY (CI).
// Exit: 0 = clean, 1 = stale refs found, 2 = bad args.

$paths = array_slice($argv, 1);
if (!$paths) {
    fwrite(STDERR, "usage: php scan_stale_theme_refs.php <file-or-dir>...\n");
    exit(2);
}

$exts = ['php', 'js', 'mustache', 'scss', 'json'];

// Path is exempt if it lives in the legacy theme dir or in tooling/docs/vendored trees.
$is_exempt = static function (string $f): bool {
    $n = str_replace('\\', '/', $f);
    return (bool) preg_match('#(^|/)theme/airpayux/#', $n)
        || strpos($n, '/moodle-enhancement/') !== false
        || strpos($n, 'moodle-enhancement/') === 0
        || strpos($n, '/node_modules/') !== false
        || strpos($n, '/vendor/') !== false
        || strpos($n, '/.git/') !== false;
};
$has_ext = static function (string $f) use ($exts): bool {
    $dot = strrpos($f, '.');
    return $dot !== false && in_array(strtolower(substr($f, $dot + 1)), $exts, true);
};

$files = [];
foreach ($paths as $p) {
    if (is_file($p)) {
        if ($has_ext($p) && !$is_exempt($p)) { $files[] = $p; }
    } else if (is_dir($p)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileinfo) {
            $f = (string) $fileinfo;
            if ($has_ext($f) && !$is_exempt($f)) { $files[] = $f; }
        }
    } else {
        fwrite(STDERR, "skip (not found): $p\n");
    }
}

$flagged = 0;
foreach ($files as $f) {
    $c = file_get_contents($f);
    if (strpos($c, 'theme_airpayux') === false) { continue; }
    foreach (explode("\n", $c) as $i => $line) {
        // Only QUOTED refs are real (AMD module names / component strings):
        //   require(['theme_airpayux/x']) · define('theme_airpayux/x') · 'theme_airpayux'
        // Bare mentions in comments/docs are not stale dependencies — ignore them.
        if (preg_match('/[\'"]theme_airpayux/', $line)) {
            // Allow an explicit per-line opt-out for the rare intentional reference.
            if (strpos($line, 'stale-theme-ref-allow') !== false) { continue; }
            $snippet = preg_replace('/\s+/', ' ', trim($line));
            echo str_replace('\\', '/', $f) . ':' . ($i + 1) . '  ' . substr($snippet, 0, 90) . "\n";
            $flagged++;
        }
    }
}
fwrite(STDERR, "FLAGGED: $flagged stale theme_airpayux reference(s)\n");
exit($flagged > 0 ? 1 : 0);
