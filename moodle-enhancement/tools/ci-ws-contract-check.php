<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Standalone WS-contract gate for CI: no Moodle bootstrap required.
 *
 * Background (ADR-009, Bug #6 / #10 / #12, 2026-05-22 audit):
 * the shared theme_sentientia/datatable AMD client
 * (theme/sentientia/amd/src/datatable.js) mounts every element matching
 * `[data-airpay-table], [data-region="airpay-datatable"]` and POSTs
 * {search, sort, sortdir, page, perpage, filters} to the WS named in its
 * data-ws-name. Moodle's strict external_function_parameters validator
 * rejects unknown keys, so every consumer WS must declare all 6 with
 * VALUE_DEFAULT.
 *
 * The Moodle-aware audit is theme_sentientia\ws_contract_scanner
 * (theme/sentientia/classes/ws_contract_scanner.php), run by
 *   - theme/sentientia/tests/ws_contract_test.php  (PHPUnit)
 *   - theme/sentientia/cli/ws_contract_audit.php   (CLI smoke)
 * Both need a bootstrapped Moodle: the scanner calls class_exists() and
 * executes <method>_parameters(). GitHub Actions has no Moodle install
 * (5-15 min per run to build one), so this script applies the SAME rules
 * by static analysis of the repo. It is a port of that class, not a
 * separate design; when the class changes, this file must follow.
 *
 * Rules mirrored from theme_sentientia\ws_contract_scanner:
 *   1. REQUIRED_CONTRACT_KEYS and PLUGIN_PREFIXES are READ from the
 *      canonical file (token-parsed, never hand-copied). The other rule
 *      literals (regexes, window, method-name default, glob) are checked
 *      against the canonical source; any mismatch exits 2.
 *   2. Walk every *.mustache under <dirroot>/local recursively; keep a
 *      file when its '/'-normalised path contains '/local/<prefix>'.
 *   3. For every data-region="airpay-datatable" hit (case-insensitive),
 *      take the window substr($content, max(0, $offset - 400), 800) and
 *      the FIRST data-ws-name="<[a-z0-9_]+>" in it. Hits without one are
 *      ignored. Result: ws name => source templates (deduplicated).
 *   4. Resolve the ws name through glob(<dirroot>/local/<prefix>*
 *      /db/services.php), prefixes in order: the first $functions entry
 *      with a non-empty 'classname' wins; methodname defaults to 'execute'.
 *   5. Locate the class the way Moodle's autoloader does
 *      (<component>\a\b -> <plugin dir>/classes/a/b.php, exact case; a
 *      plugin dir needs version.php) and check the file declares it.
 *   6. Find <methodname>_parameters() in that class and read the top-level
 *      keys of the array literal passed to `new external_function_parameters`
 *      (= array_keys($params->keys) in the Moodle scanner). Missing
 *      required keys, compared strictly, are a FAILURE.
 *
 * Where the Moodle scanner returns null (WS not in services.php, class not
 * autoloadable, method missing, private or not static, so the call
 * throws), the WS is SKIPPED, as there. Unlike the Moodle scanner, which
 * lets a skip pass because on a site it can mean "plugin disabled", this
 * gate FAILS on a skip (exit 1). In CI the whole tree is checked out, so a
 * plugin cannot be disabled: every skip is a datatable that can never load
 * (a typo in data-ws-name, a class file whose case differs on Linux, a
 * method that is protected or not static, a plugin without version.php),
 * which is the Bug #6 "stuck on Loading" class this gate exists to stop.
 * `--allow-skips` restores the Moodle behaviour for runs on partial trees;
 * a tree whose consumers are then ALL skipped exits 2 ("zero consumers
 * verified"), so the gate cannot pass without checking anything.
 *
 * Where static analysis cannot reproduce what Moodle would compute (keys
 * not a plain array literal, computed or spread keys, method perhaps
 * inherited or from a trait, class in core or in a component outside the
 * scanned tree, PHP parse error), the WS is UNVERIFIABLE and the gate
 * FAILS (exit 1): a consumer must never pass only because this script
 * could not read it. The old regex check failed such cases too.
 *
 * Trees: by default the gate audits TWO trees, one after the other, reports
 * each separately and exits with the worse of the two codes:
 *   - local/                     <dirroot> = repo root
 *   - moodle-enhancement/local/  <dirroot> = moodle-enhancement
 * Each tree is a $CFG->dirroot/local as the Moodle scanner would see it on a
 * site deployed from that tree: tools/uat/deploy_to_uat.sh maps both trees
 * onto public/<rel>, and UAT runs the moodle-enhancement copy. Scanning only
 * local/ was not enough: tools/check-tree-drift.php compares only plugins
 * that exist in BOTH trees (and accepts a baseline of known differences),
 * so a plugin that exists in one tree only was never checked. On 2026-11-16
 * sentientia_api, sentientia_content_market and sentientia_talent existed
 * only in moodle-enhancement/local. A consumer present in both trees is
 * reported once per tree, on purpose: each copy is deployable.
 * Each tree resolves services.php and class files inside itself. The one
 * exception is a class whose plugin is not in the moodle-enhancement tree
 * at all (for example a theme_* class): that tree is deployed over the repo
 * root, so the class is looked up there next. `--dirroot=PATH` audits one
 * tree only (a PATH inside the repo gets the same repo-root lookup; a PATH
 * outside it, such as a scratch fixture, is audited strictly on its own).
 *
 * Keys source: the canonical file is mandatory (exit 2 when it is missing
 * or its constants cannot be parsed). The hardcoded fallback list of the
 * old script (for runs outside a full checkout) is kept, but only with an
 * explicit --allow-fallback-keys, and it warns on STDERR. Until 2026-11-16
 * that fallback was silent and always taken: the old canonical path
 * (theme/airpayux) had been retired.
 *
 * No DB, no Moodle, PHP 8.2+ with the tokenizer extension (on by default).
 *
 * Usage (from anywhere; relative --dirroot is taken from the repo root):
 *   php moodle-enhancement/tools/ci-ws-contract-check.php
 *   php moodle-enhancement/tools/ci-ws-contract-check.php --verbose
 *   php moodle-enhancement/tools/ci-ws-contract-check.php --json
 *   php moodle-enhancement/tools/ci-ws-contract-check.php --dirroot=moodle-enhancement
 *   php moodle-enhancement/tools/ci-ws-contract-check.php --dirroot=/path/to/fixture --allow-skips
 *   php moodle-enhancement/tools/ci-ws-contract-check.php --allow-fallback-keys
 *
 * Exit codes (theme/sentientia/cli/ws_contract_audit.php uses 0/1/2 too),
 * per tree; the run exits with the highest tree code:
 *   0   All contracts satisfied
 *   1   One or more failures (missing keys, a consumer not verifiable, or
 *       a skipped consumer unless --allow-skips)
 *   2   Zero consumers found, zero consumers verified (all skipped under
 *       --allow-skips), or the gate is misconfigured (canonical file
 *       missing or changed, a tree without local/, services.php not
 *       loadable, bad option)
 *   3   Not run from the CLI
 *
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

// CLI guard: refuse to run via HTTP in case the folder is ever web-served.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(3);
}

// db/services.php files start with `defined('MOODLE_INTERNAL') || die();`.
// We only read their $functions array, so stub constants are enough.
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}
if (!defined('MOODLE_OFFICIAL_MOBILE_SERVICE')) {
    define('MOODLE_OFFICIAL_MOBILE_SERVICE', 'moodle_mobile_app');
}

