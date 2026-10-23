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
 * For every plugin with db/upgrade.php in both trees (moodle-enhancement/{local,blocks}, local/, blocks/,
 * theme/sentientia): the savepoint versions in HEAD that are not in <base>'s upgrade.php must all be greater than
 * $plugin->version in <base>'s version.php. A plugin that did not exist at <base> is skipped. Exit 1 on any finding.
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

$upgrades = array_merge(
    glob("$root/moodle-enhancement/local/*/db/upgrade.php"),
    glob("$root/moodle-enhancement/blocks/*/db/upgrade.php"),
    glob("$root/local/*/db/upgrade.php"),
    glob("$root/blocks/*/db/upgrade.php"),
    glob("$root/theme/sentientia/db/upgrade.php")
);
$findings = 0;
$checked = 0;
foreach ($upgrades as $file) {
    $rel = str_replace('\\', '/', substr($file, strlen($root) + 1));
    $plugindir = dirname(dirname($rel));
    $baseversion = $git('show ' . escapeshellarg("$basesha:$plugindir/version.php"));
    if ($baseversion === null || ($bv = $versionof($baseversion)) === null) {
        continue; // New plugin since base: install.xml is its first install, nothing to compare.
    }
    $baseupgrade = $git('show ' . escapeshellarg("$basesha:$rel")) ?? '';
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
