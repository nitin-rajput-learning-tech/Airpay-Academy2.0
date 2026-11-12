<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Self-test for tools/check-upgrade-step-versions.php.
 *
 *   php tools/check-upgrade-step-versions-selftest.php [--tool <gate.php>] [--dir <parent dir>]
 *
 * Each case makes a small git repo under <parent dir> (default: the system temp dir): a working tree of plain files
 * and a base commit built with plumbing (hash-object, mktree --missing, commit-tree), so no index, checkout or hook
 * is involved. It runs the gate there with --base and checks the exit code and output. A base tree can hold entries
 * whose objects do not exist; that is how the "git cannot read the base" cases are made without damaging a real
 * repo. The setup commands run with GIT_CONFIG_GLOBAL pointing at an empty file and GIT_CONFIG_NOSYSTEM=1, so the
 * user's own git config (identity, default branch, object format) plays no part; the gate runs with the normal
 * environment. Needs git 2.32 or later. The repos are left in place for inspection and their path is printed.
 * Exit 0 when every case passes, 1 when any fails, 2 when setup fails.
 *
 * Not covered: git cat-file stopping or dying part way through (that needs a fake git binary).
 */

$tool = __DIR__ . '/check-upgrade-step-versions.php';
$parent = sys_get_temp_dir();
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--tool' && isset($argv[$i + 1])) {
        $tool = $argv[++$i];
    } else if ($argv[$i] === '--dir' && isset($argv[$i + 1])) {
        $parent = $argv[++$i];
    } else {
        fwrite(STDERR, "usage: php tools/check-upgrade-step-versions-selftest.php [--tool <gate.php>] [--dir <parent dir>]\n");
        exit(2);
    }
}
if (!is_file($tool)) {
    fwrite(STDERR, "no gate script at $tool\n");
    exit(2);
}
$work = rtrim(str_replace('\\', '/', $parent), '/') . '/upgrade-gate-selftest-' . date('Ymd-His') . '-' . getmypid();
if (!mkdir($work, 0777, true)) {
    fwrite(STDERR, "cannot create $work\n");
    exit(2);
}
file_put_contents("$work/empty.gitconfig", '');
$setupenv = array_merge(getenv(), [
    'GIT_CONFIG_GLOBAL' => "$work/empty.gitconfig",
    'GIT_CONFIG_NOSYSTEM' => '1',
    'GIT_AUTHOR_NAME' => 'selftest', 'GIT_AUTHOR_EMAIL' => 'selftest@example.invalid',
    'GIT_COMMITTER_NAME' => 'selftest', 'GIT_COMMITTER_EMAIL' => 'selftest@example.invalid',
    'GIT_AUTHOR_DATE' => '2026-01-01T00:00:00Z', 'GIT_COMMITTER_DATE' => '2026-01-01T00:00:00Z',
]);

// Runs a command without a shell; stdout and stderr go to files, so no pipe can fill up.
$run = function (array $cmd, string $cwd, ?array $env, string $stdin = '') use ($work): array {
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', "$work/last.out", 'w'], 2 => ['file', "$work/last.err", 'w']],
        $pipes, $cwd, $env);
    if (!is_resource($proc)) {
        fwrite(STDERR, 'cannot start ' . implode(' ', $cmd) . "\n");
        exit(2);
    }
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $rc = proc_close($proc);
    return [$rc, (string) file_get_contents("$work/last.out"), (string) file_get_contents("$work/last.err")];
};
$git = function (string $repo, array $args, string $stdin = '') use ($run, $setupenv): string {
    [$rc, $out, $err] = $run(array_merge(['git', '-C', $repo], $args), $repo, $setupenv, $stdin);
    if ($rc !== 0) {
        fwrite(STDERR, 'setup failed: git ' . implode(' ', $args) . " (exit $rc): " . trim($err) . "\n");
        exit(2);
    }
    return trim($out);
};

