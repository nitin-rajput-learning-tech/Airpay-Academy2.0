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
 *    external_refs(), a PRESERVE step must.
 *
 * Tests register toy importers through set_testing_importers(), which replaces
 * disk discovery.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {

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

            // Declared tables: a legacy table or a framework table is never a target.
            $targets = $importer->target_tables();
            foreach ($targets as $table) {
                if (in_array($table, legacy_tables::KNOWN, true) || strncmp($table, 'local_sentientia_legacy', 23) === 0) {
                    $problems[] = "target_is_read_only:{$feature}:{$table}";
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
            foreach ($steps as $step) {
                if (!($step instanceof step) && !($step instanceof recompute_step)) {
                    $problems[] = "not_a_step:{$feature}";
                    continue;
                }
                $key = $step->key();
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

        // Cycles.
        try {
            self::sorted($importers, [], false);
        } catch (registry_error $e) {
            $problems = array_merge($problems, $e->problems);
        }
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
        if ($step->is_derived() && !$step->group_by()) {
            $problems[] = "derived_source_without_group_by:{$key}";
        }
        if ($step->idpolicy() === idpolicy::PRESERVE && ($step->is_derived() || $step->group_by())) {
            $problems[] = "preserve_step_cannot_be_grouped:{$key}";
        }
    }
}
