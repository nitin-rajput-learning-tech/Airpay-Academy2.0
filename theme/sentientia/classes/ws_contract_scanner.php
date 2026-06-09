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

namespace theme_sentientia;

defined('MOODLE_INTERNAL') || die();

/**
 * Scans every airpay-plugin mustache for data-region="airpay-datatable"
 * references, resolves each to its WS endpoint, and verifies the WS
 * accepts the full shared-client contract.
 *
 * Lifted out of tests/ws_contract_test.php so the same logic can run
 * from the CLI (admin smoke) AND PHPUnit (CI gate). Both call the
 * static methods here.
 *
 * Background: Bug #6 (My Requests stuck on Loading) + Bug #10 (5 sibling
 * endpoints drifted) showed that the shared theme_sentientia/datatable
 * client always POSTs {search, sort, sortdir, page, perpage, filters},
 * and Moodle's strict external_function_parameters validator rejects
 * unknown keys. Every consumer WS must therefore declare all 6 with
 * VALUE_DEFAULT.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ws_contract_scanner {

    /**
     * Keys the shared theme_sentientia/datatable AMD client always POSTs.
     */
    public const REQUIRED_CONTRACT_KEYS = [
        'search',
        'sort',
        'sortdir',
        'page',
        'perpage',
        'filters',
    ];

    /**
     * Walk every airpay-plugin mustache, find every element with
     * data-region="airpay-datatable" AND data-ws-name="X", return
     * X => [list of source files].
     *
     * @return array<string, list<string>>
     */
    public static function scan_consumers(): array {
        global $CFG;

        $templates = self::find_files($CFG->dirroot . '/local', '.mustache');
        $consumers = [];

        foreach ($templates as $path) {
            // Limit scope to airpay plugins.
            $normalised = str_replace('\\', '/', $path);
            if (!str_contains($normalised, '/local/airpay_')) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            if (!preg_match_all(
                    '/data-region\s*=\s*"airpay-datatable"/i',
                    $content, $hits, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($hits[0] as $hit) {
                $offset = $hit[1];
                $window = substr($content, max(0, $offset - 400), 800);
                if (preg_match(
                        '/data-ws-name\s*=\s*"([a-z0-9_]+)"/i',
                        $window, $wsm)) {
                    $wsname = $wsm[1];
                    $relpath = str_replace(
                        $CFG->dirroot . DIRECTORY_SEPARATOR, '', $path);
                    $consumers[$wsname][] = $relpath;
                    $consumers[$wsname] = array_values(
                        array_unique($consumers[$wsname]));
                }
            }
        }
        ksort($consumers);
        return $consumers;
    }

    /**
     * For a WS function name, return the list of REQUIRED_CONTRACT_KEYS
     * missing from execute_parameters(), or null if the class is not
     * loadable in this environment (e.g. plugin disabled).
     *
     * @return list<string>|null
     */
    public static function missing_keys(string $wsname): ?array {
        $classinfo = self::resolve_class($wsname);
        if ($classinfo === null) {
            return null;
        }
        [$classname, $methodname] = $classinfo;
        if (!class_exists($classname)) {
            return null;
        }
        $paramsmethod = $methodname . '_parameters';
        if (!method_exists($classname, $paramsmethod)) {
            return null;
        }
        try {
            $params = $classname::$paramsmethod();
        } catch (\Throwable $e) {
            return null;
        }
        if (!($params instanceof \core_external\external_function_parameters)) {
            return null;
        }
        $declared = array_keys($params->keys);
        $missing = [];
        foreach (self::REQUIRED_CONTRACT_KEYS as $key) {
            if (!in_array($key, $declared, true)) {
                $missing[] = $key;
            }
        }
        return $missing;
    }

    /**
     * Resolve a WS function name to [classname, methodname] via the owning
     * plugin's db/services.php.
     *
     * @return array{0:string,1:string}|null
     */
    public static function resolve_class(string $wsname): ?array {
        global $CFG;
        $servicefiles = glob($CFG->dirroot . '/local/airpay_*/db/services.php');
        if (!is_array($servicefiles)) {
            return null;
        }
        foreach ($servicefiles as $servicefile) {
            $functions = self::load_services_file($servicefile);
            if (isset($functions[$wsname])
                    && !empty($functions[$wsname]['classname'])) {
                // `methodname` is optional in Moodle's services.php —
                // when omitted it defaults to 'execute'. Several airpay
                // plugins use this convention, so we must too.
                return [
                    $functions[$wsname]['classname'],
                    $functions[$wsname]['methodname'] ?? 'execute',
                ];
            }
        }
        return null;
    }

    /**
     * Load a Moodle plugin db/services.php and return its $functions array.
     * Isolated scope so the file's variables don't leak into the caller.
     */
    private static function load_services_file(string $path): array {
        $functions = [];
        include $path;
        return is_array($functions) ? $functions : [];
    }

    /**
     * Find all files in $dir matching $suffix, recursively.
     * @return list<string>
     */
    private static function find_files(string $dir, string $suffix): array {
        $out = [];
        if (!is_dir($dir)) {
            return $out;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isFile()
                    && str_ends_with($file->getFilename(), $suffix)) {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }

    /**
     * Run the full audit + return a structured result.
     *   ok:        true if no failures
     *   consumers: scan result (name => [files])
     *   failures:  name => [missing keys]
     *   skipped:   names of WS that couldn't be resolved (plugin disabled)
     *
     * @return array{ok:bool, consumers:array, failures:array, skipped:list<string>}
     */
    public static function audit(): array {
        $consumers = self::scan_consumers();
        $failures = [];
        $skipped = [];
        foreach ($consumers as $wsname => $sources) {
            $missing = self::missing_keys($wsname);
            if ($missing === null) {
                $skipped[] = $wsname;
                continue;
            }
            if (!empty($missing)) {
                $failures[$wsname] = $missing;
            }
        }
        return [
            'ok' => empty($failures),
            'consumers' => $consumers,
            'failures' => $failures,
            'skipped' => $skipped,
        ];
    }
}
