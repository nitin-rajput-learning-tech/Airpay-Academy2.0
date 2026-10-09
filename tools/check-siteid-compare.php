<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SITEID strict-comparison scanner - refuses a strict comparison of the
 * SITEID constant that the types alone decide.
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
 *   1. strict-compare   SITEID on either side of `===` or `!==`
 *   2. strict-call      SITEID passed to a strict-by-contract call:
 *                         in_array / array_search / array_keys with the strict flag `true`,
 *                           positional or named (`in_array(SITEID, $ids, strict: true)`)
 *                         assertSame / assertNotSame / assertContains / assertNotContains
 *   3. strict-match     SITEID as a whole match arm condition (`match ($x) { SITEID => ... }`)
 *                         or as the match subject (`match (SITEID) { 1 => ... }`):
 *                         match compares the subject with every arm using `===`
 *
 * WHICH CAST IS ENOUGH
 * --------------------
 * The cast has to be the right one for the OTHER side, because SITEID is a
 * string and a strict comparison also fails on a type mismatch the other way:
 *
 *   (int) / (integer) / intval()   Safe against an int, which is the normal case (an id cast to
 *                                  int, a PARAM_INT value). Flagged only when the other side is
 *                                  visibly a string: a (string) cast, strval() or a string literal
 *                                  (`(string) $id === (int) SITEID` is always false).
 *   (string) / strval()            Safe ONLY against another string: the other side must also be a
 *                                  (string) cast, strval() or a string literal. Against an int it is
 *                                  always false (`$courseid === (string) SITEID`), and against
 *                                  something that cannot be shown to be a string it is flagged too:
 *                                  say (int) SITEID, or cast the other side as well.
 *   anything else ((float), (bool), (array) ...) is not a cast this scanner accepts.
 *
 * "Cast" means the cast operator, or intval( )/strval( ) directly around
 * SITEID, with or without extra grouping parentheses. The other side is read
 * from how its operand BEGINS (a cast, intval( ), strval( ), a string or int
 * literal), so a variable or a property is "unknown": it passes an int cast and
 * fails a string cast. For assertSame / assertNotSame the other side is the
 * other of expected / actual; for a match arm it is the subject.
 *
 * NOT FLAGGED: SITEID used only as an array KEY ($names[SITEID], [SITEID => 'x']).
 * PHP turns the numeric string '1' into the int key 1, so the type does not
 * matter there. The scanner stops climbing at an index bracket, so a key inside
 * an assertSame() / in_array() argument is not mistaken for a compared value.
 * (An array LITERAL element, [SITEID, 2], is a compared value and is flagged.)
 *
 * Works on PHP tokens, not on text, so a comment or a string that quotes the
 * anti-pattern (this docblock does) is never flagged.
 *
 * KNOWN LIMIT: it follows the constant where it stands as the operand, not the
 * value it carries. `$s = SITEID; $x === $s` and `($a ? SITEID : 0) === $x`
 * are invisible to it. Cast at the comparison; do not stash SITEID in a
 * variable first. A match arm is judged only when SITEID is the whole arm
 * condition; `match (true) { SITEID == $x => ... }` is a loose comparison inside
 * a boolean and is not flagged.
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
/** Calls that compare strictly only when their strict argument (the third, or `strict:`) is the literal `true`. */
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
$qualname = defined('T_NAME_QUALIFIED') ? T_NAME_QUALIFIED : -1;
$nullsafe = defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1;
/** PHP 8.0+ `match`. */
$matchid = defined('T_MATCH') ? T_MATCH : -2;
$openers = [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES];
$identicalops = [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL];

/** Token ids that, in front of a `(`, make it a grouping parenthesis (an operator, a separator, return). */
$groupingops = [T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL, T_BOOLEAN_AND, T_BOOLEAN_OR,
    T_COALESCE, T_INT_CAST, T_STRING_CAST];

/** Token ids that, in front of a `[`, make it an INDEX bracket ($a[..], $a['k'][..], f()[..], Foo::BAR[..]). */
$indexprev = [T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING, $fqname, $qualname, ']', ')', '}'];

/** Token ids that end an operand on the way left from a comparison operator. */
$operandstops = [T_DOUBLE_ARROW, T_RETURN, T_ECHO, T_BOOLEAN_AND, T_BOOLEAN_OR, T_LOGICAL_AND, T_LOGICAL_OR,
    T_LOGICAL_XOR, T_COALESCE, T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL];

/**
 * Whether a token id opens a bracket group.
 *
 * @param mixed $id Token id.
 * @return bool
 */
