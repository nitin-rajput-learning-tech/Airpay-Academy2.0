<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Discovers, validates and orders the importers (ADR-032, "Importer interface").
 *
 * Each target plugin declares its importers in db/bizlms_import.php, the way
 * db/feature_flags.php files are discovered:
 *
 *     $imports = ['classroom' => \local_sentientia_classroom\bizlms\importer::class];
 *
 * load() walks the installed plugins without the MUC cache (this runs from the
 * CLI only) and refuses to return anything when:
 *  - a feature key is duplicated;
 *  - a legacy table is claimed or declined by two importers (one owner per table);
 *  - a dependency is unknown or cyclic;
 *  - a class does not implement importer;
 *  - an installed plugin is below requires_version();
 *  - a step breaks the id-policy rule: a MAP step must not declare
 *    external_refs(), a PRESERVE step must;
 *  - a MAP step writes into a table a PRESERVE step of the feature owns (a MAP insert
 *    can take a legacy id in the middle of a run, and the next batch then collides);
 *  - a target table is not defined by the importer component's own schema (db/install.xml or
 *    classes/schema TABLES), is a legacy table (known, detected, or claimed or declined by any
 *    importer) or is a framework table: an importer never writes another feature's tables;
 *  - a core_writes() table is not on CORE_WRITES_ALLOWED, the list reviewed against ADR-032;
 *  - the importer or any of its steps is defined outside the plugin's classes/bizlms/
 *    (the static scan reads that directory and nothing else);
 *  - an importer that declares tenant_columns() does not have TENANT_OWNER (the org feature) in its
 *    dependency closure. Tenant resolution reads the organisation table, which the org importer fills;
 *    without the dependency the alphabetical tie-break can run the reader first, has_orgs() is false,
 *    and resolve() then stores any path whose root is valid. Discovery from disk also refuses a
 *    tenant importer when no org feature is registered; a test registry that has none is not checked.
 *
 * Tests register toy importers through set_testing_importers(), which replaces
 * disk discovery.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {

    /**
     * Core tables an importer may declare in core_writes(), each with the operations reviewed for it and the
     * ADR-032 decision or mapping-doc section that reviewed them. A table that is not listed cannot be declared,
     * and a listed table can be written only in the listed ways: adding a table or an operation is an amendment
     * to the ADR and a change to this constant, never a line in an importer. The writer enforces the operations,
     * so an importer that declares course (for the open_* backfill UPDATE) cannot raw-insert course rows with no
     * context or sections, and one that declares tag_instance (for a remap) cannot insert tag instances.
     *
     * 'insert' is a new row, 'update' a change of an existing row (writer::update_core()). Nothing here may be
     * adopted, purged or updated as "the import's own row": those are for the importer's target tables.
     *
     * The import never writes course completions, grades, logs, messages or any other history table
     * of core (ADR decision 1 and the rejected alternative "import into core tables").
     *
     * @var array<string, array{operations: string[], why: string}>
     */
    public const CORE_WRITES_ALLOWED = [
        'course' => [
            'operations' => ['update'],
            'why' => 'course_lookups: the open_* backfill (mapping doc, course_lookups)',
        ],
        'enrol' => [
            'operations' => ['insert', 'update'],
            'why' => 'gap.orphan_enrol_instances (G6): a manual instance for a course that has none',
        ],
        'role_assignments' => [
            'operations' => ['insert', 'update'],
            'why' => 'org_roles: the role assignments of the org role tables (mapping doc, org_roles)',
        ],
        'tag_instance' => [
            'operations' => ['update'],
            'why' => 'course_tags: the in-place remap of tag instances (mapping doc, course_tags)',
        ],
        'user_enrolments' => [
            'operations' => ['insert', 'update'],
            'why' => 'gap.orphan_enrol_instances (G6): orphaned enrolments become manual enrolments',
        ],
    ];

    /** Feature key of the importer that fills the organisation table that tenant resolution reads. */
    public const TENANT_OWNER = 'org';

    /**
     * The operations reviewed for a core table.
     *
     * @param string $table
     * @return string[] Empty for a table that is not on CORE_WRITES_ALLOWED.
     */
    public static function core_write_operations(string $table): array {
        return self::CORE_WRITES_ALLOWED[$table]['operations'] ?? [];
    }

    /** @var importer[]|null Importers injected by a test; null means discover on disk. */
    private static ?array $testing = null;

    /**
     * Replace disk discovery with these importers (tests only).
     *
     * @param importer[]|null $importers Null restores discovery.
     * @return void
     */
    public static function set_testing_importers(?array $importers): void {
        self::$testing = $importers === null ? null : array_values($importers);
    }

    /**
     * Load and validate every importer.
     *
     * @return array<string, importer> feature => importer, in discovery order
     * @throws registry_error
     */
    public static function load(): array {
        $problems = [];
        $found = [];

        if (self::$testing !== null) {
            foreach (self::$testing as $importer) {
                $found[] = [$importer->feature(), $importer];
            }
        } else {
            foreach (self::discover($problems) as $pair) {
                $found[] = $pair;
            }
        }

        $importers = [];
        foreach ($found as [$key, $importer]) {
            if ($importer->feature() !== $key) {
                $problems[] = "feature_key_mismatch:{$key}";
                continue;
            }
            if (isset($importers[$key])) {
                $problems[] = "duplicate_feature:{$key}";
                continue;
            }
            $importers[$key] = $importer;
        }

        self::validate($importers, $problems);

        if ($problems) {
            throw new registry_error($problems);
        }
        return $importers;
    }

    /**
     * Features in execution order: dependencies first, ties broken by feature
     * key so the order is deterministic.
     *
     * @param array<string, importer> $importers As returned by load().
     * @param string[] $features Wanted features; empty means all.
     * @param bool $withdependencies Add the dependencies of the wanted features
     *        (apply runs). A single-feature dry run leaves them out so that
     *        unresolved parents are reported as deferred.
     * @return string[]
     * @throws registry_error
     */
    public static function sorted(array $importers, array $features = [], bool $withdependencies = true): array {
        $wanted = $features ? array_values(array_unique($features)) : array_keys($importers);
        $problems = [];
        foreach ($wanted as $feature) {
            if (!isset($importers[$feature])) {
                $problems[] = "unknown_feature:{$feature}";
            }
        }
        if ($problems) {
            throw new registry_error($problems);
        }

        if ($withdependencies) {
            $queue = $wanted;
            $wanted = [];
            while ($queue) {
                $feature = array_shift($queue);
                if (isset($wanted[$feature])) {
                    continue;
                }
                $wanted[$feature] = $feature;
                foreach ($importers[$feature]->depends() as $dependency) {
                    if (!isset($importers[$dependency])) {
                        throw new registry_error(["unknown_dependency:{$feature}->{$dependency}"]);
                    }
                    $queue[] = $dependency;
                }
            }
            $wanted = array_values($wanted);
        }

        // Kahn's algorithm over the wanted set.
        $remaining = [];
        foreach ($wanted as $feature) {
            $remaining[$feature] = array_values(array_intersect($importers[$feature]->depends(), $wanted));
        }
        $order = [];
        while ($remaining) {
            $ready = [];
            foreach ($remaining as $feature => $deps) {
                if (!$deps) {
                    $ready[] = $feature;
                }
            }
            if (!$ready) {
                throw new registry_error(['cyclic_dependency:' . implode(',', array_keys($remaining))]);
            }
            sort($ready);
            $next = $ready[0];
            $order[] = $next;
            unset($remaining[$next]);
            foreach ($remaining as $feature => $deps) {
                $remaining[$feature] = array_values(array_diff($deps, [$next]));
            }
        }
        return $order;
    }

    /**
     * Every feature each feature depends on, directly or through others.
     *
     * @param array<string, importer> $importers
     * @return array<string, array<string, bool>> feature => set of features it (transitively) depends on
     */
    public static function dependency_closure(array $importers): array {
        $out = [];
        foreach ($importers as $feature => $importer) {
            $seen = [];
            $queue = $importer->depends();
            while ($queue) {
                $dependency = (string) array_shift($queue);
                if (isset($seen[$dependency]) || !isset($importers[$dependency])) {
                    continue;
                }
                $seen[$dependency] = true;
                foreach ($importers[$dependency]->depends() as $next) {
                    $queue[] = $next;
                }
            }
            $out[$feature] = $seen;
        }
        return $out;
    }

    /**
     * Walk installed plugins for db/bizlms_import.php files.
     *
     * @param string[] $problems Collects problems.
     * @return array<array{0: string, 1: importer}>
     */
    private static function discover(array &$problems): array {
        $found = [];
        foreach (\core_component::get_plugin_types() as $type => $typedir) {
            foreach (\core_component::get_plugin_list($type) as $name => $plugindir) {
                $file = $plugindir . '/db/bizlms_import.php';
                if (!is_readable($file)) {
                    continue;
                }
                foreach (self::include_registry($file) as $key => $class) {
                    if (!is_string($key) || !is_string($class) || !class_exists($class)) {
                        $problems[] = "importer_class_missing:{$type}_{$name}:" . (is_string($key) ? $key : '?');
                        continue;
                    }
                    if (!in_array(importer::class, class_implements($class) ?: [], true)) {
                        $problems[] = "not_an_importer:{$class}";
                        continue;
                    }
                    $found[] = [$key, new $class()];
                }
            }
        }
        return $found;
    }

    /**
     * Include one registry file in a tight scope.
     *
     * @param string $file
     * @return array
     */
    private static function include_registry(string $file): array {
        $imports = [];
        include $file;
        return is_array($imports) ? $imports : [];
    }

    /**
     * Cross-importer and per-importer validation.
     *
     * @param array<string, importer> $importers
     * @param string[] $problems
     * @return void
     */
    private static function validate(array $importers, array &$problems): void {
        $tableowner = [];
        $stepkeys = [];
        $testing = self::$testing !== null;

        // Every legacy table any importer claims or declines, and every legacy table in the database: a target
        // is never one of them.
        $legacy = array_flip(legacy_tables::detect());
        foreach ($importers as $importer) {
            foreach (array_merge(array_keys($importer->sources()), array_keys($importer->declined_tables())) as $table) {
                $legacy[$table] = true;
            }
        }

        foreach ($importers as $feature => $importer) {
            // What goes into the framework tables must fit their columns, or the failure comes mid-run.
            if (strlen($feature) > 40) {
                $problems[] = "feature_key_too_long:{$feature}";
            }
            foreach (array_merge(array_keys($importer->sources()), array_keys($importer->declined_tables())) as $table) {
                if (strlen($table) > 64) {
                    $problems[] = "source_table_name_too_long:{$feature}:{$table}";
                }
            }
            foreach ($importer->reasons() as $reason) {
                if ($reason instanceof reason && strlen($reason->code) > 40) {
                    $problems[] = "reason_code_too_long:{$feature}:{$reason->code}";
                }
            }

            // Installed plugin version.
            $installed = (int) get_config($importer->component(), 'version');
            if ($installed < $importer->requires_version()) {
                $problems[] = "plugin_below_required_version:{$importer->component()}";
            }

            // One owner per legacy table.
            $claimed = array_keys($importer->sources());
            $declined = array_keys($importer->declined_tables());
            foreach ($claimed as $table) {
                if (in_array($table, $declined, true)) {
                    $problems[] = "table_claimed_and_declined:{$feature}:{$table}";
                }
            }
            foreach (array_merge($claimed, $declined) as $table) {
                if (isset($tableowner[$table]) && $tableowner[$table] !== $feature) {
                    $problems[] = "table_owned_twice:{$table}:{$tableowner[$table]},{$feature}";
                }
                $tableowner[$table] = $feature;
            }

            // Dependencies.
            foreach ($importer->depends() as $dependency) {
                if (!isset($importers[$dependency])) {
                    $problems[] = "unknown_dependency:{$feature}->{$dependency}";
                }
            }

            // Declared tables: a legacy table or a framework table is never a target, and a target belongs
            // to the importer's own plugin.
            $targets = $importer->target_tables();
            $own = self::own_tables($importer->component(), $testing);
            foreach ($targets as $table) {
                if (in_array($table, legacy_tables::KNOWN, true) || isset($legacy[$table])
                        || strncmp($table, 'local_sentientia_legacy', 23) === 0) {
                    $problems[] = "target_is_read_only:{$feature}:{$table}";
                } else if (!isset($own[$table])) {
                    $problems[] = "target_not_in_the_plugin_schema:{$feature}:{$table}";
                }
            }
            foreach ($importer->tenant_columns() as $table => $column) {
                if (!in_array($table, $targets, true)) {
                    $problems[] = "tenant_column_on_undeclared_table:{$feature}:{$table}";
                }
            }
            foreach ($importer->core_writes() as $table => $why) {
                if (trim((string) $why) === '') {
                    $problems[] = "core_write_without_reason:{$feature}:{$table}";
                }
                if (!isset(self::CORE_WRITES_ALLOWED[$table])) {
                    $problems[] = "core_write_not_reviewed:{$feature}:{$table}";
                }
            }
            $location = self::code_location_problem($importer, $importer->component(), $testing);
            if ($location !== null) {
                $problems[] = "importer_{$location}:{$feature}:" . self::class_name($importer);
            }
            foreach ($importer->reasons() as $reason) {
                if (!($reason instanceof reason)) {
                    $problems[] = "not_a_reason:{$feature}";
                }
            }
            foreach ($importer->decisions() as $decision) {
                if (!($decision instanceof decision)) {
                    $problems[] = "not_a_decision:{$feature}";
                }
            }

            // Steps.
            $steps = $importer->steps();
            if (!$steps) {
                $problems[] = "no_steps:{$feature}";
            }
            $preserve = [];
            foreach ($steps as $step) {
                if ($step instanceof step && $step->idpolicy() === idpolicy::PRESERVE) {
                    if (isset($preserve[$step->targettable()])) {
                        $problems[] = "preserve_steps_share_a_target:{$preserve[$step->targettable()]},{$step->key()}";
                    }
                    $preserve[$step->targettable()] = $step->key();
                }
            }
            foreach ($steps as $step) {
                if (!($step instanceof step) && !($step instanceof recompute_step)) {
                    $problems[] = "not_a_step:{$feature}";
                    continue;
                }
                $key = $step->key();
                $location = self::code_location_problem($step, $importer->component(), $testing);
                if ($location !== null) {
                    $problems[] = "step_{$location}:{$key}:" . self::class_name($step);
                }
                if ($step instanceof step && $step->idpolicy() === idpolicy::MAP && isset($preserve[$step->targettable()])) {
                    // A MAP insert takes max(id)+1, which on MySQL is the next legacy id the PRESERVE step is
                    // about to write.
                    $problems[] = "map_step_targets_a_preserve_table:{$key}:{$step->targettable()}";
                }
                if (strncmp($key, $feature . '.', strlen($feature) + 1) !== 0) {
                    $problems[] = "step_key_without_feature_prefix:{$key}";
                }
                if (strlen($key) > 64) {
                    $problems[] = "step_key_too_long:{$key}";
                }
                if (isset($stepkeys[$key])) {
                    $problems[] = "duplicate_step_key:{$key}";
                }
                $stepkeys[$key] = true;
                if (!in_array($step->targettable(), $targets, true)) {
                    $problems[] = "step_target_not_declared:{$key}:{$step->targettable()}";
                }
                if ($step instanceof step) {
                    self::validate_load_step($feature, $importer, $step, $problems);
                }
            }
        }

        // Tenant resolution reads the organisation table the org importer fills.
        $closure = self::dependency_closure($importers);
        foreach ($importers as $feature => $importer) {
            if ($feature === self::TENANT_OWNER || !$importer->tenant_columns()) {
                continue;
            }
            if (!isset($importers[self::TENANT_OWNER])) {
                if (!$testing) {
                    $problems[] = 'tenant_owner_not_registered:' . $feature . ':' . self::TENANT_OWNER;
                }
                continue;
            }
            if (!isset($closure[$feature][self::TENANT_OWNER])) {
                $problems[] = 'tenant_resolution_needs_org:' . $feature . ':' . self::TENANT_OWNER;
            }
        }

        // Cycles.
        try {
            self::sorted($importers, [], false);
        } catch (registry_error $e) {
            $problems = array_merge($problems, $e->problems);
        }
    }

    /**
     * Why an object's class is not where the static scan reads: null when it is under the plugin's
     * classes/bizlms/ (and, for test importers, tests/classes/bizlms/).
     *
     * The scan walks classes/bizlms/ and nothing else, and a db/bizlms_import.php may name any class
     * while steps() may return objects of any class, so the location of the code is checked here.
     * The file is read from the class, so an anonymous class is judged where it is written.
     *
     * @param object $object An importer or a step.
     * @param string $component Owning plugin of the importer.
     * @param bool $testing Also accept tests/classes/bizlms/ (the toy importer lives there).
     * @return string|null Problem code, or null when the class is where it should be.
     */
    public static function code_location_problem(object $object, string $component, bool $testing = false): ?string {
        $dir = \core_component::get_component_directory($component);
        $file = (new \ReflectionClass($object))->getFileName();
        if (!$dir || $file === false || realpath($dir) === false || realpath($file) === false) {
            return 'code_location_unknown';
        }
        $dir = str_replace('\\', '/', (string) realpath($dir));
        $file = str_replace('\\', '/', (string) realpath($file));
        $allowed = [$dir . '/classes/bizlms/'];
        if ($testing) {
            $allowed[] = $dir . '/tests/classes/bizlms/';
        }
        foreach ($allowed as $prefix) {
            if (strncmp($file, $prefix, strlen($prefix)) === 0) {
                return null;
            }
        }
        return 'code_outside_classes_bizlms';
    }

    /**
     * Tables a plugin defines itself: its db/install.xml and the TABLES constants of its classes/schema
     * (the convention privacy_coverage_test reads). A test registry also gets the tables of the
     * checked-in fixture copies under tests/fixtures/bizlms/.
     *
     * @param string $component
     * @param bool $testing
     * @return array<string, bool>
     */
    private static function own_tables(string $component, bool $testing): array {
        $dir = \core_component::get_component_directory($component);
        if (!$dir) {
            return [];
        }
        $files = [$dir . '/db/install.xml'];
        if ($testing) {
            $files = array_merge($files, glob($dir . '/tests/fixtures/bizlms/*.install.xml') ?: []);
        }
        $tables = [];
        foreach ($files as $file) {
            $raw = is_readable($file) ? file_get_contents($file) : false;
            if ($raw !== false && preg_match_all('/<TABLE\s+NAME="([^"]+)"/', $raw, $m)) {
                foreach ($m[1] as $table) {
                    $tables[$table] = true;
                }
            }
        }
        foreach (glob($dir . '/classes/schema/*.php') ?: [] as $file) {
            $class = '\\' . $component . '\\schema\\' . basename($file, '.php');
            if (!class_exists($class) || !(new \ReflectionClass($class))->hasConstant('TABLES')) {
                continue;
            }
            foreach ((array) (new \ReflectionClass($class))->getConstant('TABLES') as $table) {
                if (is_string($table)) {
                    $tables[$table] = true;
                }
            }
        }
        return $tables;
    }

    /**
     * A class name that is safe to put in a problem line: an anonymous class name carries a NUL and a path.
     *
     * @param object $object
     * @return string
     */
    private static function class_name(object $object): string {
        return explode("\0", get_class($object))[0];
    }

    /**
     * @param string $feature
     * @param importer $importer
     * @param step $step
     * @param string[] $problems
     * @return void
     */
    private static function validate_load_step(string $feature, importer $importer, step $step, array &$problems): void {
        $key = $step->key();
        if (strlen($step->sourcetable()) > 64) {
            $problems[] = "sourcetable_too_long:{$key}";
        }
        if (!array_key_exists($step->physical_table(), $importer->sources())) {
            $problems[] = "step_source_not_claimed:{$key}:{$step->physical_table()}";
        }
        if (!in_array($step->idpolicy(), idpolicy::all(), true)) {
            $problems[] = "unknown_id_policy:{$key}";
            return;
        }
        $refs = $step->external_refs();
        if ($step->idpolicy() === idpolicy::MAP && $refs) {
            $problems[] = "map_step_declares_external_refs:{$key}";
        }
        if ($step->idpolicy() === idpolicy::PRESERVE && !$refs) {
            $problems[] = "preserve_step_without_external_refs:{$key}";
        }
        $children = $step->target_children();
        if ($step->idpolicy() === idpolicy::MAP && $children) {
            // A MAP step takes new ids, so no child can already name one of its legacy ids.
            $problems[] = "map_step_declares_target_children:{$key}";
        }
        foreach ($children as $child) {
            try {
                if (!is_array($child) || count($child) !== 2 || !is_string($child[0] ?? null) || !is_string($child[1] ?? null)) {
                    throw new \coding_exception('target_children entry is not [table, column]');
                }
                fingerprint::assert_identifier($child[0]);
                fingerprint::assert_identifier($child[1]);
            } catch (\coding_exception $e) {
                $problems[] = "target_children_malformed:{$key}";
                break;
            }
        }
        if ($step->is_derived() && !$step->group_by()) {
            $problems[] = "derived_source_without_group_by:{$key}";
        }
        if ($step->idpolicy() === idpolicy::PRESERVE && ($step->is_derived() || $step->group_by())) {
            $problems[] = "preserve_step_cannot_be_grouped:{$key}";
        }
    }
}
