<?php
// scan_mustache_comment_leaks.php
//
// Finds Mustache {{! ... }} comments that LEAK literal text onto the page,
// because their body contains a `}}` (or a `{{ }}` pair) — Mustache closes a
// comment at the FIRST `}}` after `{{!`, so anything the author intended to be
// inside the comment after that point renders as page text.
//
// Detection: for each `{{!`, take the text between its first following `}}`
// and the NEXT `{{` opener. If that tail contains another `}}`, the comment's
// real close was premature -> the middle leaked. Flags file:line + a snippet.
//
// Usage: php scan_mustache_comment_leaks.php <dir> [<dir> ...]
// Exit: 0 = clean, 1 = leaks found, 2 = bad args.

$dirs = array_slice($argv, 1);
if (!$dirs) { fwrite(STDERR, "usage: php scan_mustache_comment_leaks.php <dir>...\n"); exit(2); }

$flagged = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) { fwrite(STDERR, "skip (not a dir): $dir\n"); continue; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileinfo) {
        $f = (string) $fileinfo;
        if (substr($f, -9) !== '.mustache') { continue; }
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
            // Two leak signatures: (a) the comment body up to its first close
            // already contains an embedded `{{` (a tag/example the author put
            // inside — Mustache will treat the next `}}` as the close, leaking
            // the rest); (b) the tail after the first close still contains a
            // `}}` (the real close came late — the middle leaked).
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
}
fwrite(STDERR, "FLAGGED: $flagged leaking comment(s)\n");
exit($flagged > 0 ? 1 : 0);
