<?php
// scan_missing_end_of_body.php  (ADR-027 Gate 0 — sibling of scan_mustache_comment_leaks.php)
//
// Flags full-page layout templates that never flush standard_end_of_body_html.
// This is the static net for bug-class #1 from the 2026-06-09 audit (dead AMD /
// "window.require is undefined"): Moodle flushes the RequireJS/AMD bootstrap AND
// every queued $PAGE->requires->js_call_amd() through
// core_renderer::standard_end_of_body_html(). A full-page (<body>...</body>)
// layout template that forgets to emit it renders visually but ships ZERO working
// JavaScript — charts blank, drawers / cart badge / datatables inert. This is
// exactly the regression that hit dashboard.mustache on 2026-06-09
// (PROJECT-STATE task #382). Gate 1 (render-smoke) catches it at runtime by
// asserting `window.require` is a function; this is the cheaper static net.
//
// Rule (self-calibrating, low false-positive):
//   A .mustache file that CLOSES a </body> tag IS a full-page document and MUST
//   flush the end-of-body output, satisfied by EITHER:
//     (a) the literal `standard_end_of_body_html` in the file, OR
//     (b) a {{> partial }} include whose target template itself emits it
//         (e.g. theme_sentientia/footer, theme_sentientia/shell).
//   Mustache comments ({{! ... }}) are stripped first, so a template that only
//   MENTIONS the token in a comment but relies on a partial is checked on its
//   real mechanism, not the comment text.
//   Partials (navbar, sidebar, head, cards — no </body>) are NOT checked.
//   Pure-PHP layouts (e.g. layout/frontpage.php emit $OUTPUT->standard_end_of_body_html()
//   in PHP) are out of scope — they are not .mustache files.
//
// Per-file opt-out: add the marker `end-of-body-allow` anywhere in the file for
// the rare deliberate exception (e.g. a fragment that closes a body it did not open).
//
// EXEMPT trees: theme/airpayux/** (legacy), moodle-enhancement/**, node_modules,
// vendor, .git (docs / tooling / vendored mustache must not trip the gate).
//
// Usage: php scan_missing_end_of_body.php <path> [<path> ...]
//   <path> may be a FILE (pre-commit on staged files) or a DIRECTORY (CI).
// Exit: 0 = clean, 1 = violations found, 2 = bad args.

$paths = array_slice($argv, 1);
if (!$paths) {
    fwrite(STDERR, "usage: php scan_missing_end_of_body.php <file-or-dir>...\n");
    exit(2);
}

$is_exempt = static function (string $f): bool {
    $n = str_replace('\\', '/', $f);
    return (bool) preg_match('#(^|/)theme/airpayux/#', $n)
        || strpos($n, '/moodle-enhancement/') !== false
        || strpos($n, 'moodle-enhancement/') === 0
        || strpos($n, '/node_modules/') !== false
        || strpos($n, '/vendor/') !== false
        || strpos($n, '/.git/') !== false;
};
$is_mustache = static function (string $f): bool {
    return strtolower(substr($f, -9)) === '.mustache';
};
// Strip Mustache comments so token/partial detection runs on the live template,
// not on documentation. Mustache terminates a comment at the first '}}'.
$strip_comments = static function (string $c): string {
    return (string) preg_replace('/\{\{!.*?\}\}/s', '', $c);
};

// Collect candidate files to CHECK (from args).
$files = [];
foreach ($paths as $p) {
    if (is_file($p)) {
        if ($is_mustache($p) && !$is_exempt($p)) { $files[] = $p; }
    } else if (is_dir($p)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileinfo) {
            $f = (string) $fileinfo;
            if ($is_mustache($f) && !$is_exempt($f)) { $files[] = $f; }
        }
    } else {
        fwrite(STDERR, "skip (not found): $p\n");
    }
}

// Build the set of partial BASENAMES that emit the token, per templates root.
// A staged candidate may rely on a footer/shell partial that is NOT itself staged,
// so the emitting universe must come from the whole templates tree — not $files.
$emitting_cache = [];
$emitting_for = static function (string $candidate) use (&$emitting_cache, $is_mustache, $strip_comments): array {
    $n = str_replace('\\', '/', $candidate);
    if (($pos = strrpos($n, '/templates/')) !== false) {
        $root = substr($n, 0, $pos) . '/templates';   // the nearest .../templates dir
    } else {
        $root = dirname($n);                            // fallback: the file's own dir
    }
    if (isset($emitting_cache[$root])) { return $emitting_cache[$root]; }
    $set = [];
    if (is_dir($root)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fi) {
            $ff = (string) $fi;
            if (!$is_mustache($ff)) { continue; }
            $contents = @file_get_contents($ff);
            if ($contents === false) { continue; }
            if (strpos($strip_comments($contents), 'standard_end_of_body_html') !== false) {
                $set[basename($ff, '.mustache')] = true;
            }
        }
    }
    $emitting_cache[$root] = $set;
    return $set;
};

$flagged = 0;
foreach ($files as $f) {
    $raw = file_get_contents($f);
    if ($raw === false) { continue; }
    if (strpos($raw, 'end-of-body-allow') !== false) { continue; }   // explicit opt-out

    $c = $strip_comments($raw);
    if (stripos($c, '</body>') === false) { continue; }              // not a full-page document
    if (strpos($c, 'standard_end_of_body_html') !== false) { continue; }  // (a) direct emission

    // (b) emission via an included partial that itself emits it.
    $emitting = $emitting_for($f);
    $satisfied = false;
    if (preg_match_all('/\{\{>\s*([A-Za-z0-9_\/.]+)\s*\}\}/', $c, $m)) {
        foreach ($m[1] as $partial) {
            if (isset($emitting[basename($partial)])) { $satisfied = true; break; }
        }
    }
    if ($satisfied) { continue; }

    // VIOLATION — point at the first </body> line for a useful jump target.
    $lineno = 1;
    foreach (explode("\n", $raw) as $i => $line) {
        if (stripos($line, '</body>') !== false) { $lineno = $i + 1; break; }
    }
    echo str_replace('\\', '/', $f) . ':' . $lineno
        . "  full-page template closes </body> but never flushes standard_end_of_body_html"
        . " (AMD will not boot — ADR-027 Gate 0)\n";
    $flagged++;
}
fwrite(STDERR, "FLAGGED: $flagged template(s) missing standard_end_of_body_html\n");
exit($flagged > 0 ? 1 : 0);