const EXIT_OK = 0;
const EXIT_FAILURES = 1;
const EXIT_CONFIG = 2;

/** Canonical Moodle-aware scanner, relative to the repo root. */
const CANONICAL_SCANNER = 'theme/sentientia/classes/ws_contract_scanner.php';

/** The second plugin tree audited by default, relative to the repo root. */
const SECOND_TREE = 'moodle-enhancement';

/** Used only with --allow-fallback-keys when the canonical file is absent. */
const FALLBACK_REQUIRED_KEYS = ['search', 'sort', 'sortdir', 'page', 'perpage', 'filters'];
const FALLBACK_PLUGIN_PREFIXES = ['sentientia_', 'airpay_'];

// Rule literals of theme_sentientia\ws_contract_scanner. They are not
// constants there, so they are copied here and RULE_FINGERPRINTS proves the
// canonical source still contains them.
const REGION_REGEX = '/data-region\s*=\s*"airpay-datatable"/i';
const WSNAME_REGEX = '/data-ws-name\s*=\s*"([a-z0-9_]+)"/i';
const WINDOW_BACK = 400;
const WINDOW_LENGTH = 800;
const DEFAULT_METHODNAME = 'execute';
const PARAMS_SUFFIX = '_parameters';

/**
 * Code fragments that must appear in the canonical scanner (compared with
 * all whitespace removed). If one is gone, the Moodle-aware rules changed
 * and this port is stale: exit 2 rather than check something else.
 */
const RULE_FINGERPRINTS = [
    'walk root'             => 'self::find_files($CFG->dirroot . \'/local\', \'.mustache\')',
    'plugin prefix filter'  => 'str_contains($normalised, \'/local/\' . $prefix)',
    'data-region regex'     => 'preg_match_all(\'' . REGION_REGEX . '\', $content, $hits, PREG_OFFSET_CAPTURE)',
    'data-ws-name window'   => 'substr($content, max(0, $offset - ' . WINDOW_BACK . '), ' . WINDOW_LENGTH . ')',
    'data-ws-name regex'    => 'preg_match(\'' . WSNAME_REGEX . '\', $window, $wsm)',
    'services.php glob'     => 'glob($CFG->dirroot . \'/local/\' . $prefix . \'*/db/services.php\')',
    'classname required'    => '!empty($functions[$wsname][\'classname\'])',
    'methodname default'    => '$functions[$wsname][\'methodname\'] ?? \'' . DEFAULT_METHODNAME . '\'',
    'class must load'       => 'if (!class_exists($classname))',
    'params method name'    => '$paramsmethod = $methodname . \'' . PARAMS_SUFFIX . '\'',
    'method must exist'     => 'method_exists($classname, $paramsmethod)',
    'params type check'     => '$params instanceof \core_external\external_function_parameters',
    'declared keys'         => '$declared = array_keys($params->keys)',
    'strict key compare'    => 'in_array($key, $declared, true)',
];

/** Frankenstyle plugin type => folder under dirroot (Moodle's plugintypes). */
const COMPONENT_DIRS = [
    'local'      => 'local',
    'theme'      => 'theme',
    'block'      => 'blocks',
    'mod'        => 'mod',
    'tool'       => 'admin/tool',
    'enrol'      => 'enrol',
    'paygw'      => 'payment/gateway',
    'quizaccess' => 'mod/quiz/accessrule',
    'auth'       => 'auth',
    'report'     => 'report',
    'format'     => 'course/format',
    'filter'     => 'filter',
    'qtype'      => 'question/type',
    'repository' => 'repository',
];

/** The gate cannot run as configured: exit 2. */
final class gate_config_error extends RuntimeException {
}

// ──────────────────────────────────────────────────────────────────
// Canonical rules
// ──────────────────────────────────────────────────────────────────

/**
 * Read REQUIRED_CONTRACT_KEYS + PLUGIN_PREFIXES from the canonical scanner
 * and verify its rule fingerprints.
 *
 * @return array{source:string, required_keys:list<string>, plugin_prefixes:list<string>}
 */
