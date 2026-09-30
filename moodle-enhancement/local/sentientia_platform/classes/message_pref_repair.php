<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Message-provider preference check + repair after the ADR-025 component rename.
 *
 * relabel_plugin.php renamed {message_providers}.component in place
 * (local_airpay_X -> local_sentientia_X), but the provider's preferences are
 * keyed by NAMES that embed the component, and those were left behind:
 *
 *   {config_plugins} plugin 'message' (site defaults, read by message_send()):
 *     <processor>_provider_<component>_<name>_locked   one per processor
 *     message_provider_<component>_<name>_enabled      csv of processors on by default
 *     <component>_<name>_disable                       admin's site-wide off switch
 *   {user_preferences}:
 *     message_provider_<component>_<name>_enabled      the user's own choice
 *
 * message_send() throws coding_exception ("Could not load preference ...")
 * when the _locked key of an enabled processor is missing. On the local copy
 * that was 28 of 30 Sentientia providers, and cart_manager::mark_paid()
 * rolled back. The other keys fail silently: a lost _disable switches a
 * notification back on site-wide, a stranded user row loses that user's
 * opt-out.
 *
 * Only the _locked keys decide whether a provider is broken. _enabled is
 * absent on purpose when nothing is on by default, or when an admin turned
 * every processor off (core unsets it), so its absence is never a defect.
 *
 * Repair (copy-only; never deletes, never overwrites a key that exists):
 *   1. For each processor whose new _locked key is missing, copy the legacy
 *      _locked value and, as a unit with it, that processor's membership in
 *      the legacy _enabled list (merged into the new _enabled; existing
 *      entries are kept). The _enabled merge is written first, so a run that
 *      dies half-way re-derives the same result next time.
 *   2. Copy the legacy _disable key if the new one is absent, even when the
 *      provider is otherwise healthy. No default is ever written for it: an
 *      absent key means enabled, which is what a fresh install has.
 *   3. Move each user's legacy _enabled row to the new name unless that user
 *      already has the new name (the newer choice wins; the legacy row is
 *      left and reported as shadowed).
 *   4. Only if a _locked key is still missing after 1: write Moodle's own
 *      default from the plugin's db/messages.php, per processor
 *      (message_set_default_message_preference(), what a fresh install does).
 *
 * A healthy fresh install (UAT, the production install path) reports 0
 * copied, 0 moved, 0 defaulted, and check() returns nothing.
 *
 * Callers: local/sentientia_platform/cli/repair_task_registrations.php
 * (step 2e + the step 4 gate) and cli/migration_parity_check.php (hard fail).
 *
 * @package local_sentientia_platform
 */
class message_pref_repair {

    /**
     * Pre-rename component names that are NOT local_airpay_<same suffix>
     * (null = no pre-rename name). Everything else under local_sentientia_*
     * was local_airpay_<suffix>, or was always Sentientia, in which case no
     * legacy keys exist and the lookups simply find nothing.
     * local_sentientia_core was always Sentientia: without its null entry it
     * would claim local_airpay_core, which is local_sentientia_platform's.
     */
    const LEGACY_NAMES = [
        'local_sentientia_platform' => 'local_airpay_core',
        'local_sentientia_core'     => null,
    ];

    /**
     * Pre-rename component name for a Sentientia component, or null.
     *
     * Returns null when the would-be legacy component is itself installed:
     * its keys are then its own, not leftovers to copy.
     *
     * @param string $component e.g. local_sentientia_cart
     * @return string|null e.g. local_airpay_cart
     */
    public static function legacy_component(string $component): ?string {
        if (array_key_exists($component, self::LEGACY_NAMES)) {
            $legacy = self::LEGACY_NAMES[$component];
        } else if (strpos($component, 'local_sentientia_') === 0) {
            $legacy = 'local_airpay_' . substr($component, strlen('local_sentientia_'));
        } else {
            return null;
        }
        if ($legacy === null || \core_component::get_component_directory($legacy) !== null) {
            return null;
        }
        return $legacy;
    }

