<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Gate: an upgrade step added since <base> must carry a version ABOVE the version the plugin had at <base>.
 *
 * Moodle runs `if ($oldversion < X)` only on sites that are below X. A step added with a version a site has already
 * passed is skipped there, silently. That happened on 2026-10-16: the eval-followups merge added EV-17's steps at
 * 2026100701/02 while gap-integration had already shipped local_sentientia_evaluation 2026100802, so every site at
 * 2026100802 skipped the new column (my_evaluations.php failed with "Unknown column 'e.evaluationmode'"; fixed by a
 * repeat step in 1b6a00698). PHPUnit cannot see it: a test site installs from install.xml.
 *
 *   php tools/check-upgrade-step-versions.php [--base <git ref>]     (default: the merge base with origin/production)
 *
 * Run it from the repo root. When production has been fast-forwarded to HEAD, the default base is HEAD itself, so
 * only uncommitted changes are compared.
 *
 * For every plugin with db/upgrade.php in the working tree (moodle-enhancement/{local,blocks}, local/, blocks/,
 * theme/sentientia): the savepoint versions in the working tree that are not in <base>'s upgrade.php must all be
 * greater than $plugin->version in <base>'s version.php. A plugin that did not exist at <base> is skipped; an
 * upgrade.php that did not exist at <base> counts every step as new; a base version.php with no readable
 * $plugin->version is reported as SKIP. Exit 1 on any finding. Exit 2 when no upgrade.php is found (wrong
 * directory) or git cannot list or read the base.
 *
 * How "not at base" is decided: one `git ls-tree -r` lists the files under the plugin roots at <base>, then one
 * `git cat-file --batch` reads them. cat-file alone cannot tell a path that is not in the tree from a path whose
 * object cannot be read (both answer "<name> missing"), so a "missing" answer counts as "not at base" only when
 * ls-tree did not list the path. A listed path that cannot be read, an ls-tree failure, or any other unexpected
 * cat-file outcome exits 2.
 *
 * Until 2026-11-13 the tool ran two `git show` per plugin (about 200 processes, 111 to 309 s on the Windows dev box)
 * with stderr discarded. A failed `git show` of an upgrade.php then read as "no steps at base", and a failed read of
 * a version.php skipped the plugin without a word. On 2026-11-12 the gate reported the 3 existing steps of
 * local/sentientia_notifications as new while its byte-identical twin under moodle-enhancement/ passed; one failed
 * read of that upgrade.php gives exactly that output (likely, not proven: the git error was discarded).
 *
 * Self-test (builds throwaway git repos, needs git 2.32 or later):
 *   php tools/check-upgrade-step-versions-selftest.php
 */

