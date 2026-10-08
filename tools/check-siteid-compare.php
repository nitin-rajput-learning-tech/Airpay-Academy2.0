<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SITEID strict-comparison scanner - refuses `=== SITEID` / `!== SITEID`
 * against an un-cast constant.
 *
 * WHY THIS EXISTS
 * ---------------
 * Moodle defines the constant from the database row (lib/setup.php):
 *
 *     define('SITEID', $SITE->id);
 *
 * and MySQL/MariaDB hand every integer column back as a STRING, so SITEID is
 * the string '1', never the int 1. A strict comparison against an int is
 * therefore decided by the types alone, whatever the values are:
 *
 *     (int) $course->id !== SITEID        always true   (the guard never fires)
 *     $courseid === SITEID                always false  (the guard never matches)
 *
 * Nothing fails. The guard that was meant to keep the front page out simply
 * does nothing, and the platform carries on with the wrong behaviour. It
 * shipped five times before one full PHPUnit run found it (commit 0eb31f32f):
 *
 *   local_sentientia_emails   parity_senders::course_enrolled  e-mailed an enrolment on the site course
 *   local_sentientia_aiquiz   quiz_publisher                   did not skip the site course
 *   local_sentientia_aiquiz   generate.php                     did not refuse the site course as draft course
 *   local_sentientia_users    bizlms transcript_step           the importer would not skip site-course rows
 *   theme_sentientia          language_switcher                front page not treated as the site course
 *
 * The fix is to say which type you mean, once, at the comparison:
 *
 *     (int) $course->id !== (int) SITEID
 *
 * A loose `==` / `!=` / `<>` / `<=` is not affected (PHP compares '1' with 1
 * numerically), and neither is SITEID passed as a SQL parameter, so those are
 * not flagged.
 *
 * WHAT IT FLAGS
 * -------------
 *   1. strict-compare   SITEID on either side of `===` or `!==` without a cast
 *   2. strict-call      SITEID passed to a strict-by-contract call:
 *                         in_array / array_search / array_keys with the strict flag `true`
 *                         assertSame / assertNotSame / assertContains / assertNotContains
 *
 * "Cast" means (int), (integer), (string), intval( ) or strval( ) directly
 * around SITEID, with or without extra parentheses. A (string) cast is
 * accepted because comparing it with a database string is deterministic.
 *
 * Works on PHP tokens, not on text, so a comment or a string that quotes the
 * anti-pattern (this docblock does) is never flagged.
 *
 * KNOWN LIMIT: it follows the constant where it stands as the operand, not the
 * value it carries. `$s = SITEID; $x === $s` and `($a ? SITEID : 0) === $x`
 * are invisible to it. Cast at the comparison; do not stash SITEID in a
 * variable first.
 *
 * SCOPE
 * -----
 * The Sentientia trees only (so a core Moodle file touched under an ADR is not
 * this scanner's business): local/, moodle-enhancement/local/,
 * blocks/sentientia_*, moodle-enhancement/blocks/, theme/sentientia,
 * payment/gateway/airpay, enrol/sentientiasub,
 * mod/quiz/accessrule/sentientia_proctoring and their moodle-enhancement twins.
 * Vendored code (blocks/learnerscript, vendor/, node_modules/) is skipped.
 *
 * USAGE
 * -----
 *   php tools/check-siteid-compare.php                    whole scope, human output
 *   php tools/check-siteid-compare.php --quiet            FAIL lines only, for CI
 *   php tools/check-siteid-compare.php --root=DIR         scan another tree laid out like the repo
 *   git diff --cached --name-only --diff-filter=ACM \
 *       | php tools/check-siteid-compare.php --stdin      only these files (pre-commit)
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
 *     // siteid-compare-ok: both sides are database strings, compared as strings
 *
 * @package tools
 */

$opts = getopt('', ['stdin', 'quiet', 'help', 'root:']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "Usage: php tools/check-siteid-compare.php [--quiet] [--root=DIR]\n"
        . "       git diff --cached --name-only --diff-filter=ACM | php tools/check-siteid-compare.php --stdin\n");
    exit(0);
}
$fromstdin = isset($opts['stdin']);
$quiet     = isset($opts['quiet']);