function load_canonical_rules(string $reporoot, bool $allowfallback): array {
    $canonical = $reporoot . '/' . CANONICAL_SCANNER;
    if (!is_file($canonical)) {
        if (!$allowfallback) {
            throw new gate_config_error(
                'Canonical scanner not found: ' . CANONICAL_SCANNER . "\n"
                . 'The required keys and plugin prefixes are read from it. If the class moved, update'
                . ' CANONICAL_SCANNER in this script. To run outside a full checkout anyway, pass'
                . ' --allow-fallback-keys (uses a hardcoded copy, with a warning).');
        }
        fwrite(STDERR, "WARNING: " . CANONICAL_SCANNER . " not found.\n"
            . "WARNING: --allow-fallback-keys: using the HARDCODED key list and plugin prefixes;\n"
            . "WARNING: the rule fingerprints are NOT checked. Do not rely on this result in CI.\n");
        return [
            'source' => 'fallback (hardcoded; canonical file missing)',
            'required_keys' => FALLBACK_REQUIRED_KEYS,
            'plugin_prefixes' => FALLBACK_PLUGIN_PREFIXES,
        ];
    }
    $source = file_get_contents($canonical);
    if ($source === false) {
        throw new gate_config_error('Cannot read ' . CANONICAL_SCANNER);
    }
    try {
        $tokens = token_get_all($source, TOKEN_PARSE);
    } catch (\ParseError $e) {
        throw new gate_config_error(CANONICAL_SCANNER . ' does not parse: ' . $e->getMessage());
    }
    $rules = ['source' => CANONICAL_SCANNER];
    foreach (['required_keys' => 'REQUIRED_CONTRACT_KEYS', 'plugin_prefixes' => 'PLUGIN_PREFIXES'] as $field => $const) {
        $values = parse_string_list_const($tokens, $const);
        if ($values === null || $values === []) {
            throw new gate_config_error(
                'Cannot parse const ' . $const . ' in ' . CANONICAL_SCANNER
                . ' (expected a non-empty array literal of plain strings). No fallback is used.');
        }
        $rules[$field] = $values;
    }

    $flat = (string)preg_replace('/\s+/', '', $source);
    $stale = [];
    foreach (RULE_FINGERPRINTS as $label => $fragment) {
        if (!str_contains($flat, (string)preg_replace('/\s+/', '', $fragment))) {
            $stale[] = "  - {$label}: {$fragment}";
        }
    }
    if ($stale !== []) {
        throw new gate_config_error(
            CANONICAL_SCANNER . " no longer contains these rule fragments:\n" . implode("\n", $stale) . "\n"
            . 'theme_sentientia\ws_contract_scanner changed. Port the change to'
            . ' moodle-enhancement/tools/ci-ws-contract-check.php, then update RULE_FINGERPRINTS.');
    }
    return $rules;
}

/**
 * Parse `const NAME = ['a', 'b'];` (or array('a', 'b')) from a token stream.
 *
 * @return list<string>|null  null when absent or not a plain string list
 */
function parse_string_list_const(array $tokens, string $name): ?array {
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        if (!tok_is($tokens[$i], T_CONST)) {
            continue;
        }
        // Name = last token before '=' (also covers a typed `const array NAME`).
        $last = null;
        $j = next_sig($tokens, $i);
        while ($j >= 0 && $tokens[$j] !== '=' && $tokens[$j] !== ';') {
            $last = $tokens[$j];
            $j = next_sig($tokens, $j);
        }
        if ($j < 0 || $tokens[$j] !== '=' || !tok_is($last, T_STRING) || $last[1] !== $name) {
            continue;
        }
        $open = next_sig($tokens, $j);
        if ($open >= 0 && tok_is($tokens[$open], T_ARRAY)) {
            $open = next_sig($tokens, $open);
            if ($open < 0 || $tokens[$open] !== '(') {
                return null;
            }
        } else if ($open < 0 || $tokens[$open] !== '[') {
            return null;
        }
        $close = matching_close($tokens, $open);
        if ($close < 0) {
            return null;
        }
        $values = [];
        $expectvalue = true;
        for ($k = $open + 1; $k < $close; $k++) {
            $t = $tokens[$k];
            if (is_noise($t)) {
                continue;
            }
            if ($expectvalue && tok_is($t, T_CONSTANT_ENCAPSED_STRING)) {
                $value = decode_string_literal($t[1]);
                if ($value === null) {
                    return null;
                }
                $values[] = $value;
                $expectvalue = false;
            } else if (!$expectvalue && $t === ',') {
                $expectvalue = true;
            } else {
                return null;
            }
        }
        return $values;
    }
    return null;
}

// ──────────────────────────────────────────────────────────────────
// Rule 2 + 3: consumers (ws_contract_scanner::scan_consumers)
// ──────────────────────────────────────────────────────────────────

/**
 * @param array{templates:int, regions:int, matched:int} $stats  filled in
 * @return array<string, list<string>>  ws name => source templates (repo-relative)
 */
function scan_consumers(string $dirroot, array $prefixes, string $reporoot, array &$stats): array {
    $stats = ['templates' => 0, 'regions' => 0, 'matched' => 0];
    $templates = find_files($dirroot . '/local', '.mustache');
    // The Moodle scanner keeps filesystem order; sorting only fixes the
    // order of the "used by" lists in the report, not what is found.
    sort($templates, SORT_STRING);
    $consumers = [];
    foreach ($templates as $path) {
        $normalised = str_replace('\\', '/', $path);
        $ours = false;
        foreach ($prefixes as $prefix) {
            if (str_contains($normalised, '/local/' . $prefix)) {
                $ours = true;
                break;
            }
        }
        if (!$ours) {
            continue;
        }
        $stats['templates']++;
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        if (!preg_match_all(REGION_REGEX, $content, $hits, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($hits[0] as $hit) {
            $stats['regions']++;
            $offset = $hit[1];
            $window = substr($content, max(0, $offset - WINDOW_BACK), WINDOW_LENGTH);
            if (preg_match(WSNAME_REGEX, $window, $wsm)) {
                $stats['matched']++;
                $wsname = $wsm[1];
                $consumers[$wsname][] = repo_relative($path, $reporoot);
                $consumers[$wsname] = array_values(array_unique($consumers[$wsname]));
            }
        }
    }
    ksort($consumers);
    return $consumers;
}

/**
 * Same walk as ws_contract_scanner::find_files().
 *
 * @return list<string>
 */
function find_files(string $dir, string $suffix): array {
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    try {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $out[] = $file->getPathname();
            }
        }
    } catch (\UnexpectedValueException $e) {
        throw new gate_config_error('Cannot walk ' . $dir . ': ' . $e->getMessage());
    }
    return $out;
}

// ──────────────────────────────────────────────────────────────────
// Rule 4: services.php (ws_contract_scanner::resolve_class)
// ──────────────────────────────────────────────────────────────────

/**
 * @return array{0:string, 1:string, 2:string}|null  [classname, methodname, services.php path]
 */