    /**
     * Read-only: what is wrong right now. Empty array = healthy.
     *
     * Reports, for providers of ANY installed component:
     *  - a missing <proc>_provider_<component>_<name>_locked for any processor
     *    message_send() will use (get_message_processors(true)) - it throws;
     * and for local_sentientia_* providers with a legacy name:
     *  - a legacy _disable key not carried to the new name;
     *  - legacy user preference rows that could be moved (user has no
     *    new-name row) but were not.
     *
     * @return string[] one line per problem
     */
    public static function check(): array {
        self::requires();
        $problems = [];
        $config = self::config();
        $ready = self::ready_processor_names();
        $legacyuser = self::legacy_user_pref_names();

        foreach (self::providers() as $p) {
            $base = $p->component . '_' . $p->name;
            foreach ($ready as $proc) {
                $key = "{$proc}_provider_{$base}_locked";
                if (!isset($config->{$key})) {
                    $problems[] = "{$p->component}/{$p->name}: no {$proc} default ({$key}) - message_send() throws";
                }
            }
            $legacy = self::legacy_component($p->component);
            if ($legacy === null) {
                continue;
            }
            $oldbase = $legacy . '_' . $p->name;
            $olddis = $oldbase . '_disable';
            $newdis = $base . '_disable';
            if (isset($config->{$olddis}) && !isset($config->{$newdis})) {
                $problems[] = "{$p->component}/{$p->name}: site-wide switch {$olddis} ('"
                    . $config->{$olddis} . "') not carried to {$newdis}";
            }
            $olduser = "message_provider_{$oldbase}_enabled";
            if (isset($legacyuser[$olduser])) {
                $n = self::count_movable($olduser, "message_provider_{$base}_enabled");
                if ($n > 0) {
                    $problems[] = "{$p->component}/{$p->name}: {$n} user choice(s) still under {$olduser}";
                }
            }
        }
        return $problems;
    }

    /**
     * Repair what check() reports (and the legacy _locked values of processors
     * that are currently disabled, so enabling one later cannot break sending).
     *
     * @param bool $apply false = dry run: report what would change, write nothing
     * @param callable $out function(string $line): void, receives progress/REPORT lines
     * @return array{copied:int, moved:int, shadowed:int, defaulted:int, unresolved:int, errors:int, unmapped:int}
     *   copied     config keys written from legacy names (_locked, merged _enabled, _disable)
     *   moved      user preference rows renamed to the new name
     *   shadowed   legacy user rows left because the user already has the new name
     *   defaulted  providers given db/messages.php defaults (apply: only if config changed)
     *   unresolved providers still missing a _locked key with nothing to repair it from
     *   errors     providers (or processors) that threw; reported, run continued
     *   unmapped   local_airpay_* message keys / user pref names with no counterpart
     */
    public static function repair(bool $apply, callable $out): array {
        self::requires();
        $counts = ['copied' => 0, 'moved' => 0, 'shadowed' => 0, 'defaulted' => 0,
            'unresolved' => 0, 'errors' => 0, 'unmapped' => 0];
        $allprocs = self::all_processor_names();
        $legacyuser = self::legacy_user_pref_names();
        $knownbases = [];

        foreach (self::providers() as $p) {
            $knownbases[$p->component . '_' . $p->name] = true;
            $legacy = self::legacy_component($p->component);
            if ($legacy !== null) {
                $knownbases[$legacy . '_' . $p->name] = true;
            }
            try {
                self::repair_provider($p, $legacy, $allprocs, $legacyuser, $apply, $out, $counts);
            } catch (\Throwable $e) {
                $counts['errors']++;
                $out("  ERROR {$p->component}/{$p->name}: " . $e->getMessage() . ' - skipped, run continues');
            }
        }

        $counts['unmapped'] = self::report_unmapped($knownbases, $legacyuser, $out);
        return $counts;
    }

