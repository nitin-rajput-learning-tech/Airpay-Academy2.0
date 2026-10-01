<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * BizLMS local_interested_skills -> local_sentientia_skill_interest (MAP).
 *
 * BizLMS kept ONE comma-separated list of skill ids per learner and read it with get_record(), which returns the
 * first row it finds. So one row per learner counts (the lowest id); the learner's other rows are reported and
 * not imported. The list is split into one target row per skill; an id that is not a number, is repeated, or
 * names no imported skill is dropped and counted (skill_ids_dropped). BizLMS spliced the raw text into SQL, so
 * a list can hold anything.
 *
 * The group is a learner's rows (group_by usercreated). The winner's primary outcome is the first skill; the
 * others are sub-rows keyed skill:<legacy skill id>. The other rows of the learner are merged into the winner
 * when it imported something, and archived otherwise (a merge needs a winner that has a target).
 *
 * A row marked inactive (active 0) is a withdrawn list and is archived (withdrawn). The legacy open_costcenterid
 * is not copied; the preflight counts rows where it disagrees with the learner's current tenant.
 * usermodified is not copied.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class interests_step extends step {

    /** Most skill ids one list may name before the rest is dropped. */
    private const MAX_IDS = 500;

    public function key(): string {
        return 'skills.interests';
    }

    public function sourcetable(): string {
        return 'local_interested_skills';
    }

    public function targettable(): string {
        return 'local_sentientia_skill_interest';
    }

    public function group_by(): array {
        return ['usercreated'];
    }

    public function columns(): array {
        return ['id', 'interested_skill_ids', 'active', 'usercreated', 'timecreated', 'timemodified', 'open_costcenterid'];
    }

    public function preload(): array {
        return [['local_skill', '']];
    }

    public function transform(array $rows, context $ctx): array {
        $winner = reset($rows);
        $winnerid = (int) $winner->id;
        $userid = (int) $winner->usercreated;

        if ($userid <= 0 || !$ctx->lookups->user_exists($userid)) {
            // No learner (an empty usercreated groups every such row together): nobody's list to import.
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, 'orphan_user', 'user_not_found');
            }
            return $out;
        }

        $primary = $this->winner_outcomes($winner, $winnerid, $userid, $ctx);
        $imported = $primary[0]->kind === outcome::INSERT;

        $out = $primary;
        foreach (array_slice($rows, 1) as $duplicate) {
            $id = (int) $duplicate->id;
            $out[] = $imported ? outcome::merge($id, $winnerid, 'dup_learner_row') : outcome::archive($id, 'dup_learner_row');
        }
        return $out;
    }

    /**
     * The outcomes of the learner's lowest-id row: its primary outcome first, then its sub-rows.
     *
     * @param \stdClass $winner
     * @param int $winnerid
     * @param int $userid
     * @param context $ctx
     * @return outcome[]
     */
    private function winner_outcomes(\stdClass $winner, int $winnerid, int $userid, context $ctx): array {
        if ((int) $winner->active === 0) {
            return [outcome::archive($winnerid, 'withdrawn')];
        }

        $dropped = 0;
        $skills = [];
        $seen = [];
        $parts = explode(',', (string) $winner->interested_skill_ids);
        if (count($parts) > self::MAX_IDS) {
            $dropped += count($parts) - self::MAX_IDS;
            $parts = array_slice($parts, 0, self::MAX_IDS);
        }
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part) || (int) $part <= 0) {
                $dropped++;
                continue;
            }
            $legacy = (int) $part;
            if (isset($seen[$legacy])) {
                $dropped++;
                continue;
            }
            $seen[$legacy] = true;
            $target = $ctx->map->resolve('local_skill', $legacy);
            if ($target === null) {
                $dropped++;
                continue;
            }
            $skills[$legacy] = $target;
        }
        if (!$skills) {
            return [outcome::skip($winnerid, 'no_valid_skills')];
        }
        ksort($skills);

        $created = (int) $winner->timecreated;
        $modified = (int) ($winner->timemodified ?? 0);
        $derived = $modified <= 0;
        if ($derived) {
            $modified = $created;
        }
        $out = [];
        foreach ($skills as $legacy => $target) {
            $fields = (object) [
                'userid' => $userid,
                'skillid' => $target,
                'timecreated' => $created,
                'timemodified' => $modified,
            ];
            $out[] = $out ? outcome::insert($winnerid, $this->targettable(), $fields, 'skill:' . $legacy)
                : outcome::insert($winnerid, $this->targettable(), $fields);
        }
        if ($dropped > 0) {
            $out[0]->warn('skill_ids_dropped');
        }
        if ($derived) {
            $out[0]->warn('derived_timestamp');
        }
        return $out;
    }
}