function resolve_class(string $wsname, string $dirroot, array $prefixes): ?array {
    $servicefiles = [];
    foreach ($prefixes as $prefix) {
        $found = glob($dirroot . '/local/' . $prefix . '*/db/services.php');
        if (is_array($found)) {
            $servicefiles = array_merge($servicefiles, $found);
        }
    }
    foreach ($servicefiles as $servicefile) {
        $functions = load_services_file($servicefile);
        if (isset($functions[$wsname]) && !empty($functions[$wsname]['classname'])) {
            $classname = $functions[$wsname]['classname'];
            $methodname = $functions[$wsname]['methodname'] ?? DEFAULT_METHODNAME;
            if (!is_string($classname) || !is_string($methodname)) {
                throw new gate_config_error("{$servicefile}: classname/methodname of {$wsname} is not a string");
            }
            return [$classname, $methodname, $servicefile];
        }
    }
    return null;
}

/**
 * Load a db/services.php in an isolated scope; cached per file.
 */
function load_services_file(string $path): array {
    static $cache = [];
    if (!array_key_exists($path, $cache)) {
        $cache[$path] = (static function (string $file): array {
            $functions = [];
            try {
                include $file;
            } catch (\Throwable $e) {
                throw new gate_config_error("Cannot load {$file}: " . get_class($e) . ': ' . $e->getMessage());
            }
            return is_array($functions) ? $functions : [];
        })($path);
    }
    return $cache[$path];
}

// ──────────────────────────────────────────────────────────────────
// Rule 5: class file (Moodle autoloader, core_component::load_classes)
// ──────────────────────────────────────────────────────────────────

/**
 * Folder of a plugin component, or null when the type is unknown here.
 * Moodle only registers a plugin whose folder has a version.php. The roots
 * are tried in order (the audited tree first; see "Trees" in the header).
 *
 * @param list<string> $roots  dirroots, absolute and '/'-separated
 * @return array{0:?string, 1:bool}  [absolute plugin dir or null, type known]
 */
function component_dir(string $component, array $roots): array {
    $pos = strpos($component, '_');
    if ($pos === false) {
        return [null, false];
    }
    $type = substr($component, 0, $pos);
    $name = substr($component, $pos + 1);
    if (!isset(COMPONENT_DIRS[$type]) || $name === '') {
        return [null, false];
    }
    $rel = COMPONENT_DIRS[$type] . '/' . $name;
    foreach ($roots as $root) {
        if (exact_case_path($root, $rel . '/version.php') !== null) {
            return [$root . '/' . $rel, true];
        }
    }
    return [null, true];
}

/**
 * @param list<string> $roots  see component_dir()
 * @return array{status:string, file?:string, reason?:string}  status found|skip|unverifiable
 */
function locate_class_file(string $classname, array $roots, string $reporoot): array {
    $classname = ltrim($classname, '\\');
    if (str_contains($classname, '\\')) {
        $parts = explode('\\', $classname);
        $component = array_shift($parts);
        [$dir, $known] = component_dir($component, $roots);
        if (!$known) {
            return ['status' => 'unverifiable',
                'reason' => "{$classname} belongs to {$component}, which is not a plugin type this gate maps"
                    . ' (core or other); Moodle would load it, this gate cannot'];
        }
        if ($dir === null) {
            return ['status' => 'skip', 'reason' => "plugin {$component} not found (no version.php) in the"
                . ' scanned tree' . (count($roots) > 1 ? ' or the repo root it deploys over' : '')
                . ' (Moodle: class_exists() false)'];
        }
        $rel = 'classes/' . implode('/', $parts) . '.php';
        $file = exact_case_path($dir, $rel);
        return $file !== null
            ? ['status' => 'found', 'file' => $file]
            : ['status' => 'skip', 'reason' => 'no class file ' . repo_relative($dir . '/' . $rel, $reporoot)
                . ' (exact case; Moodle: class_exists() false)'];
    }
    // Legacy frankenstyle name: Moodle maps <component>_<file> to classes/<file>.php.
    $pos = 0;
    while (($pos = strpos($classname, '_', $pos + 1)) !== false) {
        [$dir] = component_dir(substr($classname, 0, $pos), $roots);
        if ($dir === null) {
            continue;
        }
        $file = exact_case_path($dir, 'classes/' . substr($classname, $pos + 1) . '.php');
        if ($file !== null) {
            return ['status' => 'found', 'file' => $file];
        }
    }
    return ['status' => 'skip', 'reason' => "no autoloadable class file for {$classname}"
        . ' (Moodle: class_exists() false)'];
}

/**
 * Join $rel onto $base, matching every segment with its exact case (Moodle's
 * classmap is case-sensitive, and so is the ubuntu CI filesystem, while
 * Windows is not).
 */
function exact_case_path(string $base, string $rel): ?string {
    static $listings = [];
    $path = $base;
    foreach (explode('/', $rel) as $segment) {
        if (!isset($listings[$path])) {
            $listings[$path] = is_dir($path) ? (scandir($path) ?: []) : [];
        }
        if ($segment === '' || !in_array($segment, $listings[$path], true)) {
            return null;
        }
        $path .= '/' . $segment;
    }
    return $path;
}

// ──────────────────────────────────────────────────────────────────
// Rule 6: <method>_parameters() keys (ws_contract_scanner::missing_keys)
// ──────────────────────────────────────────────────────────────────

/**
 * @return array{status:string, keys?:list<string>, reason?:string}  status keys|skip|unverifiable
 */
