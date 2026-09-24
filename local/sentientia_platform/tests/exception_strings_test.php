<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Platform-wide guard: an exception that names a core error string must name
 * one that exists.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * UAT defect N5 (2026-09-24). Eleven refusals across seven pages in four
 * plugins threw
 *
 *     throw new moodle_exception('nopermission');
 *
 * With no component argument, moodle_exception looks the key up in core's
 * lang/en/error.php. Core has no 'nopermission' - only the plural
 * 'nopermissions', which takes a {$a} - so every one of those refusals
 * rendered as the bare identifier "error/nopermission". Nothing errors when
 * that happens: the page simply tells the user nothing, and it only shows up
 * when a human reads the screen. The same sweep found the pattern with
 * 'invalidparam', 'invalidformat', 'invalidpage', 'invalidchoice',
 * 'invalidname' and 'invalidparameter' (a debug-file string, not an error-file
 * one), and one call that passed an English sentence as the key.
 *
 * Fixing the sites one by one is not a fix, so this test walks the PHP of
 * every Sentientia plugin on disk (every component whose plugin name starts
 * with "sentientia", found through core_component so it follows whichever
 * plugin tree is deployed; the pre-de-brand legacy theme directory is not a
 * Sentientia plugin and is not scanned), finds each
 *
 *     new moodle_exception('<literal key>'[, '' | 'error' | 'moodle' | 'core' | null ...])
 *     print_error('<literal key>'[, same])
 *
 * - i.e. every call that resolves to core's error.php - and asserts the key
 * exists there, using the real string manager rather than a copy of the file.
 *
 * HOW TO FIX A FAILURE
 * --------------------
 *   - a capability decides the refusal:
 *         throw new \required_capability_exception($context, '<capability>', 'nopermissions', '');
 *     which renders "Sorry, but you do not currently have permissions to do
 *     that (<capability name>)".
 *   - anything else: add a string to your own plugin's lang/en AND lang/hi and
 *     pass your component, e.g. moodle_exception('error_noorgscope', 'local_x').
 *
 * WHAT IT DOES NOT SEE
 * --------------------
 * A key or component built at runtime (a variable, a concatenation) cannot be
 * resolved statically and is skipped. Calls that pass a plugin component are
 * not checked here - their strings live in that plugin's own lang pack.
 *
 * BASELINE
 * --------
 * The sites below were found by this test on its first run and are outside
 * the change that introduced it (they belong to plugins other work is in
 * flight on). They are listed so the gate can be BLOCKING for anything new
 * from day one. The list may only shrink: fix a site and the test fails until
 * its entry is lowered or removed, so the baseline cannot rot into a
 * permanent allowlist.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversNothing
 */
final class exception_strings_test extends \advanced_testcase {

    /**
     * Component arguments that moodle_exception resolves to core's error.php.
     * Mirrors the first line of core\exception\moodle_exception::__construct():
     * empty($module) || $module == 'moodle' || $module == 'core' -> 'error'.
     *
     * @var string[]
     */
    private const CORE_COMPONENTS = ['', 'error', 'moodle', 'core'];

    /**
     * Directory names, at any depth inside a plugin, that are not scanned.
     *
     * tests/ is excluded because a test may throw a deliberately bogus key to
     * exercise an error path.
     *
     * @var string[]
     */
    private const SKIP_DIRS = ['tests', 'lang', 'vendor', 'node_modules', 'amd', 'yui'];

    /**
     * Fewer checked call sites than this means the scan is broken, not clean.
     * 46 sites resolved to core's error.php when the test was written.
     *
     * @var int
     */
    private const MIN_CALL_SITES = 25;

    /**
     * Known offenders at the time this test was written, as
     * "<component>/<path inside plugin>|<key>" => number of occurrences.
     *
     * Keyed on component-relative path, not dirroot-relative, so it holds
     * whichever of the repo's two plugin trees is deployed. No line numbers:
     * they move with every unrelated edit.
     *
     * @var array<string,int>
     */
    private const BASELINE = [
        // Same defect as N5 ('nopermission' does not exist), found by the scan.
        'local_sentientia_manager/member.php|nopermission' => 1,
        'local_sentientia_skills/index.php|nopermission' => 1,
        'theme_sentientia/classes/output/core_renderer.php|nopermission' => 1,
        // 'invalidchoice' is not a core string in any file.
        'local_sentientia_whatsapp/classes/dlt_template_registry.php|invalidchoice' => 4,
        'local_sentientia_whatsapp/classes/preference_manager.php|invalidchoice' => 1,
        // 'invalidparameter' lives in lang/en/debug.php, not error.php.
        'local_sentientia_manager/classes/external/bulk_decide.php|invalidparameter' => 1,
        'local_sentientia_users/sample.php|invalidparameter' => 1,
        'local_sentientia_leaderboard/classes/board_manager.php|invalidname' => 1,
        // An English sentence passed as the key.
        'local_sentientia_courses/classes/form/enrol_users_modal.php|No active manual enrolment method on this course.' => 1,
    ];

