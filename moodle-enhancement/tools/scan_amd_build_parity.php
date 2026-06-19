<?php
// scan_amd_build_parity.php  (sibling of scan_missing_end_of_body.php /
//                            scan_mustache_comment_leaks.php — same dual-mode
//                            CI + pre-commit detector contract)
//
// Moodle serves the MINIFIED amd/build/<name>.min.js in PRODUCTION mode (the
// default on any real install — debug-mode is what serves amd/src/). A module
// that lives in amd/src/<name>.js with NO matching amd/build/<name>.min.js
// therefore 404s at runtime, and whatever require()s it dies silently. That is
// exactly how local_sentientia_courses/enrolledusers shipped broken — the
// enrol-user modal on enrolledusers.php never opened — surfaced by the
// 2026-06-19 Moodle 5.2 compat audit, which flagged it as a RECURRING gap
// (devs edit amd/src/ and forget to run the grunt AMD build).
//
// This is the static gate: every amd/src/**/*.js MUST have a built
// amd/build/**/*.min.js. Produce one with the Moodle grunt toolchain, e.g.:
//   cd <moodle-dirroot>
//   npx grunt amd --root=public/local/<plugin>          # whole plugin, or
//   npx grunt amd --files=public/local/<plugin>/amd/src/<name>.js   # one file
// (on Windows --files lets you scope without cd-ing into a subdir).
//
// Mapping (subdirectories preserved):
//   <...>/amd/src/<rel>.js  ->  <...>/amd/build/<rel>.min.js
//
// PRE-EXISTING DEBT is grandfathered via amd-build-parity-allowlist.txt (one
// repo-relative src path per line; '#' comments + blank lines ignored). That
// list is a DRAIN, not a dumping ground: build the file, then delete its line.
// Do NOT add NEW src files to it — that defeats the gate.
//
// Per-file opt-out (rare — a src module deliberately never built, e.g. a
// hand-authored shim): add the marker `amd-build-parity-allow` anywhere in the
// src .js file. Comment WHY on the same line.
//
// EXEMPT trees: node_modules, vendor, .git (vendored / generated JS).
//
// Usage: php scan_amd_build_parity.php <path> [<path> ...]
//   <path> may be a FILE (pre-commit on staged files) or a DIRECTORY (CI).
// Exit: 0 = clean, 1 = violations found, 2 = bad args.

$paths = array_slice($argv, 1);
if (!$paths) {
    fwrite(STDERR, "usage: php scan_amd_build_parity.php <file-or-dir>...\n");
    exit(2);
}

$norm = static function (string $f): string {
    $n = str_replace('\\', '/', $f);
    return preg_match('#^\./#', $n) ? substr($n, 2) : $n;
};
$is_exempt = static function (string $n): bool {
    return strpos($n, '/node_modules/') !== false
        || strpos($n, '/vendor/') !== false
        || strpos($n, '/.git/') !== false;
};
// An amd source file: lives under .../amd/src/ and is a plain .js (never .min.js).
$is_amd_src = static function (string $n): bool {
    if (substr($n, -3) !== '.js' || substr($n, -7) === '.min.js') { return false; }
    return strpos($n, '/amd/src/') !== false || strpos($n, 'amd/src/') === 0;
};

// Load the grandfather allowlist (sibling file). Normalised, blank/# stripped.
$allow = [];
$allowfile = __DIR__ . '/amd-build-parity-allowlist.txt';
if (is_file($allowfile)) {
    foreach (file($allowfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { continue; }
        $allow[$norm($line)] = true;
    }
}
$is_allowed = static function (string $n) use ($allow): bool {
    if (isset($allow[$n])) { return true; }
    // Safety net for absolute paths: match an allowlist entry as a path suffix.
    foreach ($allow as $entry => $_) {
        if (substr($n, -(strlen($entry) + 1)) === '/' . $entry) { return true; }
    }
    return false;
};

// Collect candidate src files to CHECK (from args).
$files = [];
foreach ($paths as $p) {
    if (is_file($p)) {
        $n = $norm($p);
        if ($is_amd_src($n) && !$is_exempt($n)) { $files[$n] = $p; }
    } else if (is_dir($p)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileinfo) {
            $f = (string) $fileinfo;
            $n = $norm($f);
            if ($is_amd_src($n) && !$is_exempt($n)) { $files[$n] = $f; }
        }
    } else {
        fwrite(STDERR, "skip (not found): $p\n");
    }
}

$flagged = 0;
foreach ($files as $n => $f) {
    if ($is_allowed($n)) { continue; }                                  // grandfathered debt
    $raw = @file_get_contents($f);
    if ($raw !== false && strpos($raw, 'amd-build-parity-allow') !== false) {
        continue;                                                       // explicit opt-out
    }

    // Map src -> expected build path (subdirectories preserved).
    $build = preg_replace('#/amd/src/#', '/amd/build/', str_replace('\\', '/', $f), 1);
    $build = preg_replace('#\.js$#', '.min.js', $build);

    if (is_file($build)) { continue; }                                  // built — OK

    echo $n . ':0  amd/src module has no built '
        . preg_replace('#\.js$#', '.min.js', preg_replace('#/amd/src/#', '/amd/build/', $n, 1))
        . " — run `npx grunt amd` for this plugin (PRODUCTION mode 404s without it)\n";
    $flagged++;
}
fwrite(STDERR, "FLAGGED: $flagged amd/src module(s) missing a built amd/build/*.min.js\n");
exit($flagged > 0 ? 1 : 0);