// File specs: a string is the file's content; these two make a tree entry whose object does not exist.
const MISSING_BLOB = "\0missing blob";
const MISSING_TREE = "\0missing tree";
$mktree = function (string $repo, array $files) use (&$mktree, $git): string {
    $entries = [];
    $subdirs = [];
    foreach ($files as $path => $spec) {
        $parts = explode('/', $path, 2);
        if (count($parts) === 2) {
            $subdirs[$parts[0]][$parts[1]] = $spec;
        } else if ($spec === MISSING_TREE) {
            $entries[] = '040000 tree ' . str_repeat('2', 40) . "\t$path";
        } else if ($spec === MISSING_BLOB) {
            $entries[] = '100644 blob ' . str_repeat('1', 40) . "\t$path";
        } else {
            $entries[] = '100644 blob ' . $git($repo, ['hash-object', '-w', '--stdin'], $spec) . "\t$path";
        }
    }
    foreach ($subdirs as $dir => $inner) {
        $entries[] = '040000 tree ' . $mktree($repo, $inner) . "\t$dir";
    }
    return $git($repo, ['mktree', '--missing'], $entries ? implode("\n", $entries) . "\n" : '');
};

$version = function (string $v): string {
    return "<?php\ndefined('MOODLE_INTERNAL') || die();\n\$plugin->component = 'local_x';\n\$plugin->version = $v;\n";
};
$upgrade = function (string ...$steps): string {
    $php = "<?php\ndefined('MOODLE_INTERNAL') || die();\nfunction xmldb_local_x_upgrade(\$oldversion) {\n";
    foreach ($steps as $v) {
        $php .= "    if (\$oldversion < $v) {\n        upgrade_plugin_savepoint(true, $v, 'local', 'x');\n    }\n";
    }
    return $php . "    return true;\n}\n";
};
// A plugin directory's two files.
$plugin = function (string $dir, string $v, array $steps) use ($version, $upgrade): array {
    return ["$dir/version.php" => $version($v), "$dir/db/upgrade.php" => $upgrade(...$steps)];
};

$cases = 0;
$failed = 0;
// $base: file specs of the base commit, or a string to pass as --base unchanged. $want: [exit code, patterns the
// output must match, patterns it must not match]. Output is stdout and stderr together.
$case = function (string $name, $base, array $files, int $wantrc, array $must, array $mustnot = [])
        use (&$cases, &$failed, $work, $tool, $run, $git, $mktree): void {
    $cases++;
    $repo = sprintf('%s/%02d', $work, $cases);
    mkdir($repo);
    $git($repo, ['init', '-q', '--object-format=sha1', '--initial-branch=main']);
    foreach ($files as $path => $content) {
        if (!is_dir(dirname("$repo/$path"))) {
            mkdir(dirname("$repo/$path"), 0777, true);
        }
        file_put_contents("$repo/$path", $content);
    }
    $ref = is_string($base) ? $base : $git($repo, ['commit-tree', $mktree($repo, $base), '-m', 'base']);
    [$rc, $out, $err] = $run([PHP_BINARY, $tool, '--base', $ref], $repo, null);
    $text = $out . $err;
    $why = [];
    if ($rc !== $wantrc) {
        $why[] = "exit $rc, want $wantrc";
    }
    foreach ($must as $re) {
        if (!preg_match($re, $text)) {
            $why[] = "no match for $re";
        }
    }
    foreach ($mustnot as $re) {
        if (preg_match($re, $text)) {
            $why[] = "unwanted match for $re";
        }
    }
    if ($why) {
        $failed++;
        printf("FAIL  %02d %s: %s\n", $cases, $name, implode('; ', $why));
        foreach (explode("\n", trim($text)) as $line) {
            echo "        $line\n";
        }
    } else {
        printf("ok    %02d %s\n", $cases, $name);
    }
};

$a = $plugin('local/a', '2026020100', ['2026010100', '2026020100']);

$case('unchanged plugin in both twin trees', $a + $plugin('moodle-enhancement/local/a', '2026020100', ['2026010100', '2026020100']),
    $a + $plugin('moodle-enhancement/local/a', '2026020100', ['2026010100', '2026020100']),
    0, ['/2 plugin\(s\) compared with \w+, 0 finding\(s\)/']);