$root = isset($opts['root']) && $opts['root'] !== false ? (string) $opts['root'] : dirname(__DIR__);
if (!is_dir($root)) {
    fwrite(STDERR, "check-siteid-compare: --root is not a directory: {$root}\n");
    exit(2);
}
chdir($root);

/** Path prefixes (relative to the root) whose PHP is policed. */
$scopeprefixes = [
    'local/',
    'moodle-enhancement/local/',
    'blocks/sentientia_',
    'moodle-enhancement/blocks/',
    'theme/sentientia/',
    'payment/gateway/airpay/',
    'enrol/sentientiasub/',
    'mod/quiz/accessrule/sentientia_proctoring/',
    'moodle-enhancement/payment/gateway/airpay/',
    'moodle-enhancement/enrol/sentientiasub/',
    'moodle-enhancement/mod/quiz/accessrule/sentientia_proctoring/',
];

/** Never scan: vendored or third-party code. */
$skipre = '#(/vendor/|/node_modules/|/learnerscript/|/_stale)#';

/** Calls that compare with `===` semantics by contract, whatever their arguments. */
$strictassert = ['assertsame', 'assertnotsame', 'assertcontains', 'assertnotcontains'];
/** Calls that compare strictly only when their last argument is the literal `true`. */
$strictflagged = ['in_array', 'array_search', 'array_keys'];

/**
 * Whether a root-relative path belongs to the policed trees.
 *
 * @param string $path Forward-slash path relative to the root.
 * @return bool
 */
$inscope = static function (string $path) use ($scopeprefixes, $skipre): bool {
    if (preg_match($skipre, '/' . $path)) {
        return false;
    }
    foreach ($scopeprefixes as $prefix) {
        if (strncmp($path, $prefix, strlen($prefix)) === 0) {
            return true;
        }
    }
    return false;
};

$files = [];
if ($fromstdin) {
    while (($line = fgets(STDIN)) !== false) {
        $f = str_replace('\\', '/', trim($line));
        if ($f === '' || substr($f, -4) !== '.php') {
            continue;
        }
        if (is_file($f) && $inscope($f)) {
            $files[] = $f;
        }
    }
} else {
    $scandirs = ['local', 'moodle-enhancement/local', 'moodle-enhancement/blocks', 'theme/sentientia',
        'payment/gateway/airpay', 'enrol/sentientiasub', 'mod/quiz/accessrule/sentientia_proctoring',
        'moodle-enhancement/payment/gateway/airpay', 'moodle-enhancement/enrol/sentientiasub',
        'moodle-enhancement/mod/quiz/accessrule/sentientia_proctoring'];
    foreach ((array) glob('blocks/sentientia_*', GLOB_ONLYDIR) as $dir) {
        $scandirs[] = $dir;
    }
    foreach ($scandirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $p = str_replace('\\', '/', $f->getPathname());
            if ($f->isFile() && substr($p, -4) === '.php' && $inscope($p)) {
                $files[] = $p;
            }
        }
    }
}

// ── Token helpers ─────────────────────────────────────────────────────────

/** PHP 8 lexes \Name as one T_NAME_FULLY_QUALIFIED token; earlier versions have no such token. */
$fqname = defined('T_NAME_FULLY_QUALIFIED') ? T_NAME_FULLY_QUALIFIED : -1;
$nullsafe = defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1;
$openers = [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES];

/**
 * Whether a significant token is the SITEID constant itself.
 *
 * @param array $sig Significant tokens.
 * @param int $i Index of the token.
 * @return bool
 */
$issiteid = static function (array $sig, int $i) use ($fqname, $nullsafe): bool {
    $t = $sig[$i];
    $isname = ($t['id'] === T_STRING && $t['text'] === 'SITEID')
        || ($t['id'] === $fqname && $t['text'] === '\\SITEID');
    if (!$isname) {
        return false;
    }
    // ->SITEID, ?->SITEID, Foo::SITEID, const SITEID, function SITEID are not the constant.
    $prev = $sig[$i - 1]['id'] ?? null;
    if ($prev === T_OBJECT_OPERATOR || $prev === $nullsafe || $prev === T_DOUBLE_COLON
            || $prev === T_CONST || $prev === T_FUNCTION) {
        return false;
    }
    // SITEID( ) is a call, SITEID:: is a class reference.
    $next = $sig[$i + 1]['id'] ?? null;
    return $next !== '(' && $next !== T_DOUBLE_COLON;
};

