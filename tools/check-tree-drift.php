<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Cross-tree drift gate - the two copies of each plugin must not diverge.
 *
 * WHY THIS EXISTS
 * ---------------
 * This repository carries every local plugin TWICE:
 *
 *     local/<plugin>/                        top-level tree
 *     moodle-enhancement/local/<plugin>/     ME tree
 *
 * Both are deployed from. tools/uat/deploy_to_uat.sh takes --prefer-top or
 * --prefer-me and aborts on duplicate-tree drift, and UAT today runs the ME
 * copy for org, analytics, learningpath, compliance_report and courses. So
 * "which copy is real" depends on the plugin, and a change applied to one tree
 * silently does nothing on the surface served by the other.
 *
 * That is not theoretical. On 2026-09-22:
 *
 *   structured_logger.php   ME had 'component' => 'local_airpay_' . $plugin,
 *                           a prefix retired by ADR-022/025. The top-level copy
 *                           had already been corrected to local_sentientia_.
 *                           Nobody noticed because nothing errors - the logs
 *                           just name a component that does not exist.
 *
 *   sentientia_ratings/     The ME copy was four files: no version.php, no
 *                           lang/, no lib.php. Every shared file was
 *                           byte-identical to the complete top-level copy, so
 *                           it was a truncated copy, not a fork.
 *
 * Both are the same failure: an edit landed in one tree and not the other.
 *
 * The same applies to the other plugin areas that live in both trees (Moodle 5.3
 * compat FX-13): payment/gateway, enrol, mod/quiz/accessrule and admin/tool, each
 * against its moodle-enhancement/ counterpart. The overlay and the package build
 * export from the top-level tree, UAT deploys can take either, and the airpay
 * gateway's get_form.php had drifted (legacy global external_* classes in the
 * ME copy) without anyone noticing. An area whose ME directory does not exist
 * (today admin/tool: the single-file tool_certificate twin was retired) is skipped.
 *
 * WHAT IT DOES
 * ------------
 * Compares every file of every plugin present in both trees, normalising line
 * endings first (the trees genuinely differ in CRLF/LF and that is not drift).
 * Reports three kinds of finding:
 *
 *   CONTENT    the file exists in both trees with different content
 *   ONLY-ME    the file exists only in moodle-enhancement/local/
 *   ONLY-TOP   the file exists only in local/
 *
 * BASELINE
 * --------
 * 70 files already differ and 29 exist in only one tree. Failing the build on
 * all of them would block every push, so tools/tree-drift-baseline.txt records
 * the known set. The gate is BLOCKING for anything NOT in that baseline, which
 * stops new drift immediately while the existing set is drained. Removing a
 * line from the baseline is how a file graduates; the gate also fails if a
 * baselined path has since been reconciled, so the list cannot rot.
 *
 * USAGE
 * -----
 *   php tools/check-tree-drift.php                 human-readable report
 *   php tools/check-tree-drift.php --quiet         FAIL lines only, for CI
 *   php tools/check-tree-drift.php --update-baseline
 *                                                  rewrite the baseline file
 *
 * --update-baseline is deliberately NOT what CI runs. It exists so that a
 * deliberate, reviewed divergence can be recorded in one step.
 */

/**
 * Plugin areas that exist in both trees: [ME dir, top-level dir, prefix of the finding key].
 * The first (local plugins) is mandatory and keeps its historic un-prefixed keys, so the
 * baseline needs no rewrite. The others are optional: an area missing on either side is skipped.
 */
const AREAS = [
    ['moodle-enhancement/local', 'local', ''],
    ['moodle-enhancement/payment/gateway', 'payment/gateway', 'payment/gateway/'],
    ['moodle-enhancement/enrol', 'enrol', 'enrol/'],
    ['moodle-enhancement/mod/quiz/accessrule', 'mod/quiz/accessrule', 'mod/quiz/accessrule/'],
    ['moodle-enhancement/admin/tool', 'admin/tool', 'admin/tool/'],
];
const BASELINE = 'tools/tree-drift-baseline.txt';

$opts = array_slice($argv, 1);
$quiet = in_array('--quiet', $opts, true);
$update = in_array('--update-baseline', $opts, true);

$root = dirname(__DIR__);
$a = $root . '/' . AREAS[0][0];
$b = $root . '/' . AREAS[0][1];

if (!is_dir($a) || !is_dir($b)) {
    fwrite(STDERR, "check-tree-drift: expected both {$a} and {$b} to exist\n");
    exit(2);
}

/**
 * Hash a file with line endings normalised.
 *
 * The two trees differ in CRLF versus LF on many files for reasons that have
 * nothing to do with content, and git's autocrlf makes that worse. Comparing
 * raw bytes would drown the real findings.
 *
 * @param string $path
 * @return string
 */
