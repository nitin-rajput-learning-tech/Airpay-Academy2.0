<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Path-boundary scanner — refuses unbounded tenant/org path prefixes.
 *
 * WHY THIS EXISTS
 * ---------------
 * The same defect shipped four times, and every time it was silent: the
 * platform kept working and only the NUMBERS came out wrong, which is the
 * worst failure mode for a compliance report an auditor will read.
 *
 *   2026-09-07  admin dashboard        `open_path LIKE '/1%'`   counted the ZEEA tenant's users
 *                                                               as Airpay's (749 instead of 743)
 *   2026-09-16  compliance BU filter   `open_path LIKE '/1%'`   offered ZEEA to an Airpay admin
 *   2026-09-22  department scorecard   `$dept->path . '%'`      /1/2 swallowed /1/20 and /1/21
 *   2026-09-22  org-children picker    `'%/' . $id . '/%'`      leaf users counted as zero
 *
 * A tenant/org path is a `/`-delimited materialised path. Prefix matching on
 * it MUST be `/`-terminated, and must separately match the node itself:
 *
 *     ($col = :exact OR $col LIKE :prefix)     with prefix = $path . '/%'
 *
 * The shared helpers that do this are
 * `\local_sentientia_platform\tenant::path_descendant_filter()` for an
 * arbitrary path and `::path_filter()` for the viewer's own tenant. New code
 * calls one of those instead of hand-rolling a LIKE.
 *
 * WHAT IT FLAGS
 * -------------
 *   1. unbounded-literal    LIKE '/77%' on a path column, not '/'-terminated
 *   2. unbounded-concat     a path pattern built with a bare '%'
 *   3. slash-both-sides     '%/' . $x . '/%', which misses leaf nodes
 *   4. unbounded-php-prefix str_starts_with on a path without the '/' boundary
 *
 * USAGE
 * -----
 *   php tools/check-path-boundary.php                    whole repo, human output
 *   php tools/check-path-boundary.php --quiet            FAIL lines only, for CI
 *   git diff --cached --name-only --diff-filter=ACM \
 *       | php tools/check-path-boundary.php --stdin      only these files (pre-commit)
 *
 * Reads the file list from STDIN rather than shelling out to git, so the
 * scanner never builds a command line.
 *
 * Exit code 0 = clean, 1 = at least one finding.
 *
 * SUPPRESSION
 * -----------
 * A genuinely-correct exception carries an inline marker on the same line or
 * the line above, WITH a reason:
 *
 *     // path-boundary-ok: depth=3 rows can never be the tenant root
 *
 * @package tools
 */

$opts = getopt('', ['stdin', 'quiet', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php tools/check-path-boundary.php [--quiet]\n"
        . "       git diff --cached --name-only --diff-filter=ACM | php tools/check-path-boundary.php --stdin\n");
    exit(0);
}
$fromstdin = isset($opts['stdin']);
$quiet     = isset($opts['quiet']);

$root = dirname(__DIR__);
chdir($root);

/** Path-ish column names we police. */
$pathcols = ['open_path', 'department_path', 'org_path', 'tenant_path'];

/** Directories that hold product code worth policing. */
$scandirs = ['local', 'moodle-enhancement/local', 'moodle-enhancement/blocks', 'theme/sentientia'];

/** Never scan: vendored, compiled, third-party or language packs. */
$skipre = '#(/vendor/|/node_modules/|/amd/build/|/_stale|\.min\.js$|/lang/|/backup/)#';

$files = [];
if ($fromstdin) {
    while (($line = fgets(STDIN)) !== false) {
        $f = trim($line);
        if ($f === '' || substr($f, -4) !== '.php') {
            continue;
        }
        if (is_file($f) && !preg_match($skipre, '/' . str_replace('\\', '/', $f))) {
            $files[] = $f;
        }
    }
} else {
    foreach ($scandirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = str_replace('\\', '/', $f->getPathname());
            if ($f->isFile() && substr($p, -4) === '.php' && !preg_match($skipre, '/' . $p)) {
                $files[] = $p;
            }
        }
    }
}

$findings = [];