/**
 * Whether the SITEID at $i is explicitly cast, looking through extra parentheses.
 *
 * @param array $sig Significant tokens.
 * @param int $i Index of SITEID.
 * @return array [bool iscast, int left, int right] where left/right bound the operand.
 */
$castinfo = static function (array $sig, int $i) use ($fqname): array {
    $left = $i;
    $right = $i;
    // intval( SITEID ) / strval( SITEID ), and grouping parentheses around either form.
    while (($sig[$left - 1]['id'] ?? null) === '(' && ($sig[$right + 1]['id'] ?? null) === ')') {
        $fn = $sig[$left - 2] ?? null;
        if ($fn !== null && ($fn['id'] === T_STRING || $fn['id'] === $fqname)
                && in_array(strtolower(ltrim($fn['text'], '\\')), ['intval', 'strval'], true)) {
            return [true, $left - 2, $right + 1];
        }
        if ($fn !== null && in_array($fn['id'], [T_STRING, $fqname, T_VARIABLE], true)) {
            break; // Some other call: its argument, not a grouping.
        }
        $left--;
        $right++;
    }
    $prev = $sig[$left - 1]['id'] ?? null;
    $iscast = ($prev === T_INT_CAST || $prev === T_STRING_CAST);
    return [$iscast, $left, $right];
};

/**
 * Walk outwards from token $i to the innermost enclosing *call*, passing
 * through array literals, index brackets and grouping parentheses.
 *
 * @param array $sig Significant tokens.
 * @param int $i Index to start from.
 * @return array|null [lowercase call name, index of its '(', call name as written]
 *                    or null at a statement boundary.
 */
$enclosingcall = static function (array $sig, int $i) use ($openers, $fqname): ?array {
    $depth = 0;
    for ($j = $i - 1; $j >= 0; $j--) {
        $id = $sig[$j]['id'];
        if ($id === ')' || $id === ']' || $id === '}') {
            $depth++;
            continue;
        }
        if ($id === ';' && $depth === 0) {
            return null;
        }
        $isopen = ($id === '(' || $id === '[' || $id === '{' || in_array($id, $openers, true));
        if (!$isopen) {
            continue;
        }
        if ($depth > 0) {
            $depth--;
            continue;
        }
        if ($id === '{' || in_array($id, $openers, true)) {
            return null; // A block or interpolation boundary.
        }
        if ($id === '[') {
            continue; // Array literal or index: keep climbing.
        }
        $before = $sig[$j - 1] ?? null;
        if ($before !== null && ($before['id'] === T_STRING || $before['id'] === $fqname)) {
            $written = ltrim($before['text'], '\\');
            return [strtolower($written), $j, $written];
        }
        if ($before !== null && $before['id'] === T_ARRAY) {
            continue; // array( ... ) literal.
        }
        if ($before === null || $before['id'] === '(' || $before['id'] === ',' || $before['id'] === '['
                || $before['id'] === T_DOUBLE_ARROW || $before['id'] === T_RETURN
                || (is_string($before['id']) && strpos('=!<>.+-*/?:&|', $before['id']) !== false)
                || in_array($before['id'], [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL,
                    T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE, T_INT_CAST, T_STRING_CAST], true)) {
            continue; // Grouping parentheses: keep climbing.
        }
        return null; // if ( / while ( / foreach ( / a callable variable ...
    }
    return null;
};

/**
 * Whether the call opened at $open passes the literal `true` as its last argument.
 *
 * @param array $sig Significant tokens.
 * @param int $open Index of the call's '('.
 * @return bool
 */
