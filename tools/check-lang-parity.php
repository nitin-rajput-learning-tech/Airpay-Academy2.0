<?php
/**
 * en↔hi lang-pack parity gate — Sentientia LMS.
 *
 * CLAUDE.md mandates 100% Hindi parity, but until 2026-08-04 that was
 * convention-only and regressed silently (the UI-NAV shell chrome shipped
 * hardcoded English; see ADR-028 Phase 1.5). This gate makes it mechanical:
 *
 *   FAIL — a component HAS a lang/hi pack whose key set differs from lang/en
 *          (missing or extra keys). Parity is only enforceable where a hi
 *          pack exists, so drift there is always a bug.
 *   WARN — a component has lang/en but NO lang/hi pack at all (the known
 *          coverage backlog: 6 en-only blocks/plugins as of 2026-08-04).
 *          Warned, not failed, so CI stays green while the backlog is real;
 *          flip $failonmissingpack once coverage closes.
 *
 * Usage:  php tools/check-lang-parity.php            (whole repo)
 *         php tools/check-lang-parity.php --quiet    (errors/warnings only)
 * Exit:   0 = parity OK (warnings allowed) · 1 = parity violation(s).
 *
 * Standalone CLI — no Moodle bootstrap (runs in CI and pre-commit).
 */

$quiet = in_array('--quiet', $argv ?? [], true);
$failonmissingpack = false; // flip when the hi coverage backlog is closed.

$repo = dirname(__DIR__);
$roots = [
    $repo . '/moodle-enhancement/local',
    $repo . '/moodle-enhancement/blocks',
    $repo . '/moodle-enhancement/mod/quiz/accessrule',
    $repo . '/moodle-enhancement/payment/gateway',
    $repo . '/moodle-enhancement/enrol',
    $repo . '/theme/sentientia',           // theme lives top-level only.
];

/**
 * Extract $string[...] keys from a Moodle lang file without executing
 * arbitrary code paths: the house style is plain assignments, so a
 * tokenizer pass looking for $string['key'] is sufficient and safe.
 */
function langkeys(string $file): array {
    $keys = [];
    $tokens = token_get_all(file_get_contents($file));
    $n = count($tokens);
    for ($i = 0; $i < $n - 2; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_VARIABLE
                && $tokens[$i][1] === '$string'
                && $tokens[$i + 1] === '['
                && is_array($tokens[$i + 2])
                && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING) {
            $keys[] = trim($tokens[$i + 2][1], "'\"");
        }
    }
    return array_unique($keys);
}

$fail = 0; $warn = 0; $checked = 0;

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    // A "component dir" is $root itself when it directly holds lang/en
    // (the theme), else each first-level child that does.
    $candidates = is_dir($root . '/lang/en') ? [$root] : array_filter(glob($root . '/*'), 'is_dir');
    foreach ($candidates as $dir) {
        $endir = $dir . '/lang/en';
        if (!is_dir($endir)) {
            continue;
        }
        foreach (glob($endir . '/*.php') as $enfile) {
            $checked++;
            $base = basename($enfile);
            $hifile = $dir . '/lang/hi/' . $base;
            $rel = str_replace($repo . '/', '', $dir);
            if (!file_exists($hifile)) {
                $warn++;
                echo ($failonmissingpack ? "FAIL" : "WARN") . " no-hi-pack: {$rel} ({$base})\n";
                if ($failonmissingpack) {
                    $fail++;
                }
                continue;
            }
            $en = langkeys($enfile);
            $hi = langkeys($hifile);
            $missing = array_diff($en, $hi);
            $extra = array_diff($hi, $en);
            if ($missing || $extra) {
                $fail++;
                echo "FAIL parity: {$rel} ({$base})\n";
                foreach ($missing as $k) {
                    echo "       missing in hi: {$k}\n";
                }
                foreach ($extra as $k) {
                    echo "       extra in hi (not in en): {$k}\n";
                }
            } else if (!$quiet) {
                echo "  ok {$rel} ({$base}): " . count($en) . " keys\n";
            }
        }
    }
}

echo "\nlang-parity: {$checked} en files checked, {$fail} failure(s), {$warn} warning(s)\n";
exit($fail > 0 ? 1 : 0);
