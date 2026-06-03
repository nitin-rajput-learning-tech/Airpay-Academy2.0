<?php
// ADR-022 component-rename codemod — Track 1 (source rewrite). DRY-RUN by default.
//
// Reusable across leaf plugins. Renames the slug `<old>` -> `<new>` across the
// plugin directory AND every cross-plugin reference in the repo. Because every
// frankenstyle form of the component contains the slug substring
// (`local_<slug>`, `local/<slug>:cap`, `{local_<slug>_*}`, `\local_<slug>\`,
// `local_<slug>_wsname`, the `lang/en/local_<slug>.php` file, the dir name),
// a single guarded substring rewrite covers them all — verified by the
// post-rewrite coverage assertion (0 residual `<old>` outside intentional shims).
//
// Track 2 (the DB hand-over: rename_table + config_plugins + capability re-point
// + files) ships SEPARATELY in the renamed plugin's db/upgrade.php — NOT here.
//
// Usage (run from repo root):
//   php moodle-enhancement/tools/rename/codemod.php airpay_ratings sentientia_ratings           # dry-run
//   php moodle-enhancement/tools/rename/codemod.php airpay_ratings sentientia_ratings --apply    # rewrite + move
//
// --apply rewrites file contents then PHP-renames the dir + lang file (no shell);
// `git add` afterwards records the move as a rename. Review the dry-run diff first,
// on a clone-DB rehearsal branch. Idempotent: re-running finds 0 changes.

$old = $argv[1] ?? '';
$new = $argv[2] ?? '';
$apply = in_array('--apply', $argv, true);
if ($old === '' || $new === '') {
    fwrite(STDERR, "usage: php codemod.php <oldslug> <newslug> [--apply]\n");
    exit(2);
}

$norm = fn(string $p): string => str_replace('\\', '/', $p);     // Windows-safe path compare
$root = $norm(realpath(__DIR__ . '/../..'));     // moodle-enhancement/
$plugindir = "$root/local/$old";
if (!is_dir($plugindir)) {
    fwrite(STDERR, "plugin dir not found: $plugindir\n");
    exit(2);
}

// Own-plugin files: ALL text exts (incl. the plugin's own README.md — it renames with the plugin).
$ownexts = ['php', 'mustache', 'js', 'xml', 'json', 'md'];
// Cross-ref rewriting: CODE only. Repo-wide docs (audits, ADRs, parity-audit, state-cards,
// PROJECT-STATE, RUNBOOK) are HISTORICAL records — never rewrite the name out of history.
$codeexts = ['php', 'mustache', 'js', 'xml', 'json'];

$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugindir, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    if ($f->isFile() && in_array(strtolower($f->getExtension()), $ownexts, true)) {
        $files[$norm($f->getPathname())] = true;
    }
}
$scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($scan as $f) {
    if (!$f->isFile() || !in_array(strtolower($f->getExtension()), $codeexts, true)) continue;
    $p = $norm($f->getPathname());
    if (strpos($p, $plugindir . '/') === 0) continue;            // own files already collected
    if (strpos($p, '/amd/build/') !== false) continue;           // built artifacts
    if (strpos($p, '/tools/rename') !== false) continue;         // don't rewrite this tool
    $c = @file_get_contents($p);
    if ($c !== false && strpos($c, $old) !== false) {
        $files[$p] = true;
    }
}

$totalrefs = 0; $totalfiles = 0;
echo "=== ADR-022 codemod (" . ($apply ? "APPLY" : "DRY-RUN") . "): $old -> $new ===\n\n";
foreach (array_keys($files) as $path) {
    $src = file_get_contents($path);
    $cnt = substr_count($src, $old);
    if ($cnt === 0) continue;
    $totalrefs += $cnt; $totalfiles++;
    $rel = str_replace($root . '/', '', $path);
    $own = (strpos($path, $plugindir . '/') === 0);
    echo sprintf("  %-11s %-3d  %s\n", $own ? "[own]" : "[CROSS-REF]", $cnt, $rel);
    if ($apply) { file_put_contents($path, str_replace($old, $new, $src)); }
}

echo "\n--- summary ---\n";
echo "files touched: $totalfiles   total refs rewritten: $totalrefs\n";
echo "dir rename:  local/$old/  ->  local/$new/\n";
echo "lang rename: lang/en/local_$old.php  ->  lang/en/local_$new.php\n";

if ($apply) {
    rename("$root/local/$old", "$root/local/$new");               // PHP move, no shell
    $oldlang = "$root/local/$new/lang/en/local_$old.php";
    if (file_exists($oldlang)) { rename($oldlang, "$root/local/$new/lang/en/local_$new.php"); }
    echo "\nMoved dir + lang file. Next: `git add` (git records the rename), then GUARD:\n";
    echo "  grep -rn '$old' moodle-enhancement/   # expect only intentional WS back-compat shims\n";
} else {
    echo "\n(dry-run — no files changed. Re-run with --apply on a clone-DB rehearsal branch after review.)\n";
}