$case('new steps at and below the base version fail, one above passes, a new plugin is skipped',
    $plugin('local/a', '2026020100', ['2026010100']),
    $plugin('local/a', '2026030100', ['2026010100', '2026015000', '2026020100', '2026030100'])
        + $plugin('local/b', '2026030100', ['2026010100']),
    1, ['/FAIL  local\/a: new upgrade step 2026015000 is not above 2026020100/',
        '/FAIL  local\/a: new upgrade step 2026020100 is not above 2026020100/',
        '/1 plugin\(s\) compared with \w+, 2 finding\(s\)/'],
    ['/2026030100 is not above/', '/local\/b/']);

$case('new steps are found under every plugin root',
    $plugin('moodle-enhancement/local/a', '2026020100', ['2026010100']) + $plugin('moodle-enhancement/blocks/b', '2026020100', ['2026010100'])
        + $plugin('local/c', '2026020100', ['2026010100']) + $plugin('blocks/d', '2026020100', ['2026010100'])
        + $plugin('theme/sentientia', '2026020100', ['2026010100']),
    $plugin('moodle-enhancement/local/a', '2026030100', ['2026010100', '2026015000']) + $plugin('moodle-enhancement/blocks/b', '2026030100', ['2026010100', '2026015000'])
        + $plugin('local/c', '2026030100', ['2026010100', '2026015000']) + $plugin('blocks/d', '2026030100', ['2026010100', '2026015000'])
        + $plugin('theme/sentientia', '2026030100', ['2026010100', '2026015000']),
    1, ['/5 plugin\(s\) compared with \w+, 5 finding\(s\)/']);

$case('upgrade.php absent at base: a step below the base version fails',
    ['local/c/version.php' => $version('2026020100')],
    $plugin('local/c', '2026030100', ['2026010100', '2026030100']),
    1, ['/FAIL  local\/c: new upgrade step 2026010100 is not above 2026020100/', '/1 plugin\(s\) compared with \w+, 1 finding\(s\)/'],
    ['/2026030100 is not above/']);

$case('upgrade.php absent at base: steps above the base version pass and the plugin is compared',
    ['local/c/version.php' => $version('2026020100')],
    $plugin('local/c', '2026030100', ['2026030100']),
    0, ['/1 plugin\(s\) compared with \w+, 0 finding\(s\)/']);

foreach (['moodle-enhancement/local/a', 'moodle-enhancement/blocks/a', 'local/a', 'blocks/a', 'theme/sentientia'] as $dir) {
    $case("base version.php under $dir is in the tree but unreadable",
        ["$dir/version.php" => MISSING_BLOB, "$dir/db/upgrade.php" => $upgrade('2026010100', '2026020100')],
        $plugin($dir, '2026020100', ['2026010100', '2026020100']),
        2, ['#' . preg_quote("$dir/version.php", '#') . ' is in the base tree but git cannot read its object#'],
        ['/compared with/']);
}

$case('base upgrade.php is in the tree but unreadable (the 2026-11-12 false FAILs)',
    ['local/a/version.php' => $version('2026020100'), 'local/a/db/upgrade.php' => MISSING_BLOB],
    $a,
    2, ['#local/a/db/upgrade\.php is in the base tree but git cannot read its object#'], ['/FAIL  /', '/compared with/']);

$case('a base plugin directory is unreadable',
    ['local/a' => MISSING_TREE],
    $a,
    2, ['/cannot list plugin files at \w+: git ls-tree exit/'], ['/compared with/']);

$case('base version.php without $plugin->version is reported as SKIP',
    ['local/a/version.php' => "<?php\n// no version here\n", 'local/a/db/upgrade.php' => $upgrade('2026010100')],
    $a,
    0, ['/SKIP  local\/a: no \$plugin->version in its version\.php at \w+/', '/0 plugin\(s\) compared with \w+, 0 finding\(s\)/']);

$case('no upgrade.php in the working tree (run from the wrong directory)',
    $a,
    ['README.md' => "nothing here\n"],
    2, ['/no db\/upgrade\.php found under .* - run this from the repo root/'], ['/compared with/']);

$case('base is not a commit', 'no-such-ref', $a, 2, ["/base 'no-such-ref' is not a commit/"]);

printf("%d of %d case(s) passed. Repos: %s\n", $cases - $failed, $cases, $work);
exit($failed ? 1 : 0);