function drift_hash(string $path): string {
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return 'unreadable';
    }
    return hash('sha256', str_replace("\r\n", "\n", $raw));
}

/**
 * Every file under a directory, relative and slash-normalised.
 *
 * @param string $dir
 * @return string[]
 */
function drift_files(string $dir): array {
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($dir) + 1);
        $out[] = str_replace('\\', '/', $rel);
    }
    sort($out);
    return $out;
}

// ── collect findings ─────────────────────────────────────────────────────
$plugins = [];
$findings = [];
foreach (AREAS as [$areame, $areatop, $prefix]) {
    $a = $root . '/' . $areame;
    $b = $root . '/' . $areatop;
    if (!is_dir($a) || !is_dir($b)) {
        continue;
    }
    $areaplugins = [];
    foreach (scandir($a) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir("{$a}/{$entry}") && is_dir("{$b}/{$entry}")) {
            $areaplugins[] = $entry;
            $plugins[] = $prefix . $entry;
        }
    }
    sort($areaplugins);

    foreach ($areaplugins as $plugin) {
        $fa = drift_files("{$a}/{$plugin}");
        $fb = drift_files("{$b}/{$plugin}");
        $key = $prefix . $plugin;

        foreach (array_intersect($fa, $fb) as $rel) {
            if (drift_hash("{$a}/{$plugin}/{$rel}") !== drift_hash("{$b}/{$plugin}/{$rel}")) {
                $findings["CONTENT {$key}/{$rel}"] = true;
            }
        }
        foreach (array_diff($fa, $fb) as $rel) {
            $findings["ONLY-ME {$key}/{$rel}"] = true;
        }
        foreach (array_diff($fb, $fa) as $rel) {
            $findings["ONLY-TOP {$key}/{$rel}"] = true;
        }
    }
}
sort($plugins);
$findings = array_keys($findings);
sort($findings);

// ── baseline ─────────────────────────────────────────────────────────────
$baselinepath = $root . '/' . BASELINE;

if ($update) {
    $header = "# Cross-tree drift baseline - see tools/check-tree-drift.php\n"
        . "#\n"
        . "# Every line is a file that differs between local/ and\n"
        . "# moodle-enhancement/local/ (or between the payment/enrol/mod/admin\n"
        . "# areas and their moodle-enhancement/ twins) and has NOT yet been reconciled. The gate\n"
        . "# fails on anything not listed here, so this list can only shrink\n"
        . "# without a deliberate --update-baseline.\n"
        . "#\n"
        . "# To drain one: decide which tree is authoritative, copy that file\n"
        . "# over the other, delete the line here. If you are not sure which is\n"
        . "# authoritative, check whether UAT serves this plugin from the ME\n"
        . "# tree (org, analytics, learningpath, compliance_report, courses).\n"
        . "#\n"
        . '# Generated ' . date('Y-m-d') . ' with --update-baseline' . "\n";
    file_put_contents($baselinepath, $header . implode("\n", $findings) . "\n");
    echo 'Baseline written: ' . count($findings) . " entries\n";
    exit(0);
}

$baseline = [];
if (is_readable($baselinepath)) {
    foreach (file($baselinepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] !== '#') {
            $baseline[$line] = true;
        }
    }
}

$new = array_values(array_filter($findings, static fn($f) => !isset($baseline[$f])));
$stale = array_values(array_filter(array_keys($baseline),
    static fn($f) => !in_array($f, $findings, true)));

// ── report ───────────────────────────────────────────────────────────────
if (!$quiet) {
    echo "Cross-tree drift: " . count($plugins) . " plugins in both trees (local, payment/gateway, enrol, mod/quiz/accessrule, admin/tool)\n";
    echo '  total drifting files : ' . count($findings) . "\n";
    echo '  baselined (known)    : ' . count($baseline) . "\n";
    echo '  NEW (blocking)       : ' . count($new) . "\n";
    echo '  baseline now stale   : ' . count($stale) . "\n\n";
}

foreach ($new as $f) {
    echo "FAIL new drift: {$f}\n";
}
foreach ($stale as $f) {
    echo "FAIL baseline entry no longer drifts, delete it: {$f}\n";
}

if (!empty($new) || !empty($stale)) {
    if (!$quiet) {
        echo "\nThe two trees are both deployed from. A change in one and not the\n"
            . "other is invisible until the wrong copy is served. Apply the change\n"
            . "to both, or record a deliberate divergence with --update-baseline.\n";
    }
    exit(1);
}

if (!$quiet) {
    echo "OK - no new cross-tree drift.\n";
}
exit(0);