    /**
     * The scanner must recognise every call shape it claims to, and skip the
     * ones it cannot resolve. Without this, a tokenizer regression would turn
     * the real scan into an empty pass.
     */
    public function test_scanner_recognises_the_call_shapes(): void {
        $source = <<<'PHP'
<?php
throw new moodle_exception('a_bare');
throw new \moodle_exception('b_fullyqualified');
throw new moodle_exception('c_error', 'error');
throw new moodle_exception("d_doublequoted", 'moodle');
throw new moodle_exception('e_core', 'core', $url, $a);
throw new moodle_exception('f_emptycomponent', '');
throw new moodle_exception('g_nullcomponent', null, $url);
throw new \core\exception\moodle_exception('h_namespaced');
print_error('i_printerror');
throw new moodle_exception ( 'j_spaced' /* odd but legal */ );
throw new moodle_exception('k_it\'s_escaped');
\print_error('l_fqprinterror', 'error');

throw new moodle_exception('x_plugincomponent', 'local_foo');
throw new moodle_exception($dynamickey);
throw new moodle_exception('x_concatenated' . $suffix);
throw new moodle_exception('x_dynamiccomponent', $component);
throw new required_capability_exception($context, 'x/cap:view', 'nopermissions', '');
// throw new moodle_exception('x_linecomment');
/* throw new moodle_exception('x_blockcomment'); */
$s = "throw new moodle_exception('x_insidestring')";
$obj->print_error('x_method');
foo::print_error('x_static');
function print_error($x) {}
PHP;

        $calls = $this->find_core_string_calls($source);
        $keys = array_column($calls, 'key');

        $this->assertSame([
            'a_bare', 'b_fullyqualified', 'c_error', 'd_doublequoted', 'e_core',
            'f_emptycomponent', 'g_nullcomponent', 'h_namespaced', 'i_printerror',
            'j_spaced', "k_it's_escaped", 'l_fqprinterror',
        ], $keys);

        $this->assertSame(2, $calls[0]['line'], 'line numbers must point at the call');
        $this->assertSame('print_error', $calls[8]['call']);
    }

    /**
     * Every core-resolved exception key in Sentientia code exists in core's
     * lang/en/error.php - apart from the shrinking baseline above.
     */
    public function test_core_error_keys_named_by_sentientia_code_exist(): void {
        $components = $this->components();
        $this->assertGreaterThan(20, count($components),
            'found suspiciously few Sentientia plugins; the component walk is broken');

        $strings = get_string_manager();
        $checked = 0;
        $missing = [];

        foreach ($components as $component => $dir) {
            $root = rtrim(str_replace('\\', '/', $dir), '/');
            foreach ($this->php_files($dir) as $file) {
                $source = file_get_contents($file);
                if ($source === false) {
                    continue;
                }
                $relative = substr($file, strlen($root) + 1);

                foreach ($this->find_core_string_calls($source) as $call) {
                    $checked++;
                    if ($strings->string_exists($call['key'], 'error')) {
                        continue;
                    }
                    $missing["{$component}/{$relative}|{$call['key']}"][] = sprintf(
                        "%s/%s:%d %s('%s')", $component, $relative, $call['line'],
                        $call['call'], $call['key']);
                }
            }
        }

        $this->assertGreaterThanOrEqual(self::MIN_CALL_SITES, $checked,
            "only {$checked} core-resolved exception call sites were found; an "
            . 'empty scan would pass this test while proving nothing');

        $new = [];
        foreach ($missing as $baselinekey => $locations) {
            if (count($locations) > (self::BASELINE[$baselinekey] ?? 0)) {
                $new = array_merge($new, $locations);
            }
        }

        $stale = [];
        foreach (self::BASELINE as $baselinekey => $allowed) {
            [$path] = explode('|', $baselinekey, 2);
            [$component, $relative] = explode('/', $path, 2);
            // A plugin not deployed in this environment cannot be judged.
            if (!isset($components[$component]) || !is_file($components[$component] . '/' . $relative)) {
                continue;
            }
            $now = count($missing[$baselinekey] ?? []);
            if ($now < $allowed) {
                $stale[] = "{$baselinekey}: baseline says {$allowed}, found {$now}";
            }
        }

        $this->assertSame([], $new,
            "these exceptions name a key that core's lang/en/error.php does not have, "
            . "so the user sees the bare identifier (e.g. \"error/nopermission\") instead "
            . "of a message. For a capability refusal use\n"
            . "  throw new \\required_capability_exception(\$context, '<capability>', 'nopermissions', '');\n"
            . "otherwise add the string to your plugin's lang/en and lang/hi and pass your "
            . "component:\n  - " . implode("\n  - ", $new));

        $this->assertSame([], $stale,
            "baselined sites have been fixed - lower or remove their entries in "
            . "exception_strings_test::BASELINE so the list keeps shrinking:\n  - "
            . implode("\n  - ", $stale));
    }

