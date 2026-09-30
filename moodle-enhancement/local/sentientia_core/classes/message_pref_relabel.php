<?php
// This file is part of Sentientia LMS. GNU GPL v3 or later.

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Message-provider preference keys, relabelled for a renamed plugin
 * (step 1b of cli/relabel_plugin.php, ADR-025 Class B).
 *
 * Step 2 of the CLI relabels {message_providers}.component, but the provider's
 * site defaults ({config_plugins} plugin 'message') and each user's choice
 * ({user_preferences}) are keyed by NAMES that embed the component:
 *   <processor>_provider_<component>_<name>_locked   (message_send() throws if missing)
 *   message_provider_<component>_<name>_enabled      (site default and user choice)
 *   <component>_<name>_disable                       (admin's site-wide off switch)
 *
 * This renames the exact keys of the component's providers (never by prefix:
 * local_airpay_X_ is also the start of local_airpay_X_Y_...). An existing key
 * under the new name is kept and the old one left. Only the legacy processors
 * whose _locked key this run moves are carried into the new _enabled (their
 * locked value and enabled membership travel together): merged into it when it
 * exists; when it does not, the old key is renamed if that is every member,
 * else just those processors are written under the new name and the old key is
 * left. A user who already has the new name keeps it; their legacy row is left
 * and reported.
 *
 * Each provider is handled in one delegated transaction, so a run that dies
 * between its _locked renames and its _enabled key does not leave the provider
 * half moved (the _locked keys back, the _enabled membership lost).
 *
 * This can only rename what exists. Afterwards
 * local/sentientia_platform/cli/repair_task_registrations.php --apply is
 * REQUIRED: its step 2e (message_pref_repair) copies what is still stranded and
 * writes db/messages.php defaults for any provider still missing a _locked key,
 * and its step 4 exits 1 if any provider remains broken. It copies; it never
 * deletes the legacy keys this step leaves behind.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services / Sentientia LMS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_pref_relabel {

    /**
     * Relabel the preference keys of every provider whose component is $from or $to.
     *
     * @param string   $from the old component (local_airpay_X)
     * @param string   $to   the new component (local_sentientia_X)
     * @param bool     $run  false = report only, nothing is written
     * @param callable $out  called with one line of report text at a time (no newline)
     * @return array{config: int, users: int} config keys moved or written, user rows moved
     *         (dry run: what would be)
     */
    public static function relabel(string $from, string $to, bool $run, callable $out): array {
        global $DB;

        $counts = ['config' => 0, 'users' => 0];
        if (!$DB->get_manager()->table_exists(new \xmldb_table('message_providers'))) {
            return $counts;
        }

        [$insql, $inparams] = $DB->get_in_or_equal([$from, $to]);
        $provnames = array_unique($DB->get_fieldset_select('message_providers', 'name', "component {$insql}", $inparams));
        $procnames = $DB->get_fieldset_select('message_processors', 'name', '1 = 1');

        foreach ($provnames as $pname) {
            $transaction = $run ? $DB->start_delegated_transaction() : null;
            try {
                self::relabel_provider((string) $pname, $procnames, $from, $to, $run, $out, $counts);
            } catch (\Throwable $e) {
                if ($transaction) {
                    $transaction->rollback($e);  // rolls back and rethrows
                }
                throw $e;
            }
            if ($transaction) {
                $transaction->allow_commit();
            }
        }

        if ($run && $counts['config'] > 0) {
            // set_field bypasses set_config(), so invalidate the cached 'message' config
            // (what core's message_update_providers() does), not every cache on the site.
            \cache_helper::invalidate_by_definition('core', 'config', [], 'message');
        }
        return $counts;
    }

    /**
     * One provider: its _locked keys, _disable key, _enabled key, then its user rows.
     *
     * @param string   $pname
     * @param string[] $procnames names of the installed message processors
     * @param string   $from
     * @param string   $to
     * @param bool     $run
     * @param callable $out
     * @param array    $counts running totals, updated in place
     */
    private static function relabel_provider(string $pname, array $procnames, string $from, string $to,
            bool $run, callable $out, array &$counts): void {
        global $DB;

        $movedlocks = [];
        $pairs = [];
        foreach ($procnames as $proc) {
            $pairs[$proc] = ["{$proc}_provider_{$from}_{$pname}_locked", "{$proc}_provider_{$to}_{$pname}_locked"];
        }
        $pairs['_disable'] = ["{$from}_{$pname}_disable", "{$to}_{$pname}_disable"];
        foreach ($pairs as $proc => [$oldkey, $newkey]) {
            if (!self::has_key($oldkey)) {
                continue;
            }
            if (self::has_key($newkey)) {
                $out("  message default {$newkey}: already set (kept; {$oldkey} left)");
                continue;
            }
            $out("  message default {$oldkey} -> {$newkey}");
            if ($run) {
                $DB->set_field('config_plugins', 'name', $newkey, ['plugin' => 'message', 'name' => $oldkey]);
            }
            $counts['config']++;
            if ($proc !== '_disable') {
                $movedlocks[] = (string) $proc;
            }
        }

        // _enabled after the _locked keys: it carries only the processors whose
        // _locked key this run moved, so a lock and its enabled membership always
        // travel together (a processor whose new _locked key was already there, or
        // has no legacy one, is not put into the enabled list by this step).
        $oldkey = "message_provider_{$from}_{$pname}_enabled";
        $newkey = "message_provider_{$to}_{$pname}_enabled";
        $oldval = $DB->get_field('config_plugins', 'value', ['plugin' => 'message', 'name' => $oldkey]);
        if ($oldval !== false) {
            $newval = $DB->get_field('config_plugins', 'value', ['plugin' => 'message', 'name' => $newkey]);
            $oldlist = self::split_list($oldval);
            $add = array_values(array_intersect($oldlist, $movedlocks));
            if ($newval === false) {
                if ($add === $oldlist) {
                    // Every legacy member's lock moved: the whole key goes across.
                    $out("  message default {$oldkey} -> {$newkey}");
                    if ($run) {
                        $DB->set_field('config_plugins', 'name', $newkey, ['plugin' => 'message', 'name' => $oldkey]);
                    }
                    $counts['config']++;
                } else if ($add) {
                    // Only some did: write just those and leave the old key alone.
                    $value = implode(',', $add);
                    $out("  message default {$newkey}: new '{$value}' (legacy " . implode(',', $add)
                        . " only, the processors whose lock moved; {$oldkey} left)");
                    if ($run) {
                        $DB->insert_record('config_plugins',
                            (object) ['plugin' => 'message', 'name' => $newkey, 'value' => $value]);
                    }
                    $counts['config']++;
                } else {
                    $out("  message default {$oldkey}: none of its processors had a lock moved (left)");
                }
            } else {
                $current = self::split_list($newval);
                $merged = array_values(array_unique(array_merge($current, $add)));
                if ($merged !== $current) {
                    $value = implode(',', $merged);
                    $out("  message default {$newkey}: '{$newval}' -> '{$value}'"
                        . ' (merged legacy ' . implode(',', $add) . "; {$oldkey} left)");
                    if ($run) {
                        $DB->set_field('config_plugins', 'value', $value, ['plugin' => 'message', 'name' => $newkey]);
                    }
                    $counts['config']++;
                } else {
                    $out("  message default {$newkey}: already set (kept; {$oldkey} left)");
                }
            }
        }

        // Per-user choices use the message_provider_<component>_<name>_enabled name.
        $olduser = "message_provider_{$from}_{$pname}_enabled";
        $newuser = "message_provider_{$to}_{$pname}_enabled";
        $total = $DB->count_records('user_preferences', ['name' => $olduser]);
        if ($total > 0) {
            // Only rows whose user has no new-name row can move; the rest are leftovers.
            $movable = (int) $DB->count_records_sql(
                "SELECT COUNT(1) FROM {user_preferences} o
                  WHERE o.name = :old
                    AND NOT EXISTS (SELECT 1 FROM {user_preferences} n WHERE n.userid = o.userid AND n.name = :new)",
                ['old' => $olduser, 'new' => $newuser]);
            if ($movable > 0) {
                $out("  user_preferences {$olduser}: {$movable} row(s) -> {$newuser}");
            }
            if ($total > $movable) {
                $out("  user_preferences {$olduser}: " . ($total - $movable) . ' row(s) left'
                    . " (those users already have {$newuser}; kept)");
            }
            $counts['users'] += $movable;
            if ($run && $movable > 0) {
                // Row by row: an UPDATE ... WHERE userid NOT IN (SELECT ... FROM the same
                // table) is refused by MySQL (error 1093).
                foreach ($DB->get_records('user_preferences', ['name' => $olduser], 'id', 'id, userid') as $pref) {
                    if (!$DB->record_exists('user_preferences', ['userid' => $pref->userid, 'name' => $newuser])) {
                        $DB->set_field('user_preferences', 'name', $newuser, ['id' => $pref->id]);
                        mark_user_preferences_changed($pref->userid);  // live sessions reload preferences
                    }
                }
            }
        }
    }

    /** Does the 'message' plugin config have a key of this name? */
    private static function has_key(string $name): bool {
        global $DB;
        return $DB->record_exists('config_plugins', ['plugin' => 'message', 'name' => $name]);
    }

    /** A comma-separated config value as a list of non-empty, trimmed entries. */
    private static function split_list($value): array {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }
}