$laststrictarg = static function (array $sig, int $open) use ($openers): bool {
    $depth = 0;
    $argstart = $open + 1;
    $args = [];
    $n = count($sig);
    for ($j = $open + 1; $j < $n; $j++) {
        $id = $sig[$j]['id'];
        if ($id === '(' || $id === '[' || $id === '{' || in_array($id, $openers, true)) {
            $depth++;
        } else if ($id === ')' || $id === ']' || $id === '}') {
            if ($depth === 0) {
                if ($j > $argstart) {
                    $args[] = [$argstart, $j - 1];
                }
                break;
            }
            $depth--;
        } else if ($id === ',' && $depth === 0) {
            $args[] = [$argstart, $j - 1];
            $argstart = $j + 1;
        }
    }
    if (count($args) < 3) {
        return false; // The strict flag is the third argument.
    }
    [$from, $to] = $args[count($args) - 1];
    return $from === $to && $sig[$from]['id'] === T_STRING && strtolower($sig[$from]['text']) === 'true';
};

$findings = [];

foreach ($files as $file) {
    $src = @file_get_contents($file);
    if ($src === false || strpos($src, 'SITEID') === false) {
        continue; // Cheap pre-filter: most files never mention the constant.
    }
    $lines = preg_split('/\r\n|\r|\n/', $src);

    $sig = [];
    foreach (token_get_all($src) as $tok) {
        if (is_array($tok)) {
            if (in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_INLINE_HTML], true)) {
                continue;
            }
            $sig[] = ['id' => $tok[0], 'text' => $tok[1], 'line' => $tok[2]];
        } else {
            $sig[] = ['id' => $tok, 'text' => $tok, 'line' => end($sig)['line'] ?? 1];
        }
    }

    foreach ($sig as $i => $t) {
        if (!$issiteid($sig, $i)) {
            continue;
        }
        $lineno = $t['line'];

        // Suppression marker on this line or the one above.
        $here = $lines[$lineno - 1] ?? '';
        $above = $lines[$lineno - 2] ?? '';
        if (strpos($here, 'siteid-compare-ok') !== false || strpos($above, 'siteid-compare-ok') !== false) {
            continue;
        }

        [$iscast, $left, $right] = $castinfo($sig, $i);
        if ($iscast) {
            continue;
        }

        $code = trim($lines[$lineno - 1] ?? '');

        // ── Rule 1: SITEID beside === or !== ──────────────────────────────
        $before = $sig[$left - 1]['id'] ?? null;
        $after = $sig[$right + 1]['id'] ?? null;
        if ($before === T_IS_IDENTICAL || $before === T_IS_NOT_IDENTICAL
                || $after === T_IS_IDENTICAL || $after === T_IS_NOT_IDENTICAL) {
            $findings[] = [$file, $lineno, 'strict-compare',
                'SITEID is the database string (e.g. \'1\'), so a strict comparison with an int is '
                . 'always the same answer and the site-course guard silently does nothing. '
                . 'Write (int) SITEID.', $code];
            continue;
        }

        // ── Rule 2: SITEID handed to a strict-by-contract call ────────────
        $call = $enclosingcall($sig, $left);
        if ($call === null) {
            continue;
        }
        [$name, $open, $written] = $call;
        if (in_array($name, $strictassert, true)
                || (in_array($name, $strictflagged, true) && $laststrictarg($sig, $open))) {
            $findings[] = [$file, $lineno, 'strict-call',
                "SITEID is the database string, but {$written}() compares strictly here, so an int on the "
                . 'other side can never match. Cast one side: (int) SITEID.', $code];
        }
    }
}

// ── Report ────────────────────────────────────────────────────────────────
if (!$findings) {
    if (!$quiet) {
        fwrite(STDOUT, sprintf(
            "OK  siteid-compare: %d file(s) scanned, no strict comparison against an un-cast SITEID.\n",
            count($files)));
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
    fwrite(STDOUT, "Fix: compare (int) \$x === (int) SITEID. SITEID comes from \$SITE->id, which the "
        . "database returns as a string.\n");
    fwrite(STDOUT, "A genuinely-correct exception needs an inline "
        . "`siteid-compare-ok: <reason>` marker.\n");
}
exit(1);