function declared_param_keys(string $file, string $classname, string $methodname): array {
    $source = file_get_contents($file);
    if ($source === false) {
        return ['status' => 'unverifiable', 'reason' => 'cannot read the class file'];
    }
    try {
        $tokens = token_get_all($source, TOKEN_PARSE);
    } catch (\ParseError $e) {
        return ['status' => 'unverifiable', 'reason' => 'class file does not parse: ' . $e->getMessage()];
    }
    $class = find_class($tokens, ltrim($classname, '\\'));
    if ($class === null) {
        return ['status' => 'skip', 'reason' => "the class file does not declare {$classname}"
            . ' (Moodle: class_exists() false)'];
    }
    $wanted = $methodname . PARAMS_SUFFIX;
    $method = find_method($tokens, $class['open'], $class['close'], $wanted);
    if ($method === null) {
        if ($class['usestraits']) {
            return ['status' => 'unverifiable', 'reason' => "{$wanted}() is not declared in the class;"
                . ' it may come from a trait, which this gate does not follow'];
        }
        $parent = $class['parent'];
        if ($parent === null || strcasecmp(last_segment($parent), 'external_api') === 0) {
            return ['status' => 'skip', 'reason' => "no {$wanted}() (Moodle: method_exists() false)"];
        }
        return ['status' => 'unverifiable', 'reason' => "{$wanted}() is not declared in the class;"
            . " it may be inherited from {$parent}, which this gate does not follow"];
    }
    if ($method['private'] || !$method['static'] || $method['abstract'] || $method['body'] === null) {
        return ['status' => 'skip', 'reason' => "{$wanted}() is not a public static method with a body"
            . ' (Moodle: the call throws, so the scanner skips it)'];
    }
    return parameters_array_keys($tokens, $method['body'][0], $method['body'][1], $wanted);
}

/**
 * Find a class (or enum: class_exists() is true for both) by its
 * case-insensitive fully-qualified name.
 *
 * @return array{open:int, close:int, parent:?string, usestraits:bool}|null
 */
function find_class(array $tokens, string $fqname): ?array {
    $namespace = '';
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (tok_is($t, T_NAMESPACE)) {
            $j = next_sig($tokens, $i);
            if ($j >= 0 && (tok_is($tokens[$j], T_STRING) || tok_is($tokens[$j], T_NAME_QUALIFIED))) {
                $namespace = $tokens[$j][1];
            } else if ($j >= 0 && $tokens[$j] === '{') {
                $namespace = '';
            }
            continue;
        }
        if (!tok_is($t, T_CLASS) && !tok_is($t, T_ENUM)) {
            continue;
        }
        $prev = prev_sig($tokens, $i);
        if ($prev >= 0 && (tok_is($tokens[$prev], T_DOUBLE_COLON) || tok_is($tokens[$prev], T_NEW))) {
            continue;
        }
        $j = next_sig($tokens, $i);
        if ($j < 0 || !tok_is($tokens[$j], T_STRING)) {
            continue;
        }
        $name = ($namespace === '' ? '' : $namespace . '\\') . $tokens[$j][1];
        if (strcasecmp($name, $fqname) !== 0) {
            continue;
        }
        $parent = null;
        $k = $j;
        while (($k = next_sig($tokens, $k)) >= 0 && $tokens[$k] !== '{') {
            if (tok_is($tokens[$k], T_EXTENDS)) {
                $p = next_sig($tokens, $k);
                $parent = $p >= 0 ? tok_text($tokens[$p]) : null;
            }
        }
        if ($k < 0) {
            return null;
        }
        $close = matching_close($tokens, $k);
        if ($close < 0) {
            return null;
        }
        $usestraits = false;
        $depth = 0;
        for ($m = $k + 1; $m < $close; $m++) {
            $depth += bracket_delta($tokens[$m]);
            if ($depth === 0 && tok_is($tokens[$m], T_USE)) {
                $usestraits = true;
            }
        }
        return ['open' => $k, 'close' => $close, 'parent' => $parent, 'usestraits' => $usestraits];
    }
    return null;
}

/**
 * Find a method declared directly in the class body (not a closure).
 *
 * @return array{private:bool, static:bool, abstract:bool, body:?array{0:int,1:int}}|null
 */
function find_method(array $tokens, int $classopen, int $classclose, string $wanted): ?array {
    $depth = 0;
    for ($i = $classopen + 1; $i < $classclose; $i++) {
        $t = $tokens[$i];
        if ($depth === 0 && tok_is($t, T_FUNCTION)) {
            $j = next_sig($tokens, $i);
            if ($j >= 0 && $tokens[$j] === '&') {
                $j = next_sig($tokens, $j);
            }
            if ($j >= 0 && tok_is($tokens[$j], T_STRING) && strcasecmp($tokens[$j][1], $wanted) === 0) {
                $mods = [];
                $p = prev_sig($tokens, $i);
                while ($p >= 0 && is_array($tokens[$p]) && in_array($tokens[$p][0],
                        [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL], true)) {
                    $mods[] = $tokens[$p][0];
                    $p = prev_sig($tokens, $p);
                }
                $body = null;
                $paren = next_sig($tokens, $j);
                $parenclose = ($paren >= 0 && $tokens[$paren] === '(') ? matching_close($tokens, $paren) : -1;
                if ($parenclose >= 0) {
                    for ($k = $parenclose + 1; $k < $classclose; $k++) {
                        if ($tokens[$k] === ';') {
                            break;
                        }
                        if ($tokens[$k] === '{') {
                            $end = matching_close($tokens, $k);
                            $body = $end >= 0 ? [$k, $end] : null;
                            break;
                        }
                    }
                }
                return [
                    'private' => in_array(T_PRIVATE, $mods, true) || in_array(T_PROTECTED, $mods, true),
                    'static' => in_array(T_STATIC, $mods, true),
                    'abstract' => in_array(T_ABSTRACT, $mods, true),
                    'body' => $body,
                ];
            }
        }
        $depth += bracket_delta($t);
    }
    return null;
}

/**
 * Top-level keys of the array literal given to the one
 * `new external_function_parameters(...)` inside the method body.
 *
 * @return array{status:string, keys?:list<string>, reason?:string}
 */
