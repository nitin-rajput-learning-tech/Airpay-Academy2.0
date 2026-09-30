<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The CLI guard of the import (ADR-032, "Gating").
 *
 * The import is a one-time, whole-site data operation with no user-visible
 * surface, so a feature flag is the wrong control: an admin can flip a flag from
 * a web page, the Switchboard's tenant and customer scopes mean nothing here,
 * and set() is a toggle while an import is an event. The gate is this guard.
 *
 * --apply refuses (exit 3, naming the missing condition) unless ALL hold:
 *  1. CLI_SCRIPT, and --confirm equals the install fingerprint printed by
 *     --status, so a command copied from a rehearsal cannot run on production;
 *  2. local_sentientia_platform/bizlms_import_armed_until is later than now
 *     (the operator sets it with admin/cli/cfg.php; it expires on its own);
 *  3. CLI maintenance mode is on, unless --allow-online is given, which is
 *     refused when bizlms_production = 1 (the cutover runbook sets it);
 *  4. $CFG->noemailever is true;
 *  5. the scheduled-task runner is off (core cron_enabled = 0) and no task is
 *     running (the tripwire also detects a leak);
 *  6. the bizlms_import lock is taken through the core lock API;
 *  7. every target plugin is at or above requires_version() (registry);
 *  8. every decision the selected features need is present, and at cutover the
 *     decisions hash equals --expect-decisions-hash (runner preflight);
 *  9. preflight found no blocker (runner).
 *
 * This class covers 1 to 6 and the hash part of 8.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guard {

    /** Plugin that owns the guard's config keys. */
    public const COMPONENT = 'local_sentientia_platform';

    /** Lock resource name. */
    public const LOCK = 'bizlms_import';

    /** Longest an import may hold the lock, in seconds. */
    private const LOCK_LIFETIME = 43200;

    /**
     * Conditions that refuse --apply (and --resume, --retry-skipped).
     *
     * @param array{confirm?: string, allow_online?: bool, decisions_hash?: string, expect_hash?: string} $options
     * @return string[] Refusal reasons; empty means every checked condition holds.
     */
    public static function refusals_for_apply(array $options): array {
        global $CFG;
        $fails = [];

        if (!defined('CLI_SCRIPT') || !CLI_SCRIPT) {
            $fails[] = 'not_a_cli_script';
        }
        $confirm = trim((string) ($options['confirm'] ?? ''));
        if ($confirm === '' || !hash_equals(fingerprint::install(), $confirm)) {
            $fails[] = 'confirm_does_not_match_the_install_fingerprint (see --status)';
        }
        if ((int) get_config(self::COMPONENT, 'bizlms_import_armed_until') <= time()) {
            $fails[] = 'guard_not_armed (set local_sentientia_platform/bizlms_import_armed_until to a later time with admin/cli/cfg.php)';
        }

        $production = self::is_production();
        if (!empty($options['allow_online'])) {
            if ($production) {
                $fails[] = 'allow_online_is_refused_when_bizlms_production_is_1';
            }
        } else if (!self::maintenance_on()) {
            $fails[] = 'maintenance_mode_is_off (admin/cli/maintenance.php --enable)';
        }

        if (empty($CFG->noemailever)) {
            $fails[] = 'noemailever_is_off';
        }
        // Fail closed: an absent row means the admin setting's default, which is ON.
        $cron = get_config('core', 'cron_enabled');
        if ($cron === false || $cron === null || (int) $cron !== 0) {
            $fails[] = 'scheduled_task_runner_is_on (cron_enabled must be explicitly 0)';
        }
        if (self::running_tasks() > 0) {
            $fails[] = 'a_scheduled_or_adhoc_task_is_running';
        }

        $expect = trim((string) ($options['expect_hash'] ?? ''));
        $actual = (string) ($options['decisions_hash'] ?? '');
        if ($production && $expect === '') {
            $fails[] = 'expect_decisions_hash_is_required_when_bizlms_production_is_1';
        }
        if ($expect !== '' && !hash_equals($actual, $expect)) {
            $fails[] = 'decisions_hash_differs_from_the_expected_one';
        }
        return $fails;
    }

    /**
     * Conditions that refuse --purge-feature (rehearsal only).
     *
     * @param array{confirm?: string, understood?: bool} $options
     * @return string[]
     */
    public static function refusals_for_purge(array $options): array {
        $fails = [];
        if (!defined('CLI_SCRIPT') || !CLI_SCRIPT) {
            $fails[] = 'not_a_cli_script';
        }
        $confirm = trim((string) ($options['confirm'] ?? ''));
        if ($confirm === '' || !hash_equals(fingerprint::install(), $confirm)) {
            $fails[] = 'confirm_does_not_match_the_install_fingerprint (see --status)';
        }
        if (self::is_production()) {
            $fails[] = 'purge_is_refused_when_bizlms_production_is_1';
        }
        if (empty($options['understood'])) {
            $fails[] = 'i_understand_this_deletes_is_missing';
        }
        return $fails;
    }

    /**
     * Take the import lock. Released automatically if the process dies.
     *
     * @return \core\lock\lock
     * @throws guard_refused When another import holds it.
     */
    public static function acquire_lock(): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $factory->get_lock(self::LOCK, 0, self::LOCK_LIFETIME);
        if (!$lock) {
            throw new guard_refused('another_bizlms_import_holds_the_lock');
        }
        return $lock;
    }

    /**
     * Has the runbook declared this a production database?
     *
     * @return bool
     */
    public static function is_production(): bool {
        return (int) get_config(self::COMPONENT, 'bizlms_production') === 1;
    }

    /**
     * Is CLI maintenance mode on?
     *
     * @return bool
     */
    public static function maintenance_on(): bool {
        global $CFG;
        return !empty($CFG->maintenance_enabled) || file_exists($CFG->dataroot . '/climaintenance.html');
    }

    /**
     * Number of scheduled or ad-hoc tasks running right now.
     *
     * @return int
     */
    public static function running_tasks(): int {
        return count(\core\task\manager::get_running_tasks());
    }

    /**
     * The facts --status prints.
     *
     * @return array<string, mixed>
     */
    public static function state(): array {
        global $CFG;
        $armed = (int) get_config(self::COMPONENT, 'bizlms_import_armed_until');
        return [
            'fingerprint' => fingerprint::install(),
            'armed_seconds_left' => max(0, $armed - time()),
            'production' => self::is_production(),
            'production_open' => (int) get_config(self::COMPONENT, 'bizlms_production_open') > 0,
            'maintenance' => self::maintenance_on(),
            'noemailever' => !empty($CFG->noemailever),
            'cron_enabled' => (bool) get_config('core', 'cron_enabled'),
            'running_tasks' => self::running_tasks(),
        ];
    }
}
