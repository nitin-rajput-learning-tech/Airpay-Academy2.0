<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skillsai\task;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_skillsai\gap_engine;

/**
 * Scheduled task — rebuild every active learner's skills-gap feed.
 *
 * No-ops unless sentientia.skillsai.gap_engine is ON. Bounded: processes
 * at most BATCH_SIZE users per run so a large tenant doesn't blow the cron
 * window — the next run picks up where this left off (cheapest-first by
 * stale gap-feed timestamp). Makes ZERO Anthropic calls — the gap engine
 * is pure DB maths over already-extracted/approved skill data.
 *
 * @package local_sentientia_skillsai
 */
class rebuild_gap_feed extends \core\task\scheduled_task {

    /** Max users processed per cron run. */
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

        [$insql, $params] = $DB->get_in_or_equal($designations, SQL_PARAMS_NAMED, 'd');
        $params['deleted'] = 0;
        $params['suspended'] = 0;
        $users = $DB->get_records_select('user',
            "open_designation $insql AND deleted = :deleted AND suspended = :suspended",
            $params, 'id ASC', 'id, open_designation', 0, self::BATCH_SIZE);

        $processed = 0;
        foreach ($users as $u) {
            gap_engine::rebuild_for_user((int)$u->id, (string)$u->open_designation);
            $processed++;
        }

        mtrace("local_sentientia_skillsai: rebuilt gap feed for {$processed} users.");
    }
}