    /**
     * Repair one provider. See the class docblock for the order of steps.
     *
     * @param \stdClass $p provider row (component, name)
     * @param string|null $legacy pre-rename component, or null
     * @param string[] $allprocs every {message_processors} name
     * @param array $legacyuser legacy user-pref name => row count
     * @param bool $apply
     * @param callable $out
     * @param array $counts updated in place
     */
    private static function repair_provider(\stdClass $p, ?string $legacy, array $allprocs,
            array $legacyuser, bool $apply, callable $out, array &$counts): void {
        global $CFG;
        // Fresh read per provider: set_config() invalidates the core/config
        // 'message' cache, so this sees what earlier providers wrote.
        $config = self::config();
        $base = $p->component . '_' . $p->name;
        $label = "{$p->component}/{$p->name}";

        $lockmissing = [];
        foreach ($allprocs as $proc) {
            if (!isset($config->{"{$proc}_provider_{$base}_locked"})) {
                $lockmissing[] = $proc;
            }
        }

        if ($legacy !== null) {
            $oldbase = $legacy . '_' . $p->name;

            // 1. _locked + _enabled membership, per processor, as a unit.
            $oldenabled = self::csv($config->{"message_provider_{$oldbase}_enabled"} ?? '');
            $newenabled = self::csv($config->{"message_provider_{$base}_enabled"} ?? '');
            $copylocks = [];
            $addenabled = [];
            foreach ($lockmissing as $proc) {
                $old = "{$proc}_provider_{$oldbase}_locked";
                if (isset($config->{$old})) {
                    $copylocks[$proc] = (string) $config->{$old};
                    if (in_array($proc, $oldenabled, true)) {
                        $addenabled[] = $proc;
                    }
                }
            }
            if ($addenabled) {
                $merged = array_values(array_unique(array_merge($newenabled, $addenabled)));
                if ($merged !== $newenabled) {
                    $value = implode(',', $merged);
                    $out(($apply ? '  set ' : '  would set ') . "message_provider_{$base}_enabled = '{$value}'"
                        . ' (from legacy: ' . implode(',', $addenabled) . ')');
                    if ($apply) {
                        set_config("message_provider_{$base}_enabled", $value, 'message');
                    }
                    $counts['copied']++;
                }
            }
            foreach ($copylocks as $proc => $value) {
                $out(($apply ? '  copied ' : '  would copy ')
                    . "{$proc}_provider_{$oldbase}_locked -> {$proc}_provider_{$base}_locked = '{$value}'");
                if ($apply) {
                    set_config("{$proc}_provider_{$base}_locked", $value, 'message');
                }
                $counts['copied']++;
            }
            $lockmissing = array_values(array_diff($lockmissing, array_keys($copylocks)));

            // 2. The admin's site-wide off switch. Copy-only.
            $olddis = "{$oldbase}_disable";
            $newdis = "{$base}_disable";
            if (isset($config->{$olddis}) && !isset($config->{$newdis})) {
                $value = (string) $config->{$olddis};
                $out(($apply ? '  copied ' : '  would copy ') . "{$olddis} -> {$newdis} = '{$value}'");
                if ($apply) {
                    set_config($newdis, $value, 'message');
                }
                $counts['copied']++;
            }

            // 3. Users' own choices.
            $olduser = "message_provider_{$oldbase}_enabled";
            if (isset($legacyuser[$olduser])) {
                [$moved, $shadowed] = self::move_user_rows($olduser, "message_provider_{$base}_enabled", $apply);
                if ($moved > 0) {
                    $out(($apply ? '  moved ' : '  would move ')
                        . "{$moved} user preference row(s) {$olduser} -> message_provider_{$base}_enabled");
                }
                if ($shadowed > 0) {
                    $out("  REPORT: {$shadowed} legacy row(s) {$olduser} left: those users already have"
                        . " message_provider_{$base}_enabled (kept)");
                }
                $counts['moved'] += $moved;
                $counts['shadowed'] += $shadowed;
            }
        }

        if (!$lockmissing) {
            return;
        }

        // 4. Fallback: db/messages.php defaults for the _locked keys still missing.
        $file = message_get_providers_from_file($p->component);
        if (!isset($file[$p->name])) {
            $out("  REPORT: {$label} has no default for " . implode(', ', $lockmissing)
                . ' and no entry in db/messages.php - left as-is');
            $counts['unresolved']++;
            return;
        }
        $writable = [];
        foreach ($lockmissing as $proc) {
            // message_set_default_message_preference() -> get_message_processor()
            // throws for a processor row whose code is gone (a restored DB).
            if (!is_readable("{$CFG->dirroot}/message/output/{$proc}/message_output_{$proc}.php")) {
                $out("  REPORT: {$label}: processor '{$proc}' has no code on disk - no default written for it");
                continue;
            }
            $writable[] = $proc;
        }
        if (!$writable) {
            return;
        }
        $out(($apply ? '  wrote defaults ' : '  would write defaults ') . "{$label} ("
            . implode(', ', $writable) . ') from db/messages.php');
        if (!$apply) {
            $counts['defaulted']++;
            return;
        }
        $before = (array) self::config();
        foreach ($writable as $proc) {
            try {
                message_set_default_message_preference($p->component, $p->name, $file[$p->name], $proc);
            } catch (\Throwable $e) {
                $counts['errors']++;
                $out("  ERROR {$label} ({$proc} default): " . $e->getMessage() . ' - continuing');
            }
        }
        if ((array) self::config() != $before) {
            $counts['defaulted']++;
        }
    }

    /**
     * Move legacy user rows to the new name, row by row. A user who already has
     * the new name keeps it (their newer choice) and the legacy row stays.
     *
     * Row by row because UPDATE ... WHERE userid NOT IN (SELECT ... FROM the same
     * table) is refused by MySQL (error 1093), and the unique (userid, name)
     * index must never be hit.
     *
     * @return int[] [moved, shadowed]
     */
    private static function move_user_rows(string $old, string $new, bool $apply): array {
        global $DB;
        $moved = 0;
        $shadowed = 0;
        foreach ($DB->get_records('user_preferences', ['name' => $old], 'id', 'id, userid') as $pref) {
            if ($DB->record_exists('user_preferences', ['userid' => $pref->userid, 'name' => $new])) {
                $shadowed++;
                continue;
            }
            if ($apply) {
                $DB->set_field('user_preferences', 'name', $new, ['id' => $pref->id]);
                mark_user_preferences_changed($pref->userid);
            }
            $moved++;
        }
        return [$moved, $shadowed];
    }

