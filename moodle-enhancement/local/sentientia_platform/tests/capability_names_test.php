<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Platform-wide guard: a capability check must name a capability that exists.
 *
 * WHY THIS TEST EXISTS
 * --------------------
 * Persona pass 2026-09-30, defect D9. After ADR-024/025 retired the BizLMS
 * plugins, five pages and a helper still asked about the BizLMS names
 *
 *     has_capability('local/courses:manage', ...)       (now local/sentientia_courses:manage)
 *     has_capability('local/courses:enrol', ...)        (now local/sentientia_courses:enrol)
 *     has_capability('local/classroom:takesessionattendance', ...)
 *
 * Core answers an unknown capability with `false` plus a debugging() notice -
 * for EVERY caller, a site administrator included, because the existence check
 * runs before the admin bypass. Nothing errors. A tenant admin who held only
 * the new capability was silently treated as an ordinary user (no team picker,
 * no course-management shell), and the notice was logged on every course view.
 * Fixing sites one by one is not a fix, so this test walks the PHP of every
 * Sentientia plugin and asserts that each
 *
 *     has_capability('<literal>' ...)       require_capability('<literal>' ...)
 *     db/services.php  'capabilities' => '<literal>[, <literal>]'
 *
 * names a capability the site really declares (the same lookup core does,
 * get_capability_info()).
 *
 * WHAT COUNTS AS GUARDED
 * ----------------------
 * A file that itself asks get_capability_info('<same literal>') is deliberately
 * probing a legacy name that only exists where BizLMS is still installed
 * (compliance_report viewer_scope.php, block_sentientia_compliance audit.php).
 * Those are left alone: the guard is the fix. accesslib::legacy_cap() is the
 * same pattern as a helper; it is not a has_capability() call and is not scanned.
 *
 * WHAT IT DOES NOT SEE
 * --------------------
 * A capability built at runtime (a variable, an interpolated string) cannot be
 * resolved statically and is skipped. assign_capability() / role definitions
 * in cli/ scripts are not checked here (they throw, they do not silently pass).
 *
 * BASELINE
 * --------
 * The sites below were found by this test on its first run and belong to
 * plugins that other work is in flight on. They are real defects (the check
 * can never pass, for anyone) and are listed so the test fails on anything NEW
 * from day one. The list may only shrink: fix a site and the test fails until
 * its entry is lowered or removed, so the baseline cannot rot into a permanent
 * allowlist.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversNothing
 */
final class capability_names_test extends \advanced_testcase {

    /**
     * Global functions whose first argument is a capability name.
     *
     * @var string[]
     */
    private const CHECK_FUNCTIONS = ['has_capability', 'require_capability'];

    /**
     * Directory names, at any depth inside a plugin, that are not scanned.
     *
     * @var string[]
     */
    private const SKIP_DIRS = ['tests', 'lang', 'vendor', 'node_modules', 'amd', 'yui'];

    /**
     * Fewer checked sites than this means the scan is broken, not clean. When
     * written the top-level tree had about 670 literal checks (the
     * moodle-enhancement tree about the same).
     *
     * @var int
     */
    private const MIN_SITES = 300;

    /**
     * Known offenders: capability names checked by code but declared by no
     * db/access.php, as "<component>/<path inside plugin>|<capability>" =>
     * number of occurrences. Component-relative paths, no line numbers, so the
     * list holds whichever plugin tree is deployed.
     *
     * @var array<string,int>
     */
    private const BASELINE = [
        // (Bulk enrolment by audience gated on local/sentientia_classroom:enrol, which no access.php declares, so the
        // page, the form and both web services refused everyone. Fixed 2026-10-07, XC-CLS-ENROL: they gate on
        // :manage now, behind the flag sentientia.classroom.bulk_enrol_audience. The four entries are gone.)
        // Evaluation response list and detail gate on :view, which only :manage
        // and :respond exist next to. Declare :view (with a back-fill) or gate
        // on :manage; a product decision, out of scope for the persona fixes.
        'local_sentientia_evaluation/response_detail.php|local/sentientia_evaluation:view' => 1,
        'local_sentientia_evaluation/response_list.php|local/sentientia_evaluation:view' => 1,
    ];