$isopen = static function ($id) use ($openers): bool {
    return $id === '(' || $id === '[' || $id === '{' || in_array($id, $openers, true);
};

/**
 * Whether a token id closes a bracket group.
 *
 * @param mixed $id Token id.
 * @return bool
 */
$isclose = static function ($id): bool {
    return $id === ')' || $id === ']' || $id === '}';
};

/**
 * Whether a `(` that follows this token is a grouping parenthesis, as opposed to
 * the parenthesis of a call, an `if (`, a `match (` and so on.
 *
 * @param array|null $before The significant token in front of the `(`, null at the file start.
 * @return bool
 */
$isgroupingbefore = static function (?array $before) use ($groupingops): bool {
    if ($before === null) {
        return true;
    }
    $id = $before['id'];
    return $id === '(' || $id === ',' || $id === '[' || $id === T_DOUBLE_ARROW || $id === T_RETURN
        || (is_string($id) && strpos('=!<>.+-*/?:&|', $id) !== false)
        || in_array($id, $groupingops, true);
};

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
 * Work out the operand SITEID stands in: its cast, if any, and its full extent.
 *
 * @param array $sig Significant tokens.
 * @param int $i Index of SITEID.
 * @return array [?string cast type 'int'|'string'|'other'|null, int start, int end]. start..end is the
 *               whole operand: the cast operator or the intval(/strval( name through the
 *               closing token, inside any grouping parentheses that wrap it.
 */
$castinfo = static function (array $sig, int $i) use ($fqname, $isgroupingbefore): array {
    $left = $i;
    $right = $i;
    $type = null;
    $start = $i;
    $end = $i;
    // intval( SITEID ) / strval( SITEID ), and grouping parentheses around either form.
    while (($sig[$left - 1]['id'] ?? null) === '(' && ($sig[$right + 1]['id'] ?? null) === ')') {
        $fn = $sig[$left - 2] ?? null;
        if ($fn !== null && ($fn['id'] === T_STRING || $fn['id'] === $fqname)) {
            $name = strtolower(ltrim($fn['text'], '\\'));
            if ($name === 'intval' || $name === 'strval') {
                $type = $name === 'intval' ? 'int' : 'string';
                $start = $left - 2;
                $end = $right + 1;
                break;
            }
        }
        if (!$isgroupingbefore($fn)) {
            break; // Some other call or keyword: its argument, not a grouping.
        }
        $left--;
        $right++;
    }
    if ($type === null) {
        $prev = $sig[$left - 1]['id'] ?? null;
        if ($prev === T_INT_CAST) {
            $type = 'int';
        } else if ($prev === T_STRING_CAST) {
            $type = 'string';
        } else if (in_array($prev, [T_DOUBLE_CAST, T_BOOL_CAST, T_ARRAY_CAST, T_OBJECT_CAST], true)) {
            $type = 'other';
        }
        $start = $type !== null ? $left - 1 : $left;
        $end = $right;
    }
    // Grouping parentheses around the whole operand: ((int) SITEID), (intval(SITEID)).
    while (($sig[$start - 1]['id'] ?? null) === '(' && ($sig[$end + 1]['id'] ?? null) === ')'
            && $isgroupingbefore($sig[$start - 2] ?? null)) {
        $start--;
        $end++;
    }
    return [$type, $start, $end];
};

/**
 * Walk outwards from token $i to the innermost enclosing *call*, passing
 * through array literals and grouping parentheses, but NOT through an index
 * bracket: SITEID inside $names[ ] is an array key, and the type of a key does
 * not matter ('1' and 1 are the same key).
 *
 * @param array $sig Significant tokens.
 * @param int $i Index to start from.
 * @return array|null [lowercase call name, index of its '(', call name as written]
 *                    or null at a statement boundary or an index bracket.
 */
$enclosingcall = static function (array $sig, int $i) use ($openers, $fqname, $isgroupingbefore, $indexprev): ?array {
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
            if (in_array($sig[$j - 1]['id'] ?? null, $indexprev, true)) {
                return null; // Index bracket: SITEID is a key, not a compared value.
            }
            continue; // Array literal: its elements are compared by the call, keep climbing.
        }
        $before = $sig[$j - 1] ?? null;
        if ($before !== null && ($before['id'] === T_STRING || $before['id'] === $fqname)) {
            $written = ltrim($before['text'], '\\');
            return [strtolower($written), $j, $written];
        }
        if ($before !== null && $before['id'] === T_ARRAY) {
            continue; // array( ... ) literal.
        }
        if ($isgroupingbefore($before)) {
            continue; // Grouping parentheses: keep climbing.
        }
        return null; // if ( / while ( / foreach ( / match ( / a callable variable ...
    }
    return null;
};