function parameters_array_keys(array $tokens, int $bodyopen, int $bodyclose, string $method): array {
    $news = [];
    for ($i = $bodyopen + 1; $i < $bodyclose; $i++) {
        if (!tok_is($tokens[$i], T_NEW)) {
            continue;
        }
        $j = next_sig($tokens, $i);
        if ($j >= 0 && (tok_is($tokens[$j], T_STRING) || tok_is($tokens[$j], T_NAME_QUALIFIED)
                || tok_is($tokens[$j], T_NAME_FULLY_QUALIFIED))
                && strcasecmp(last_segment($tokens[$j][1]), 'external_function_parameters') === 0) {
            $news[] = $j;
        }
    }
    if (count($news) !== 1) {
        return ['status' => 'unverifiable', 'reason' => "{$method}() has " . count($news)
            . ' `new external_function_parameters(...)` expressions; the gate needs exactly one'];
    }
    $paren = next_sig($tokens, $news[0]);
    if ($paren < 0 || $tokens[$paren] !== '(') {
        return ['status' => 'unverifiable', 'reason' => "{$method}(): `new external_function_parameters`"
            . ' without an argument list'];
    }
    $first = next_sig($tokens, $paren);
    if ($first >= 0 && $tokens[$first] === ')') {
        return ['status' => 'keys', 'keys' => []];
    }
    $open = $first;
    if ($open >= 0 && tok_is($tokens[$open], T_ARRAY)) {
        $open = next_sig($tokens, $open);
        if ($open < 0 || $tokens[$open] !== '(') {
            $open = -1;
        }
    } else if ($open < 0 || $tokens[$open] !== '[') {
        $open = -1;
    }
    $close = $open >= 0 ? matching_close($tokens, $open) : -1;
    $after = $close >= 0 ? next_sig($tokens, $close) : -1;
    if ($close < 0 || $after < 0 || ($tokens[$after] !== ',' && $tokens[$after] !== ')')) {
        return ['status' => 'unverifiable', 'reason' => "{$method}(): the keys are not a plain array"
            . ' literal (variable, helper call or expression); static analysis cannot evaluate them'];
    }

    $keys = [];
    $element = [];
    $depth = 0;
    for ($i = $open + 1; $i <= $close; $i++) {
        $t = $tokens[$i];
        if ($i === $close || ($depth === 0 && $t === ',')) {
            $result = element_key($element);
            if ($result === false) {
                return ['status' => 'unverifiable', 'reason' => "{$method}(): a computed or spread key"
                    . ' in the parameters array; static analysis cannot evaluate it'];
            }
            if (is_string($result)) {
                $keys[] = $result;
            }
            $element = [];
            continue;
        }
        if (!is_noise($t)) {
            $element[] = [$t, $depth];
        }
        $depth += bracket_delta($t);
    }
    return ['status' => 'keys', 'keys' => array_values(array_unique($keys))];
}

/**
 * Key of one array element: string key, null for an int/positional key or
 * an empty slot, false when it cannot be determined statically.
 *
 * @param list<array{0:mixed, 1:int}> $element  significant tokens with depth
 * @return string|null|false
 */
function element_key(array $element) {
    if ($element === []) {
        return null;
    }
    if (tok_is($element[0][0], T_ELLIPSIS)) {
        return false;
    }
    $keytokens = [];
    foreach ($element as [$t, $depth]) {
        if ($depth === 0 && tok_is($t, T_DOUBLE_ARROW)) {
            if (count($keytokens) !== 1) {
                return false;
            }
            $k = $keytokens[0];
            if (tok_is($k, T_LNUMBER)) {
                return null;
            }
            if (tok_is($k, T_CONSTANT_ENCAPSED_STRING)) {
                $value = decode_string_literal($k[1]);
                return $value ?? false;
            }
            return false;
        }
        $keytokens[] = $t;
    }
    return null;
}

// ──────────────────────────────────────────────────────────────────
// Token helpers
// ──────────────────────────────────────────────────────────────────

