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
 *  3. CLI maintenance mode is on (climaintenance.html; web maintenance does not count), unless
 *     --allow-online is given, which is refused when bizlms_production = 1 (the cutover
 *     runbook sets it);
 *  4. $CFG->noemailever is true;
 *  5. the scheduled-task runner is off (core cron_enabled = 0) and no task is
 *     running (the tripwire also detects a leak);
 *  6. the bizlms_import lock is taken through the core lock API;
 *  7. every target plugin is at or above requires_version() (registry);
 *  8. every decision the selected features need is present, and at cutover the
 *     decisions hash equals --expect-decisions-hash (runner preflight);
 *  9. preflight found no blocker (runner).
 *
 * This class covers 1 to 6 and the hash part of 8. It also issues the guard_permit the runner
 * demands: the conditions are not only a CLI courtesy, nothing writes without the permit.
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
     * Conditions that refuse the capability repair's --apply (it changes who may do what).
     *
     * @param array{confirm?: string, allow_online?: bool} $options
     * @return string[]
     */
    public static function refusals_for_repair(array $options): array {
        $fails = [];
        if (!defined('CLI_SCRIPT') || !CLI_SCRIPT) {
            $fails[] = 'not_a_cli_script';
        }
        $confirm = trim((string) ($options['confirm'] ?? ''));
        if ($confirm === '' || !hash_equals(fingerprint::install(), $confirm)) {
            $fails[] = 'confirm_does_not_match_the_install_fingerprint (see import_bizlms.php --status)';
        }
        if (!empty($options['allow_online'])) {
            if (self::is_production()) {
                $fails[] = 'allow_online_is_refused_when_bizlms_production_is_1';
            }
        } else if (!self::maintenance_on()) {
            $fails[] = 'maintenance_mode_is_off (admin/cli/maintenance.php --enable)';
        }
        return $fails;
    }

    /**
     * The permit the runner demands before it writes or deletes anything.
     *
     * @param string $kind guard_permit::APPLY or guard_permit::PURGE.
     * @param string[] $refusals What refusals_for_apply() or refusals_for_purge() returned for this call.
     * @return guard_permit
     * @throws guard_refused When there is any refusal.
     */
    public static function permit(string $kind, array $refusals): guard_permit {
        if ($refusals) {
            throw new guard_refused(implode('; ', $refusals));
        }
        return guard_permit::issue($kind);
    }

    /**
     * A permit for a PHPUnit test, which exercises the runner without the guard's site state.
     *
     * @param string $kind guard_permit::APPLY or guard_permit::PURGE.
     * @return guard_permit
     * @throws \coding_exception Outside PHPUnit.
     */
    public static function test_permit(string $kind = guard_permit::APPLY): guard_permit {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('guard::test_permit() is for PHPUnit only');
        }
        return guard_permit::issue($kind);
    }

    /**
     * Take the import lock.
     *
     * With the default file or session-backed lock factories the lock goes when the process dies. With
     * $CFG->lock_factory = \core\lock\db_record_lock_factory it does not: a run killed with SIGKILL leaves
     * the row behind until LOCK_LIFETIME (12 hours) passes. The refusal then says how old the newest
     * running run's heartbeat is; a heartbeat that stopped long ago, or no running run at all, means the
     * holder is dead. Release the row only after confirming that no import process is alive (ps on the host).
     *
     * @return \core\lock\lock
     * @throws guard_refused When another import holds it.
     */
    public static function acquire_lock(): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        $lock = $factory->get_lock(self::LOCK, 0, self::LOCK_LIFETIME);
        if (!$lock) {
            throw new guard_refused('another_bizlms_import_holds_the_lock' . self::stale_lock_hint());
        }
        return $lock;
    }

    /**
     * What the operator needs to tell a live import from a dead one that still holds the lock.
     *
     * @return string A parenthesised hint, or an empty string when the run table is not there.
     */
    private static function stale_lock_hint(): string {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_sentientia_legacyrun')) {
            return '';
        }
        $runs = $DB->get_records_select('local_sentientia_legacyrun', "status = 'running'", [], 'heartbeat DESC',
            'id, heartbeat', 0, 1);
        if (!$runs) {
            return ' (no run is marked running, so the holder is probably a killed process; see acquire_lock())';
        }
        return ' (newest running run: heartbeat ' . max(0, time() - (int) reset($runs)->heartbeat) . 's ago)';
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
     * Is CLI maintenance mode on? Only climaintenance.html counts (admin/cli/maintenance.php --enable).
     *
     * $CFG->maintenance_enabled is the WEB maintenance mode: it lets administrators log in and edit the
     * very tables the import is writing. ADR-032 gating item 3 names CLI maintenance, which shuts the site
     * for everyone.
     *
     * @return bool
     */
    public static function maintenance_on(): bool {
        global $CFG;
        return file_exists($CFG->dataroot . '/climaintenance.html');
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