/**
 * The index of the innermost bracket that opens in front of $i and is still open there.
 *
 * @param array $sig Significant tokens.
 * @param int $i Index to start from.
 * @return int|null Index of the opening token, or null at a statement boundary.
 */
$enclosingopen = static function (array $sig, int $i) use ($isopen, $isclose): ?int {
    $depth = 0;
    for ($j = $i - 1; $j >= 0; $j--) {
        $id = $sig[$j]['id'];
        if ($isclose($id)) {
            $depth++;
            continue;
        }
        if ($id === ';' && $depth === 0) {
            return null;
        }
        if ($isopen($id)) {
            if ($depth > 0) {
                $depth--;
                continue;
            }
            return $j;
        }
    }
    return null;
};

/**
 * Whether the operand at $start..$end is a match subject or a whole match arm condition.
 *
 * @param array $sig Significant tokens.
 * @param int $start First token of the operand.
 * @param int $end Last token of the operand.
 * @return array|null null, or ['subject' => ?int] where the value is the index of the first
 *                    token of the match subject when this operand is an ARM condition, and
 *                    null when this operand IS the subject.
 */
$matchcontext = static function (array $sig, int $start, int $end) use ($matchid, $enclosingopen): ?array {
    $prev = $sig[$start - 1]['id'] ?? null;
    $next = $sig[$end + 1]['id'] ?? null;
    // match ( SITEID ) { ... }
    if ($prev === '(' && ($sig[$start - 2]['id'] ?? null) === $matchid && $next === ')') {
        return ['subject' => null];
    }
    // An arm condition starts after the opening brace or after a comma (the next condition of
    // the list, or the first of the next arm: a comma in a match body is only ever followed by
    // a condition) and ends at a comma or the arrow. Anything after `=>` is the arm's RESULT.
    if (($prev !== '{' && $prev !== ',') || ($next !== ',' && $next !== T_DOUBLE_ARROW)) {
        return null;
    }
    $brace = $enclosingopen($sig, $start);
    if ($brace === null || $sig[$brace]['id'] !== '{' || ($sig[$brace - 1]['id'] ?? null) !== ')') {
        return null;
    }
    $depth = 0;
    for ($j = $brace - 1; $j >= 0; $j--) {
        $id = $sig[$j]['id'];
        if ($id === ')') {
            $depth++;
        } else if ($id === '(') {
            $depth--;
            if ($depth === 0) {
                if (($sig[$j - 1]['id'] ?? null) !== $matchid) {
                    return null; // A block after some other parenthesis: function () { ... }, if () { ... }.
                }
                return ['subject' => $j + 1];
            }
        }
    }
    return null;
};

/**
 * What type the operand that BEGINS at $p is visibly cast to.
 *
 * @param array $sig Significant tokens.
 * @param int $p Index of the first token of the operand.
 * @return string|null 'int', 'string', or null when the operand does not say.
 */
$optype = static function (array $sig, int $p) use ($fqname): ?string {
    while (($sig[$p]['id'] ?? null) === '(') {
        $p++; // Grouping parentheses.
    }
    $t = $sig[$p] ?? null;
    if ($t === null) {
        return null;
    }
    if ($t['id'] === T_INT_CAST || $t['id'] === T_LNUMBER) {
        return 'int';
    }
    if ($t['id'] === T_STRING_CAST || $t['id'] === T_CONSTANT_ENCAPSED_STRING) {
        return 'string';
    }
    if (($t['id'] === T_STRING || $t['id'] === $fqname) && ($sig[$p + 1]['id'] ?? null) === '(') {
        $name = strtolower(ltrim($t['text'], '\\'));
        return $name === 'intval' ? 'int' : ($name === 'strval' ? 'string' : null);
    }
    return null;
};

/**
 * Index of the first token of the operand that ENDS at $p, walking left over balanced brackets
 * to the nearest operator or separator of lower precedence than a comparison.
 *
 * @param array $sig Significant tokens.
 * @param int $p Index of the operand's last token.
 * @return int
 */
