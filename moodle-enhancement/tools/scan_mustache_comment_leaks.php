<?php
// scan_mustache_comment_leaks.php
//
// Finds Mustache {{! ... }} comments that LEAK literal text onto the page,
// because their body contains a `}}` (or an embedded `{{`) — Mustache closes a
// comment at the FIRST `}}` after `{{!`, so anything the author intended to be
// inside the comment after that point renders as page text.
//
// Detection (two leak signatures): for each `{{!`,
//   (a) the comment body up to its first `}}` already contains an embedded
//       `{{` (a tag/example the author put inside — its `}}` closes early); OR
//   (b) the tail between that first `}}` and the next `{{` opener still
//       contains another `}}` (the real close came late — the middle leaked).
//
// Usage: php scan_mustache_comment_leaks.php <path> [<path> ...]
//   <path> may be a .mustache FILE (scanned directly — used by the pre-commit
//   hook on staged files) or a DIRECTORY (recursed — used by CI).
// Exit: 0 = clean, 1 = leaks found, 2 = bad args.

$paths = array_slice($argv, 1);
if (!$paths) {
    fwrite(STDERR, "usage: php scan_mustache_comment_leaks.php <file-or-dir>...\n");
    exit(2);
}

// Resolve every arg (file or dir) into a flat list of .mustache files.
$files = [];
foreach ($paths as $p) {
    if (is_file($p)) {
        if (substr($p, -9) === '.mustache') { $files[] = $p; }
    } else if (is_dir($p)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($p, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileinfo) {
            $f = (string) $fileinfo;
            if (substr($f, -9) === '.mustache') { $files[] = $f; }
        }
    } else {
        fwrite(STDERR, "skip (not found): $p\n");
    }
}

$flagged = 0;
foreach ($files as $f) {
    $c = file_get_contents($f);
    $off = 0;
    while (($p = strpos($c, '{{!', $off)) !== false) {
        $close = strpos($c, '}}', $p);
        if ($close === false) { break; }
        $afterclose = $close + 2;
        $nextopen = strpos($c, '{{', $afterclose);
        $tail = ($nextopen === false)
            ? substr($c, $afterclose)
            : substr($c, $afterclose, $nextopen - $afterclose);
        $body = substr($c, $p + 3, $close - ($p + 3));
        if (strpos($body, '{{') !== false || strpos($tail, '}}') !== false) {
            $line = substr_count(substr($c, 0, $p), "\n") + 1;
            $snippet = preg_replace('/\s+/', ' ', trim(substr($c, $p, 70)));
            echo str_replace('\\', '/', $f) . ":$line  " . $snippet . "...\n";
            $flagged++;
        }
        $off = $close + 2;
    }
}
fwrite(STDERR, "FLAGGED: $flagged leaking comment(s)\n");
exit($flagged > 0 ? 1 : 0);