foreach ($files as $file) {
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    $intest = (strpos(str_replace('\\', '/', $file), '/tests/') !== false);

    foreach ($lines as $i => $line) {
        $lineno = $i + 1;

        // Suppression marker on this line or the one above.
        $prev = $i > 0 ? $lines[$i - 1] : '';
        if (strpos($line, 'path-boundary-ok') !== false
                || strpos($prev, 'path-boundary-ok') !== false) {
            continue;
        }

        // Comment-only lines cannot execute. Docblocks legitimately quote the
        // anti-pattern when explaining why it is wrong, and flagging those
        // teaches people to ignore the scanner.
        $trimmed = ltrim($line);
        if ($trimmed === '' || $trimmed[0] === '*' || $trimmed[0] === '#'
                || strncmp($trimmed, '//', 2) === 0
                || strncmp($trimmed, '/*', 2) === 0) {
            continue;
        }

        // ── Rule 3: '%/' . $x . '/%' misses leaf nodes ────────────────────
        if (preg_match("#'%/'\s*\.\s*.+\.\s*'/%'#", $line)) {
            $findings[] = [$file, $lineno, 'slash-both-sides',
                "'%/x/%' misses leaf nodes: a row at '/1/5' never matches. "
                . 'Use tenant::path_descendant_filter($path).', trim($line)];
            continue;
        }

        // ── Rule 2: a path pattern built with a bare '%' ──────────────────
        // $toporg . '%'   |   $dept->path . '%'   |   '/' . $id . '%'
        // The correct '/%' form does not match this pattern.
        //
        // Narrowed so percentage formatting ($rate . '%') does not trip it:
        // fire only when the expression is path-ish, i.e. the concatenated
        // variable is named for a path, OR the expression is rooted at a
        // literal '/' (the shape that builds a materialised path).
        if (preg_match("#(\\\$[a-zA-Z_][\w>\-\[\]'\"\$\.]*|\w+->path)\s*\.\s*'%'#", $line, $m)) {
            $expr = $m[1];
            // Deliberately narrow: a bare 'dept' matched $dept['rate'] . '%' (a
            // percentage), so the token must look like it carries a path.
            $ispathy = (bool) preg_match('#path|org|tenant|costcenter#i', $expr);
            // "'/' . $x . '%'" — rooted at a slash literal.
            $isrooted = (bool) preg_match("#'/'\s*\.\s*" . preg_quote($expr, '#') . "\s*\.\s*'%'#", $line)
                || (bool) preg_match("#'/[^']*'\s*\.\s*.{0,40}\.\s*'%'#", $line);
            if ($ispathy || $isrooted) {
                $findings[] = [$file, $lineno, 'unbounded-concat',
                    "path pattern ends in a bare '%', so /1/2 also matches /1/20. "
                    . "Terminate with '/%' and match the node itself, or use "
                    . 'tenant::path_descendant_filter().', trim($line)];
            }
            continue;
        }

        // ── Rule 1: LIKE on a path column with a non-'/%' literal ─────────
        foreach ($pathcols as $col) {
            if (stripos($line, $col) === false || stripos($line, 'LIKE') === false) {
                continue;
            }
            if (preg_match("#LIKE\s+'([^']*)'#i", $line, $m)) {
                $pat = $m[1];
                if (substr($pat, -1) === '%' && substr($pat, -2) !== '/%') {
                    $findings[] = [$file, $lineno, 'unbounded-literal',
                        "LIKE '{$pat}' is not '/'-terminated, so it matches sibling "
                        . 'paths that share a digit prefix.', trim($line)];
                }
            }
            break;
        }

        // ── Rule 4: PHP-side prefix test without the '/' boundary ─────────
        if (!$intest && preg_match(
                '#str_starts_with\s*\(\s*(\$[\w>\-\[\]\'"\$\.]+)\s*,\s*(\$[\w>\-\[\]\'"\$\.]+)\s*\)#',
                $line, $m)) {
            // The correct form appends the separator: str_starts_with($p, $dpath . '/')
            if (!preg_match("#\.\s*'/'\s*\)#", $line)) {
                $haspathword = false;
                foreach (['path', 'Path'] as $w) {
                    if (strpos($m[1], $w) !== false || strpos($m[2], $w) !== false) {
                        $haspathword = true;
                    }
                }
                if ($haspathword) {
                    $findings[] = [$file, $lineno, 'unbounded-php-prefix',
                        "str_starts_with on a path without the '/' separator: "
                        . "'/1/2' also prefixes '/1/20'. Compare equality OR append . '/'.",
                        trim($line)];
                }
            }
        }
    }
}

// ── Report ────────────────────────────────────────────────────────────────
if (!$findings) {
    if (!$quiet) {
        fwrite(STDOUT, sprintf(
            "OK  path-boundary: %d file(s) scanned, no unbounded path prefixes.\n", count($files)));
    }
    exit(0);
}

$byrule = [];
foreach ($findings as $f) {
    $byrule[$f[2]] = true;
}
foreach ($findings as [$file, $lineno, $rule, $why, $code]) {
    fwrite(STDOUT, "FAIL {$file}:{$lineno}  [{$rule}]\n");
    if (!$quiet) {
        fwrite(STDOUT, "       {$code}\n       {$why}\n");
    }
}
if (!$quiet) {
    fwrite(STDOUT, sprintf("\n%d finding(s) across %d rule(s). Scanned %d file(s).\n",
        count($findings), count($byrule), count($files)));
    fwrite(STDOUT, "Fix with \\local_sentientia_platform\\tenant::path_descendant_filter"
        . "(\$path, \$alias, \$column, \$tag)\n");
    fwrite(STDOUT, "or, for the viewer's own tenant, ::path_filter(\$alias, \$column, \$allow_null).\n");
    fwrite(STDOUT, "A genuinely-correct exception needs an inline "
        . "`path-boundary-ok: <reason>` marker.\n");
}
exit(1);