    /**
     * REPORT local_airpay_* message keys and user preference names that belong
     * to no installed provider and no Sentientia provider's legacy name.
     * Report-only: they are left as they are.
     *
     * @param array $knownbases <component>_<name> => true, installed + legacy
     * @param array $legacyuser legacy user-pref name => row count
     * @param callable $out
     * @return int number of unmapped keys/names
     */
    private static function report_unmapped(array $knownbases, array $legacyuser, callable $out): int {
        $unmapped = 0;
        foreach ((array) self::config() as $key => $value) {
            if (strpos($key, 'local_airpay_') === false) {
                continue;
            }
            $base = self::key_base($key);
            if ($base !== null && isset($knownbases[$base])) {
                continue;
            }
            $out("  REPORT: message default {$key} has no Sentientia counterpart - left as-is");
            $unmapped++;
        }
        foreach ($legacyuser as $name => $n) {
            $base = null;
            if (preg_match('/^message_provider_(.+)_enabled$/', $name, $m)) {
                $base = $m[1];
            }
            if ($base !== null && isset($knownbases[$base])) {
                continue;
            }
            $out("  REPORT: user preference {$name} ({$n} row(s)) has no Sentientia counterpart - left as-is");
            $unmapped++;
        }
        return $unmapped;
    }

    /**
     * The <component>_<name> part of a message config key, or null.
     */
    private static function key_base(string $key): ?string {
        if (preg_match('/^message_provider_(.+)_enabled$/', $key, $m)) {
            return $m[1];
        }
        if (preg_match('/^[a-z0-9]+_provider_(.+)_locked$/', $key, $m)) {
            return $m[1];
        }
        if (preg_match('/^(.+)_disable$/', $key, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Provider rows whose component is installed. Rows of a component that is
     * gone never send (repair_task_registrations.php step 2c purges them).
     *
     * @return \stdClass[]
     */
    private static function providers(): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('message_providers', null, 'component, name', 'id, component, name') as $p) {
            if (\core_component::get_component_directory($p->component) === null) {
                continue;
            }
            $out[] = $p;
        }
        return $out;
    }

    /**
     * Legacy user preference names, with row counts, in one query.
     *
     * @return array name => count
     */
    private static function legacy_user_pref_names(): array {
        global $DB;
        $like = $DB->sql_like('name', ':n');
        return $DB->get_records_sql_menu(
            "SELECT name, COUNT(1) AS n FROM {user_preferences} WHERE {$like} GROUP BY name",
            ['n' => 'message\_provider\_local\_airpay\_%']);
    }

    /**
     * Legacy rows whose user has no row under the new name (i.e. movable).
     */
    private static function count_movable(string $old, string $new): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {user_preferences} o
              WHERE o.name = :old
                AND NOT EXISTS (SELECT 1 FROM {user_preferences} n
                                 WHERE n.userid = o.userid AND n.name = :new)",
            ['old' => $old, 'new' => $new]);
    }

    /**
     * Every processor row, enabled or not: a disabled processor with no
     * _locked key breaks sending the day an admin enables it.
     *
     * @return string[]
     */
    private static function all_processor_names(): array {
        global $DB;
        return array_values($DB->get_fieldset_select('message_processors', 'name', '1 = 1'));
    }

    /**
     * The processors message_send() iterates: enabled and configured.
     *
     * @return string[]
     */
    private static function ready_processor_names(): array {
        global $DB;
        try {
            return array_keys(get_message_processors(true));
        } catch (\Throwable $e) {
            // A processor whose code is broken on disk: fall back to the enabled rows.
            return array_values($DB->get_fieldset_select('message_processors', 'name', 'enabled = 1'));
        }
    }

    /**
     * Site message defaults: the same object message_send() reads.
     */
    private static function config(): \stdClass {
        return (object) get_config('message');
    }

    /**
     * Split a processor csv, dropping empties.
     *
     * @return string[]
     */
    private static function csv(string $value): array {
        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }

    /**
     * Core message libraries this class calls into.
     */
    private static function requires(): void {
        global $CFG;
        require_once($CFG->libdir . '/messagelib.php');
        require_once($CFG->dirroot . '/message/lib.php');
    }
}