$operandstart = static function (array $sig, int $p) use ($isopen, $isclose, $operandstops): int {
    $depth = 0;
    for ($j = $p; $j >= 0; $j--) {
        $id = $sig[$j]['id'];
        if ($isclose($id)) {
            $depth++;
            continue;
        }
        if ($isopen($id)) {
            if ($depth === 0) {
                return $j + 1;
            }
            $depth--;
            continue;
        }
        if ($depth === 0 && ($id === ',' || $id === ';' || $id === '=' || $id === '?' || $id === ':'
                || $id === '&' || $id === '|' || $id === '^' || in_array($id, $operandstops, true))) {
            return $j + 1;
        }
    }
    return 0;
};

/**
 * The visible type of the operand on the far side of the comparison operator at $opidx.
 *
 * @param array $sig Significant tokens.
 * @param int $opidx Index of the === / !== token.
 * @param int $dir +1 when the other operand is to the right of the operator, -1 to the left.
 * @return string|null 'int', 'string', or null when it does not say.
 */
$otherside = static function (array $sig, int $opidx, int $dir) use ($operandstart, $optype): ?string {
    return $optype($sig, $dir > 0 ? $opidx + 1 : $operandstart($sig, $opidx - 1));
};

/**
 * Why a cast on SITEID is wrong for the other side of a strict comparison.
 *
 * @param string|null $type 'int', 'string' or 'other' (the cast on SITEID).
 * @param string|null $other 'int', 'string' or null (the other side).
 * @return string|null The reason, or null when the pair is fine.
 */
$clash = static function (?string $type, ?string $other): ?string {
    if ($type === 'other') {
        return 'SITEID is cast to a type that is neither int nor string ((float), (bool), (array), (object)), '
            . 'which a strict comparison can never match. Write (int) SITEID.';
    }
    if ($type === 'int' && $other === 'string') {
        return 'SITEID is cast to int but the other side is a string, so the strict comparison is '
            . 'always false. Use the same cast on both sides.';
    }
    if ($type === 'string' && $other !== 'string') {
        return $other === 'int'
            ? '(string) SITEID against an int is always false under strict comparison. Cast SITEID to '
                . 'int, or cast the other side to string as well.'
            : '(string) SITEID is only right against another string, and the other side is not shown '
                . 'to be one. Write (int) SITEID against an int, or cast the other side to (string) too.';
    }
    return null;
};

/**
 * Split the arguments of the call whose '(' is at $open.
 *
 * @param array $sig Significant tokens.
 * @param int $open Index of the call's '('.
 * @return array[] One ['from' => int, 'to' => int, 'name' => ?string] per argument; from..to is
 *                 the value (after a `name:` prefix), name is the lowercased argument name of a
 *                 PHP 8 named argument, else null.
 */
$callargs = static function (array $sig, int $open) use ($isopen, $isclose): array {
    $depth = 0;
    $argstart = $open + 1;
    $ranges = [];
    $n = count($sig);
    for ($j = $open + 1; $j < $n; $j++) {
        $id = $sig[$j]['id'];
        if ($isopen($id)) {
            $depth++;
        } else if ($isclose($id)) {
            if ($depth === 0) {
                if ($j > $argstart) {
                    $ranges[] = [$argstart, $j - 1];
                }
                break;
            }
            $depth--;
        } else if ($id === ',' && $depth === 0) {
            $ranges[] = [$argstart, $j - 1];
            $argstart = $j + 1;
        }
    }
    $args = [];
    foreach ($ranges as [$from, $to]) {
        $name = null;
        if ($to - $from >= 2 && $sig[$from]['id'] === T_STRING && ($sig[$from + 1]['id'] ?? null) === ':') {
            $name = strtolower($sig[$from]['text']);
            $from += 2;
        }
        $args[] = ['from' => $from, 'to' => $to, 'name' => $name];
    }
    return $args;
};

/**
 * Whether the call passes the literal `true` as its strict argument: the third positional
 * argument, or the named argument `strict:`.
 *
 * @param array $sig Significant tokens.
 * @param array[] $args From $callargs.
 * @return bool
 */
$strictflag = static function (array $sig, array $args): bool {
    $pos = 0;
    foreach ($args as $a) {
        $istrue = $a['from'] === $a['to'] && $sig[$a['from']]['id'] === T_STRING
            && strtolower($sig[$a['from']]['text']) === 'true';
        if ($a['name'] !== null) {
            if ($a['name'] === 'strict') {
                return $istrue;
            }
            continue;
        }
        if ($pos === 2) {
            return $istrue;
        }
        $pos++;
    }
    return false;
};

/**
 * For assertSame / assertNotSame: the visible type of the other of expected / actual, when the
 * operand at $start..$end is a whole argument in one of those two slots.
 *
 * @param array $sig Significant tokens.
 * @param array[] $args From $callargs.
 * @param int $start First token of the SITEID operand.
 * @param int $end Last token of the SITEID operand.
 * @return string|null 'int', 'string', or null when it does not say.
 */