$root = getcwd();
$base = null;
foreach ($argv as $i => $a) {
    if ($a === '--base' && isset($argv[$i + 1])) {
        $base = $argv[$i + 1];
    }
}
$git = function (string $args) use ($root): ?string {
    $out = [];
    $rc = 0;
    exec('git -C ' . escapeshellarg($root) . ' ' . $args . ' 2>' . (stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null'), $out, $rc);
    return $rc === 0 ? implode("\n", $out) : null;
};
if ($base === null) {
    $base = $git('merge-base HEAD origin/production');
    if ($base === null) {
        fwrite(STDERR, "no --base given and no merge base with origin/production\n");
        exit(2);
    }
}
$basesha = $git('rev-parse --verify ' . escapeshellarg($base . '^{commit}'));
if ($basesha === null) {
    fwrite(STDERR, "base '$base' is not a commit\n");
    exit(2);
}

$savepoints = function (string $php): array {
    preg_match_all('/upgrade_\w*savepoint\(\s*true\s*,\s*(\d{10}(?:\.\d+)?)/', $php, $m);
    return array_map('floatval', $m[1]);
};
$versionof = function (string $php): ?float {
    return preg_match('/\$plugin->version\s*=\s*(\d{10}(?:\.\d+)?)/', $php, $m) ? (float) $m[1] : null;
};

// The directories the globs further down look in.
$roots = ['moodle-enhancement/local', 'moodle-enhancement/blocks', 'local', 'blocks', 'theme/sentientia'];

// Returns [path => true] for every file under $roots at base. ls-tree reads only tree objects and exits non-zero
// when it cannot read one; that exits 2 here.
$listbase = function () use ($root, $basesha, $roots): array {
    $proc = proc_open('git -C ' . escapeshellarg($root) . " ls-tree -r -z --full-tree --name-only $basesha -- "
        . implode(' ', array_map('escapeshellarg', $roots)),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['blocking_pipes' => true]);
    if (!is_resource($proc)) {
        fwrite(STDERR, "cannot start git ls-tree\n");
        exit(2);
    }
    fclose($pipes[0]);
    $out = (string) stream_get_contents($pipes[1]); // ls-tree writes at most one error line, so stderr cannot fill.
    $err = trim((string) stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    if ($rc !== 0) {
        fwrite(STDERR, sprintf("cannot list plugin files at %s: git ls-tree exit %d%s - no result\n",
            substr($basesha, 0, 9), $rc, $err === '' ? '' : " (git: $err)"));
        exit(2);
    }
    return array_fill_keys(array_filter(explode("\0", $out), 'strlen'), true);
};

// Returns [path => contents at base, or null when ls-tree did not list the path]. Any other git outcome exits 2.
$readbase = function (array $paths, array $listed) use ($root, $basesha): array {
    $proc = proc_open('git -C ' . escapeshellarg($root) . ' cat-file --batch',
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null,
        ['blocking_pipes' => true]); // Windows: without it a pipe read gives up when git is slow.
    if (!is_resource($proc)) {
        fwrite(STDERR, "cannot start git cat-file\n");
        exit(2);
    }
    $blobs = [];
    $problem = null;
    foreach ($paths as $path) {
        // One request at a time: cat-file flushes after each answer, so stdin and stdout cannot fill up. stderr is
        // read only at the end; cat-file writes nothing there in normal use.
        fwrite($pipes[0], "$basesha:$path\n");
        fflush($pipes[0]);
        $head = fgets($pipes[1]);
        if ($head === false) {
            $problem = "no answer for $path";
            break;
        }
        $head = rtrim($head, "\n");
        if ($head === "$basesha:$path missing") {
            if (isset($listed[$path])) {
                $problem = "$path is in the base tree but git cannot read its object";
                break;
            }
            $blobs[$path] = null;
            continue;
        }
        if (!preg_match('/^[0-9a-f]{40,64} blob (\d+)$/', $head, $m)) {
            $problem = "unexpected answer for $path: $head";
            break;
        }
        $size = (int) $m[1];
        $data = '';
        while (strlen($data) < $size && ($chunk = fread($pipes[1], $size - strlen($data))) !== false && $chunk !== '') {
            $data .= $chunk;
        }
        if (strlen($data) !== $size || fread($pipes[1], 1) !== "\n") {
            $problem = "short read for $path";
            break;
        }
        $blobs[$path] = $data;
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    $err = trim((string) stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    if ($problem !== null || $rc !== 0) {
        fwrite(STDERR, sprintf("cannot read plugin files at %s: %s%s - no result\n", substr($basesha, 0, 9),
            $problem ?? "git cat-file exit $rc", $err === '' ? '' : " (git: $err)"));
        exit(2);
    }
    return $blobs;
};

$upgrades = array_merge(
    glob("$root/moodle-enhancement/local/*/db/upgrade.php"),
    glob("$root/moodle-enhancement/blocks/*/db/upgrade.php"),
    glob("$root/local/*/db/upgrade.php"),
    glob("$root/blocks/*/db/upgrade.php"),
    glob("$root/theme/sentientia/db/upgrade.php")
);
if ($upgrades === []) {
    fwrite(STDERR, "no db/upgrade.php found under $root - run this from the repo root\n");
    exit(2);
}
$want = [];
foreach ($upgrades as $file) {
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $want[] = dirname(dirname($rel)) . '/version.php';
    $want[] = $rel;
}
$atbase = $readbase($want, $listbase());
$findings = 0;
$checked = 0;
foreach ($upgrades as $file) {
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $plugindir = dirname(dirname($rel));
    $baseversion = $atbase["$plugindir/version.php"];
    if ($baseversion === null) {
        continue; // New plugin since base: install.xml is its first install, nothing to compare.
    }
    if (($bv = $versionof($baseversion)) === null) {
        printf("SKIP  %s: no \$plugin->version in its version.php at %s - not compared\n", $plugindir, substr($basesha, 0, 9));
        continue;
    }
    $baseupgrade = $atbase[$rel] ?? ''; // null: upgrade.php added since base, so every step in it is new.
    $added = array_diff($savepoints((string) file_get_contents($file)), $savepoints($baseupgrade));
    $checked++;
    foreach ($added as $v) {
        if ($v <= $bv) {
            $findings++;
            printf("FAIL  %s: new upgrade step %s is not above %s, the version this plugin had at %s - sites already at %s would skip it\n",
                $plugindir, (string) $v, (string) $bv, substr($basesha, 0, 9), (string) $bv);
        }
    }
}
printf("upgrade-step versions: %d plugin(s) compared with %s, %d finding(s)\n", $checked, substr($basesha, 0, 9), $findings);
exit($findings ? 1 : 0);