function is_noise($tok): bool {
    return is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function tok_is($tok, int $type): bool {
    return is_array($tok) && $tok[0] === $type;
}

function tok_text($tok): string {
    return is_array($tok) ? $tok[1] : (string)$tok;
}

function next_sig(array $tokens, int $i): int {
    $n = count($tokens);
    for ($j = $i + 1; $j < $n; $j++) {
        if (!is_noise($tokens[$j])) {
            return $j;
        }
    }
    return -1;
}

function prev_sig(array $tokens, int $i): int {
    for ($j = $i - 1; $j >= 0; $j--) {
        if (!is_noise($tokens[$j])) {
            return $j;
        }
    }
    return -1;
}

/** +1 for an opening bracket token, -1 for a closing one, else 0. */
function bracket_delta($tok): int {
    if ($tok === '(' || $tok === '[' || $tok === '{' || tok_is($tok, T_CURLY_OPEN)
            || tok_is($tok, T_DOLLAR_OPEN_CURLY_BRACES) || tok_is($tok, T_ATTRIBUTE)) {
        return 1;
    }
    if ($tok === ')' || $tok === ']' || $tok === '}') {
        return -1;
    }
    return 0;
}

/** Index of the token that closes the bracket opened at $open, or -1. */
function matching_close(array $tokens, int $open): int {
    $depth = 0;
    $n = count($tokens);
    for ($i = $open; $i < $n; $i++) {
        $depth += bracket_delta($tokens[$i]);
        if ($depth === 0) {
            return $i;
        }
    }
    return -1;
}

/** Value of a PHP string literal token; null if it has escapes we do not decode. */
function decode_string_literal(string $literal): ?string {
    if ($literal !== '' && ($literal[0] === 'b' || $literal[0] === 'B')) {
        $literal = substr($literal, 1);
    }
    $quote = $literal[0] ?? '';
    $inner = substr($literal, 1, -1);
    if ($quote === "'") {
        return preg_replace('/\\\\([\\\\\'])/', '$1', $inner);
    }
    if ($quote === '"') {
        return str_contains($inner, '\\') ? null : $inner;
    }
    return null;
}

function last_segment(string $name): string {
    $pos = strrpos($name, '\\');
    return $pos === false ? $name : substr($name, $pos + 1);
}

function repo_relative(string $path, string $reporoot): string {
    $path = str_replace('\\', '/', $path);
    $root = rtrim(str_replace('\\', '/', $reporoot), '/') . '/';
    // path-boundary-ok: filesystem path, not a tenant path; $root is '/'-terminated on the line above
    return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
}

// ──────────────────────────────────────────────────────────────────
// One tree (ws_contract_scanner::audit, plus the CI verdict)
// ──────────────────────────────────────────────────────────────────

/**
 * Rules 2-6 on one tree, and that tree's exit code.
 *
 * @param string $dirroot  tree whose local/ is scanned (absolute, '/'-separated)
 * @param list<string> $classroots  where plugin classes are looked up, in order
 * @return array{tree:string, exit_code:int, reason:?string, stats:array, consumers:array,
 *               verified:int, failures:array, unverifiable:array, skipped:array, details:array}
 */
function audit_tree(string $dirroot, array $classroots, array $rules, string $reporoot, bool $allowskips): array {
    if (!is_dir($dirroot . '/local')) {
        throw new gate_config_error("No local/ folder under {$dirroot}");
    }
    $treelabel = repo_relative($dirroot . '/local', $reporoot) . '/';

    $stats = [];
    $consumers = scan_consumers($dirroot, $rules['plugin_prefixes'], $reporoot, $stats);

    $details = [];
    $failures = [];
    $unverifiable = [];
    $skipped = [];
    $verified = 0;
    foreach ($consumers as $wsname => $sources) {
        $detail = ['used_by' => $sources];
        $resolved = resolve_class($wsname, $dirroot, $rules['plugin_prefixes']);
        if ($resolved === null) {
            $detail['status'] = 'skip';
            $detail['reason'] = 'no db/services.php entry with a classname under ' . $treelabel
                . implode('*, ' . $treelabel, $rules['plugin_prefixes']) . '*';
        } else {
            [$classname, $methodname, $servicefile] = $resolved;
            $detail['classname'] = $classname;
            $detail['methodname'] = $methodname;
            $detail['services_file'] = repo_relative($servicefile, $reporoot);
            $located = locate_class_file($classname, $classroots, $reporoot);
            if ($located['status'] !== 'found') {
                $detail['status'] = $located['status'];
                $detail['reason'] = $located['reason'];
            } else {
                $detail['class_file'] = repo_relative($located['file'], $reporoot);
                $declared = declared_param_keys($located['file'], $classname, $methodname);
                if ($declared['status'] !== 'keys') {
                    $detail['status'] = $declared['status'];
                    $detail['reason'] = $declared['reason'];
                } else {
                    $missing = [];
                    foreach ($rules['required_keys'] as $key) {
                        if (!in_array($key, $declared['keys'], true)) {
                            $missing[] = $key;
                        }
                    }
                    $detail['declared_keys'] = $declared['keys'];
                    $detail['missing_keys'] = $missing;
                    $detail['status'] = $missing === [] ? 'ok' : 'fail';
                }
            }
        }
        if ($detail['status'] === 'ok' || $detail['status'] === 'fail') {
            $verified++;
        }
        if ($detail['status'] === 'fail') {
            $failures[$wsname] = $detail['missing_keys'];
        } else if ($detail['status'] === 'unverifiable') {
            $unverifiable[$wsname] = $detail['reason'];
        } else if ($detail['status'] === 'skip') {
            $skipped[$wsname] = $detail['reason'];
        }
        $details[$wsname] = $detail;
    }

    if ($consumers === []) {
        [$exit, $reason] = [EXIT_CONFIG, 'zero_consumers'];
    } else if ($failures !== [] || $unverifiable !== [] || ($skipped !== [] && !$allowskips)) {
        [$exit, $reason] = [EXIT_FAILURES, null];
    } else if ($verified === 0) {
        // Only reachable with --allow-skips: every consumer was skipped.
        [$exit, $reason] = [EXIT_CONFIG, 'zero_verified'];
    } else {
        [$exit, $reason] = [EXIT_OK, null];
    }

    return [
        'tree' => $treelabel,
        'exit_code' => $exit,
        'reason' => $reason,
        'stats' => $stats,
        'consumers' => $consumers,
        'verified' => $verified,
        'failures' => $failures,
        'unverifiable' => $unverifiable,
        'skipped' => $skipped,
        'details' => $details,
    ];
}

// ──────────────────────────────────────────────────────────────────
// Main
// ──────────────────────────────────────────────────────────────────

function usage(): string {
    return <<<EOT
Usage: php moodle-enhancement/tools/ci-ws-contract-check.php [options]

Static mirror of theme_sentientia\\ws_contract_scanner (no Moodle needed).
By default audits local/ AND moodle-enhancement/local/; exits with the worse code.

Options:
  --json                 Machine-readable output
  -v, --verbose          List the templates that use each WS
  --dirroot=PATH         Audit only the tree whose local/ is under PATH
                         (a relative PATH is taken from the repo root, e.g.
                         --dirroot=moodle-enhancement)
  --allow-skips          Let skipped consumers pass, as the Moodle scanner
                         does (for partial trees). A tree whose consumers
                         are all skipped then exits 2 (zero verified)
  --allow-fallback-keys  If the canonical scanner file is missing, use the
                         hardcoded key list instead of exiting 2 (warns)
  -h, --help             This help

Exit: 0 pass, 1 failures (skips included unless --allow-skips),
      2 zero consumers, zero verified, or misconfigured gate.

EOT;
}

function main(array $argv): int {
    $opts = ['json' => false, 'verbose' => false, 'dirroot' => null, 'fallback' => false, 'allowskips' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--json') {
            $opts['json'] = true;
        } else if ($arg === '--verbose' || $arg === '-v') {
            $opts['verbose'] = true;
        } else if ($arg === '--allow-fallback-keys') {
            $opts['fallback'] = true;
        } else if ($arg === '--allow-skips') {
            $opts['allowskips'] = true;
        } else if (str_starts_with($arg, '--dirroot=')) {
            $opts['dirroot'] = substr($arg, strlen('--dirroot='));
        } else if ($arg === '--help' || $arg === '-h') {
            echo usage();
            return EXIT_OK;
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n\n" . usage());
            return EXIT_CONFIG;
        }
    }

    try {
        if (!extension_loaded('tokenizer')) {
            throw new gate_config_error('The PHP tokenizer extension is required.');
        }
        $reporoot = realpath(__DIR__ . '/../..');
        if ($reporoot === false) {
            throw new gate_config_error('Cannot resolve the repo root from ' . __DIR__);
        }
        $reporoot = str_replace('\\', '/', $reporoot);
        $rules = load_canonical_rules($reporoot, $opts['fallback']);

        // [dirroot, class lookup roots]; see "Trees" in the header.
        $trees = [];
        if ($opts['dirroot'] !== null && $opts['dirroot'] !== '') {
            $candidate = preg_match('#^([a-zA-Z]:)?[/\\\\]#', $opts['dirroot'])
                ? $opts['dirroot'] : $reporoot . '/' . $opts['dirroot'];
            $resolved = realpath($candidate);
            if ($resolved === false) {
                throw new gate_config_error("--dirroot not found: {$candidate}");
            }
            $dirroot = str_replace('\\', '/', $resolved);
            $classroots = [$dirroot];
            if ($dirroot !== $reporoot && str_starts_with($dirroot . '/', $reporoot . '/')) {
                $classroots[] = $reporoot;
            }
            $trees[] = [$dirroot, $classroots];
        } else {
            $second = $reporoot . '/' . SECOND_TREE;
            $trees[] = [$reporoot, [$reporoot]];
            $trees[] = [$second, [$second, $reporoot]];
        }

        $results = [];
        foreach ($trees as [$dirroot, $classroots]) {
            $results[] = audit_tree($dirroot, $classroots, $rules, $reporoot, $opts['allowskips']);
        }
    } catch (gate_config_error $e) {
        if ($opts['json']) {
            echo json_encode(['ok' => false, 'reason' => 'config_error', 'message' => $e->getMessage()],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            echo "CONFIG ERROR (ws_contract CI gate):\n" . $e->getMessage() . "\n";
        }
        return EXIT_CONFIG;
    }

    $exit = max(array_column($results, 'exit_code'));

    if ($opts['json']) {
        echo json_encode([
            'ok' => $exit === EXIT_OK,
            'exit_code' => $exit,
            'rules' => $rules,
            'allow_skips' => $opts['allowskips'],
            'trees' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        return $exit;
    }

    echo "\n=== ws_contract CI gate (static mirror of theme_sentientia\\ws_contract_scanner) ===\n\n";
    echo sprintf("Rules source:      %s\n", $rules['source']);
    echo sprintf("Required keys:     %s\n", implode(', ', $rules['required_keys']));
    echo sprintf("Plugin prefixes:   %s\n", implode(', ', $rules['plugin_prefixes']));
    echo sprintf("Trees:             %s\n", implode(', ', array_column($results, 'tree')));
    echo sprintf("Skipped consumers: %s\n", $opts['allowskips']
        ? 'allowed (--allow-skips); a tree with none verified exits 2'
        : 'FAIL the gate (--allow-skips lets them pass)');

    $n = count($results);
    $skipsfailed = false;
    foreach ($results as $i => $tree) {
        print_tree($tree, $i + 1, $n, $opts['verbose'], $opts['allowskips']);
        $skipsfailed = $skipsfailed || ($tree['skipped'] !== [] && !$opts['allowskips']);
    }
    echo "\n";

    if ($exit === EXIT_OK) {
        echo "RESULT: ALL PASS\n\n";
        return $exit;
    }
    echo $exit === EXIT_CONFIG
        ? "RESULT: FAIL (exit 2: a tree found zero consumers or verified none; see the tree results)\n"
        : "RESULT: FAIL\n";
    if (in_array(EXIT_FAILURES, array_column($results, 'exit_code'), true)) {
        echo "Fix pattern: local/sentientia_request/classes/external/list_mine.php declares all\n";
        echo "required keys with VALUE_DEFAULT in a plain array literal.\n";
    }
    if ($skipsfailed) {
        echo "A SKIP is a datatable that can never load: check data-ws-name against db/services.php,\n";
        echo "the class file name and its exact case, that <method>_parameters() is public static,\n";
        echo "and that the plugin has a version.php.\n";
    }
    echo "\n";
    return $exit;
}

/**
 * Text report of one audit_tree() result.
 */
function print_tree(array $tree, int $index, int $count, bool $verbose, bool $allowskips): void {
    $stats = $tree['stats'];
    echo sprintf("\n--- Tree %d of %d: %s ---\n", $index, $count, $tree['tree']);
    echo sprintf("Templates read:    %d (prefixed plugins)\n", $stats['templates']);
    echo sprintf("Datatable regions: %d data-region=\"airpay-datatable\", %d with a data-ws-name in the window\n",
        $stats['regions'], $stats['matched']);
    echo "\n";

    if ($tree['consumers'] === []) {
        echo "WARNING: zero airpay-datatable consumers found in this tree.\n";
        echo "Either the scanner rules broke or no plugins use the shared datatable any more.\n";
        echo "Tree result: ZERO CONSUMERS (exit 2)\n";
        return;
    }

    echo sprintf("Consumers found:   %d\n", count($tree['consumers']));
    echo sprintf("Verified:          %d  (keys read and compared: OK or FAIL)\n", $tree['verified']);
    echo sprintf("Failures:          %d\n", count($tree['failures']));
    echo sprintf("Unverifiable:      %d  (counted as failures)\n", count($tree['unverifiable']));
    echo sprintf("Skipped:           %d  (%s)\n", count($tree['skipped']), $allowskips
        ? 'allowed by --allow-skips, as the Moodle scanner does'
        : 'counted as failures; the Moodle scanner lets them pass');
    echo "\n";

    $labels = ['ok' => ' OK ', 'fail' => 'FAIL', 'unverifiable' => 'UNVR', 'skip' => 'SKIP'];
    foreach ($tree['details'] as $wsname => $detail) {
        echo sprintf("  [%s] %s\n", $labels[$detail['status']], $wsname);
        if (isset($detail['class_file']) && ($verbose || $detail['status'] !== 'ok')) {
            echo "         class file: {$detail['class_file']}\n";
        }
        if ($detail['status'] === 'fail') {
            echo '         missing contract keys: ' . implode(', ', $detail['missing_keys']) . "\n";
        } else if (isset($detail['reason'])) {
            echo "         {$detail['reason']}\n";
        }
        if ($verbose || $detail['status'] !== 'ok') {
            foreach ($detail['used_by'] as $src) {
                echo "         used by: {$src}\n";
            }
        }
    }
    echo "\n";

    if ($tree['reason'] === 'zero_verified') {
        echo "WARNING: zero consumers verified: every consumer in this tree was skipped,\n";
        echo "so it checked nothing.\n";
        echo "Tree result: ZERO VERIFIED (exit 2)\n";
        return;
    }
    echo 'Tree result: ' . ($tree['exit_code'] === EXIT_OK ? 'PASS' : 'FAIL') . "\n";
}

exit(main($argv));