    /**
     * Sites already fixed on another branch that is not merged into this one
     * yet. They may be present (before that merge) or absent (after it), so
     * unlike {@see self::BASELINE} they never go stale. Delete the entry once
     * the branch has landed.
     *
     * @var array<string,int>
     */
    private const PENDING_ELSEWHERE = [
        // claude/fixes-0930 (77e7fd0a9, c7b6cecb4) gates on
        // local/sentientia_classroom:attendance instead.
        'local_sentientia_pages/qr_attendance.php|local/classroom:takesessionattendance' => 1,
    ];

    /**
     * The scanner must recognise every call shape it claims to and skip the
     * ones it cannot resolve. Without this, a tokenizer regression would turn
     * the real scan into an empty pass.
     */
    public function test_scanner_recognises_the_call_shapes(): void {
        $source = <<<'PHP'
<?php
if (has_capability('a/one:view', $context)) {}
require_capability("b/two:manage", $ctx);
$x = \has_capability('c/three:edit', $c, $userid);
require_capability('d/four:do', $context, null, false, 'nopermissions');
has_capability ( 'e/five:spaced' , $ctx );

has_capability($dynamic, $context);
has_capability('x/interp:' . $suffix, $context);
has_capability("x/interp:{$suffix}", $context);
$user->has_capability('x/method:call', $ctx);
foo::require_capability('x/static:call', $ctx);
function has_capability($capability, $context) {}
// has_capability('x/linecomment:view', $ctx);
/* require_capability('x/blockcomment:view', $ctx); */
$s = "has_capability('x/insidestring:view', \$ctx)";
legacy_cap('x/legacyhelper:view', $ctx);
has_any_capability(['x/array:view'], $ctx);
PHP;

        $calls = $this->find_capability_calls($source);

        $this->assertSame(
            ['a/one:view', 'b/two:manage', 'c/three:edit', 'd/four:do', 'e/five:spaced'],
            array_column($calls, 'capability'));
        $this->assertSame(2, $calls[0]['line'], 'line numbers must point at the call');
        $this->assertSame('require_capability', $calls[1]['call']);
    }

    /**
     * db/services.php declares the capability a web service demands, as one
     * string that may list several, comma separated.
     */
    public function test_scanner_reads_service_capabilities(): void {
        $source = <<<'PHP'
<?php
$functions = [
    'x_a' => ['classname' => 'x', 'capabilities' => 'a/one:view'],
    'x_b' => ['classname' => 'x', 'capabilities' => 'a/one:view, b/two:manage'],
    'x_c' => ['classname' => 'x', 'capabilities' => ''],
    'x_d' => ['classname' => 'x'],
];
PHP;
        $this->assertSame(
            ['a/one:view', 'a/one:view', 'b/two:manage'],
            array_column($this->find_service_capabilities($source), 'capability'));
    }

    /**
     * Every literal capability checked by Sentientia code is declared -
     * apart from the shrinking baseline above.
     */
    public function test_capabilities_checked_by_sentientia_code_are_declared(): void {
        $components = $this->components();
        $this->assertGreaterThan(20, count($components),
            'found suspiciously few Sentientia plugins; the component walk is broken');

        $checked = 0;
        $undeclared = [];

        foreach ($components as $component => $dir) {
            $root = rtrim(str_replace('\\', '/', $dir), '/');
            foreach ($this->php_files($dir) as $file) {
                $source = file_get_contents($file);
                if ($source === false) {
                    continue;
                }
                $relative = substr($file, strlen($root) + 1);

                $sites = $this->find_capability_calls($source);
                if (substr($relative, -15) === 'db/services.php') {
                    $sites = array_merge($sites, $this->find_service_capabilities($source));
                }

                foreach ($sites as $site) {
                    $capability = $site['capability'];
                    $checked++;
                    if (get_capability_info($capability)) {
                        continue;
                    }
                    if ($this->probes_capability($source, $capability)) {
                        continue;
                    }
                    $undeclared["{$component}/{$relative}|{$capability}"][] = sprintf(
                        "%s/%s:%d %s('%s')", $component, $relative, $site['line'],
                        $site['call'], $capability);
                }
            }
        }

        $this->assertGreaterThanOrEqual(self::MIN_SITES, $checked,
            "only {$checked} capability checks were found; an empty scan would pass "
            . 'this test while proving nothing');

        $allowed = self::BASELINE + self::PENDING_ELSEWHERE;
        $new = [];
        foreach ($undeclared as $key => $locations) {
            if (count($locations) > ($allowed[$key] ?? 0)) {
                $new = array_merge($new, $locations);
            }
        }

        $stale = [];
        foreach (self::BASELINE as $key => $expected) {
            [$path] = explode('|', $key, 2);
            [$component, $relative] = explode('/', $path, 2);
            // A plugin not deployed in this environment cannot be judged.
            if (!isset($components[$component]) || !is_file($components[$component] . '/' . $relative)) {
                continue;
            }
            $now = count($undeclared[$key] ?? []);
            if ($now < $expected) {
                $stale[] = "{$key}: baseline says {$expected}, found {$now}";
            }
        }

        $this->assertSame([], $new,
            "these checks name a capability that no db/access.php declares. core's "
            . "has_capability() answers such a name with false (and a debugging notice) for "
            . "EVERY caller, site admins included. Use the current name - e.g. "
            . "local/courses:manage is now local/sentientia_courses:manage (ADR-025) - or, "
            . "for a legacy name that only exists where BizLMS is installed, probe it with "
            . "get_capability_info() first:\n  - " . implode("\n  - ", $new));

        $this->assertSame([], $stale,
            "baselined sites have been fixed - lower or remove their entries in "
            . "capability_names_test::BASELINE so the list keeps shrinking:\n  - "
            . implode("\n  - ", $stale));
    }

