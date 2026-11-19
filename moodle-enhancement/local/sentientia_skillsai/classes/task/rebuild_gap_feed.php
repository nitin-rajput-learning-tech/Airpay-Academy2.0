<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_skillsai\gap_engine;

/**
 * Scheduled task — rebuild every active learner's skills-gap feed.
 *
 * No-ops unless sentientia.skillsai.gap_engine is ON. Walks EVERY active user
 * whose designation has role requirements, BATCH_SIZE users per query (keyset
 * on user id, so memory stays flat), until none are left. Makes ZERO Anthropic
 * calls — the gap engine is pure DB maths over already-extracted/approved
 * skill data.
 *
 * Until 2026-11-19 (post-demo plan 2.4) a run read one batch only: the first
 * BATCH_SIZE users by id, every night. This docblock promised that the next
 * run would pick up where the last one stopped, but nothing stored where it
 * stopped, so from the 501st matching user on nobody's gap feed was rebuilt.
 *
 * @package local_sentientia_skillsai
 */
class rebuild_gap_feed extends \core\task\scheduled_task {

    /** Users read per query; the run goes on batch by batch until all are done. */
    public const BATCH_SIZE = 500;

    public function get_name(): string {
        return get_string('task_rebuild_gap_feed', 'local_sentientia_skillsai');
    }

    public function execute(): void {
        global $DB;

        if (!class_exists('\\local_sentientia_platform\\feature_flags')
                || !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.gap_engine')) {
            mtrace('local_sentientia_skillsai: gap_engine flag OFF — skipping.');
            return;
        }

        // Only rebuild for users who already have at least one role
        // requirement OR an existing gap row — avoids scanning the whole
        // user table. The role-skills table keys on designation, so we
        // collect distinct designations then their users.
        $designations = $DB->get_fieldset_sql(
            "SELECT DISTINCT designation FROM {local_sentientia_role_skills}");
        if (empty($designations)) {
            mtrace('local_sentientia_skillsai: no role requirements — nothing to rebuild.');
            return;
        }

        // Resolve users by designation only when the production column
        // exists. In its absence (e.g. fresh install) we no-op gracefully.
        $manager = $DB->get_manager();
        $table = new \xmldb_table('user');
        $field = new \xmldb_field('open_designation');
        if (!$manager->field_exists($table, $field)) {
            mtrace('local_sentientia_skillsai: user.open_designation absent — skipping rebuild.');
            return;
        }

        $result = self::rebuild_all($designations);

        mtrace("local_sentientia_skillsai: rebuilt gap feed for {$result['processed']} users"
            . " in {$result['batches']} batch(es) of up to " . self::BATCH_SIZE . '.');
        if ($result['failed'] > 0) {
            mtrace("local_sentientia_skillsai: {$result['failed']} user(s) failed; see the lines above.");
        }
    }

    /**
     * Rebuild the gap feed of every active (not deleted, not suspended) user
     * whose open_designation is one of $designations, in user id order,
     * $batchsize users per query, until a query returns fewer than $batchsize.
     *
     * One user's failure is reported and skipped, not fatal: it must not cost
     * every user after it their rebuild. Each user's rebuild is its own
     * transaction (gap_engine::rebuild_for_user()), so a failed one leaves that
     * user's previous feed in place.
     *
     * The caller checks that user.open_designation exists.
     *
     * @param string[] $designations designations that have role requirements
     * @param int $batchsize users per query (tests pass a small one)
     * @return array{processed: int, batches: int, failed: int}
     *         processed = rebuilt; batches = queries that returned users
     */
    public static function rebuild_all(array $designations, int $batchsize = self::BATCH_SIZE): array {
        global $DB;

        $result = ['processed' => 0, 'batches' => 0, 'failed' => 0];
        if (empty($designations)) {
            return $result;
        }
        $batchsize = max(1, $batchsize);

        [$insql, $inparams] = $DB->get_in_or_equal(array_values($designations), SQL_PARAMS_NAMED, 'd');
        $lastid = 0;
        do {
            $params = $inparams + ['deleted' => 0, 'suspended' => 0, 'lastid' => $lastid];
            $users = $DB->get_records_select('user',
                "open_designation $insql AND deleted = :deleted AND suspended = :suspended AND id > :lastid",
                $params, 'id ASC', 'id, open_designation', 0, $batchsize);
            if (empty($users)) {
                break;
            }
            $result['batches']++;

            foreach ($users as $u) {
                $lastid = (int) $u->id;
                try {
                    gap_engine::rebuild_for_user($lastid, (string) $u->open_designation);
                    $result['processed']++;
                } catch (\Throwable $e) {
                    $result['failed']++;
                    mtrace("local_sentientia_skillsai: gap feed rebuild failed for user id {$lastid}: "
                        . $e->getMessage());
                }
            }
        } while (count($users) === $batchsize);

        return $result;
    }
}