$assertother = static function (array $sig, array $args, int $start, int $end) use ($optype): ?string {
    $slots = [];
    $pos = 0;
    foreach ($args as $k => $a) {
        if ($a['name'] === 'expected') {
            $slots[$k] = 0;
        } else if ($a['name'] === 'actual') {
            $slots[$k] = 1;
        } else if ($a['name'] !== null) {
            $slots[$k] = -1;
        } else {
            $slots[$k] = $pos++;
        }
    }
    foreach ($args as $k => $a) {
        if ($a['from'] !== $start || $a['to'] !== $end || ($slots[$k] !== 0 && $slots[$k] !== 1)) {
            continue;
        }
        foreach ($args as $m => $b) {
            if ($slots[$m] === 1 - $slots[$k]) {
                return $optype($sig, $b['from']);
            }
        }
    }
    return null;
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

        [$type, $start, $end] = $castinfo($sig, $i);
        $code = trim($lines[$lineno - 1] ?? '');
        $before = $sig[$start - 1]['id'] ?? null;
        $after = $sig[$end + 1]['id'] ?? null;

        // ── Rule 1: SITEID beside === or !== ──────────────────────────────
        $leftop = in_array($before, $identicalops, true);
        $rightop = in_array($after, $identicalops, true);
        if ($leftop || $rightop) {
            if ($type === null) {
                $findings[] = [$file, $lineno, 'strict-compare',
                    'SITEID is the database string (e.g. \'1\'), so a strict comparison with an int is '
                    . 'always the same answer and the site-course guard silently does nothing. '
                    . 'Write (int) SITEID.', $code];
            } else {
                $reason = $clash($type,
                    $leftop ? $otherside($sig, $start - 1, -1) : $otherside($sig, $end + 1, 1));
                if ($reason !== null) {
                    $findings[] = [$file, $lineno, 'strict-compare', $reason, $code];
                }
            }
            continue;
        }

        // ── Rule 3: SITEID as a match subject or a whole arm condition ────
        $match = $matchcontext($sig, $start, $end);
        if ($match !== null) {
            if ($type === null) {
                $findings[] = [$file, $lineno, 'strict-match', $match['subject'] === null
                    ? 'match compares its subject with every arm using ===, and SITEID is the database '
                        . 'string (e.g. \'1\'): an int arm (1 => ...) can never match. Write (int) SITEID.'
                    : 'match compares its subject with each arm condition using ===, and SITEID is the '
                        . 'database string (e.g. \'1\'): against an int subject this arm can never match. '
                        . 'Write (int) SITEID.', $code];
            } else {
                $reason = $clash($type, $match['subject'] === null ? null : $optype($sig, $match['subject']));
                if ($reason !== null) {
                    $findings[] = [$file, $lineno, 'strict-match', 'match compares with ===. ' . $reason, $code];
                }
            }
            continue;
        }

        // An array-literal key ([SITEID => 'x']): PHP normalises '1' to the int key 1.
        if ($after === T_DOUBLE_ARROW) {
            continue;
        }

        // ── Rule 2: SITEID handed to a strict-by-contract call ────────────
        $call = $enclosingcall($sig, $start);
        if ($call === null) {
            continue;
        }
        [$name, $open, $written] = $call;
        $isassert = in_array($name, $strictassert, true);
        if (!$isassert && !in_array($name, $strictflagged, true)) {
            continue;
        }
        $args = $callargs($sig, $open);
        if (!$isassert && !$strictflag($sig, $args)) {
            continue;
        }
        if ($type === null) {
            $findings[] = [$file, $lineno, 'strict-call',
                "SITEID is the database string, but {$written}() compares strictly here, so an int on the "
                . 'other side can never match. Cast one side: (int) SITEID.', $code];
        } else {
            $other = in_array($name, ['assertsame', 'assertnotsame'], true)
                ? $assertother($sig, $args, $start, $end) : null;
            $reason = $clash($type, $other);
            if ($reason !== null) {
                $findings[] = [$file, $lineno, 'strict-call', "{$written}() compares strictly here. " . $reason, $code];
            }
        }
    }
}

// ── Report ────────────────────────────────────────────────────────────────
if (!$findings) {
    if (!$quiet) {
        fwrite(STDOUT, sprintf(
            "OK  siteid-compare: %d file(s) scanned, no strict comparison the types alone decide.\n",
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