    /**
     * Does the file deliberately probe this capability with
     * get_capability_info() before using it?
     *
     * @param string $source PHP source.
     * @param string $capability Capability name.
     * @return bool
     */
    private function probes_capability(string $source, string $capability): bool {
        $quoted = preg_quote($capability, '/');
        return (bool) preg_match('/get_capability_info\(\s*[\'"]' . $quoted . '[\'"]\s*\)/', $source);
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
     * Find has_capability() / require_capability() calls whose first argument
     * is a literal capability name.
     *
     * Uses the PHP tokenizer, not a regex, so comments and string contents
     * cannot produce matches: a guard that fires on its own documentation gets
     * switched off.
     *
     * @param string $source PHP source code.
     * @return array<int,array{call:string,capability:string,line:int}>
     */
    private function find_capability_calls(string $source): array {
        $tokens = array_values(array_filter(token_get_all($source), static function ($token): bool {
            return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        }));

        $calls = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }
            $name = strtolower(ltrim($token[1], '\\'));
            if (!in_array($name, self::CHECK_FUNCTIONS, true)) {
                continue;
            }
            // Only the global function: not a declaration, a method or a static call.
            $prev = $tokens[$i - 1] ?? null;
            if (is_array($prev) && in_array($prev[0], [T_FUNCTION, T_OBJECT_OPERATOR,
                    T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW], true)) {
                continue;
            }
            if (($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }
            $arg = $tokens[$i + 2] ?? null;
            if (!is_array($arg) || $arg[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            // A bare literal, not the head of a concatenation or other expression.
            $after = $tokens[$i + 3] ?? null;
            if ($after !== ',' && $after !== ')') {
                continue;
            }
            $capability = substr($arg[1], 1, -1);
            if (!$this->looks_like_capability($capability)) {
                continue;
            }
            $calls[] = ['call' => $name, 'capability' => $capability, 'line' => (int) $arg[2]];
        }

        return $calls;
    }

    /**
     * Find the capabilities a db/services.php definition demands.
     *
     * @param string $source PHP source code of a db/services.php.
     * @return array<int,array{call:string,capability:string,line:int}>
     */
    private function find_service_capabilities(string $source): array {
        $found = [];
        if (!preg_match_all('/[\'"]capabilities[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/', $source, $matches,
                PREG_OFFSET_CAPTURE)) {
            return $found;
        }
        foreach ($matches[1] as [$list, $offset]) {
            $line = substr_count(substr($source, 0, (int) $offset), "\n") + 1;
            foreach (preg_split('/\s*,\s*/', trim($list)) as $capability) {
                if ($this->looks_like_capability($capability)) {
                    $found[] = ['call' => 'services.capabilities', 'capability' => $capability, 'line' => $line];
                }
            }
        }
        return $found;
    }

    /**
     * Same shape core insists on: lowercase "component/name:capability".
     *
     * @param string $value
     * @return bool
     */
    private function looks_like_capability(string $value): bool {
        return (bool) preg_match('/^[a-z0-9_]+\/[a-z0-9_]+:[a-z0-9_]+$/', $value);
    }
}