    /**
     * Every plugin to scan, keyed by frankenstyle component.
     *
     * @return array<string,string> component => absolute directory
     */
    private function components(): array {
        $out = [];
        foreach (array_keys(\core_component::get_plugin_types()) as $type) {
            foreach (\core_component::get_plugin_list($type) as $name => $dir) {
                $component = $type . '_' . $name;
                if (strpos($name, 'sentientia') === 0) {
                    $out[$component] = $dir;
                }
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * PHP files under a plugin directory, excluding {@see self::SKIP_DIRS}.
     *
     * @param string $dir
     * @return string[] Absolute paths with forward slashes, sorted.
     */
    private function php_files(string $dir): array {
        $skip = self::SKIP_DIRS;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $file) use ($skip): bool {
                    if ($file->isDir()) {
                        return !in_array($file->getFilename(), $skip, true);
                    }
                    return strtolower($file->getExtension()) === 'php';
                }
            )
        );

        $files = [];
        foreach ($iterator as $file) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
        sort($files);
        return $files;
    }

    /**
     * Find moodle_exception / print_error calls whose key is a literal and
     * whose component resolves to core's error.php.
     *
     * Uses the PHP tokenizer, not a regex, so comments and string contents
     * cannot produce matches: a guard that fires on its own documentation
     * gets switched off.
     *
     * @param string $source PHP source code.
     * @return array<int,array{call:string,key:string,line:int}>
     */
    private function find_core_string_calls(string $source): array {
        $tokens = array_values(array_filter(token_get_all($source), static function ($token): bool {
            return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        }));

        $namekinds = [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED];
        $calls = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            $call = null;
            $open = 0;
            if ($token[0] === T_NEW) {
                $next = $tokens[$i + 1] ?? null;
                if (is_array($next) && in_array($next[0], $namekinds, true)) {
                    $class = strtolower(ltrim($next[1], '\\'));
                    if ($class === 'moodle_exception' || $class === 'core\\exception\\moodle_exception') {
                        $call = 'moodle_exception';
                        $open = $i + 2;
                    }
                }
            } else if (in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
                    && strtolower(ltrim($token[1], '\\')) === 'print_error') {
                $prev = $tokens[$i - 1] ?? null;
                // Skip a declaration and method calls; only the global function counts.
                if (is_array($prev) && in_array($prev[0], [T_FUNCTION, T_OBJECT_OPERATOR,
                        T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                    continue;
                }
                $call = 'print_error';
                $open = $i + 1;
            }

            if ($call === null || ($tokens[$open] ?? null) !== '(') {
                continue;
            }

            $keytoken = $tokens[$open + 1] ?? null;
            if (!is_array($keytoken) || $keytoken[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $afterkey = $tokens[$open + 2] ?? null;

            if ($afterkey === ')') {
                $component = '';
            } else if ($afterkey === ',') {
                $comptoken = $tokens[$open + 3] ?? null;
                $aftercomp = $tokens[$open + 4] ?? null;
                if ($aftercomp !== ',' && $aftercomp !== ')') {
                    continue;
                }
                if (is_array($comptoken) && $comptoken[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $component = $this->literal_value($comptoken[1]);
                } else if (is_array($comptoken) && $comptoken[0] === T_STRING
                        && strtolower($comptoken[1]) === 'null') {
                    $component = '';
                } else {
                    continue;
                }
            } else {
                // Concatenation or some other expression: not resolvable.
                continue;
            }

            if (!in_array($component, self::CORE_COMPONENTS, true)) {
                continue;
            }

            $calls[] = [
                'call' => $call,
                'key'  => $this->literal_value($keytoken[1]),
                'line' => (int) $keytoken[2],
            ];
        }

        return $calls;
    }

    /**
     * The runtime value of a T_CONSTANT_ENCAPSED_STRING token.
     *
     * @param string $literal Quoted literal as it appears in source.
     * @return string
     */
    private function literal_value(string $literal): string {
        $literal = ltrim($literal, 'bB');
        $body = substr($literal, 1, -1);
        if ($literal[0] === "'") {
            return strtr($body, ['\\\\' => '\\', "\\'" => "'"]);
        }
        return stripcslashes($body);
    }
}
