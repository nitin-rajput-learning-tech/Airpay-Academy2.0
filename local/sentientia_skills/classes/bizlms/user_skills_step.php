<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * What learners already earned: completions of skill-mapped courses -> local_sentientia_user_skills and
 * local_sentientia_user_skill_hist (MAP).
 *
 * BizLMS had no skill level per learner; it showed "achieved" as a completed course (timecompleted set) for a
 * course that teaches the skill. Sentientia keeps a level per learner and skill plus a history of each change,
 * so the import derives both from the same completions, in one chronological pass per learner and skill, the
 * way skills_manager::update_from_course() would have (a level is only ever raised):
 *
 * - current_level is the highest level any completed course of the skill teaches; source_id is the course that
 *   set it; timecreated is the learner's earliest completion, timemodified the completion that set the level.
 * - one history row for every completion that raised the level: previous_level (0 for the first), new_level,
 *   the course, the completion time, no acting user. A completion that does not raise the level writes none.
 * - with decision skills.history_from_archive, completions the recompletion plugin archived before it reset
 *   them (local_recompletion_cc) are events too, so a reset never erases what the learner earned.
 * - source is the owner's label (decision skills.source_label: import or course).
 *
 * The accounting unit is the learner's completion rows (#course_completions.userid): one group per learner,
 * keyed by the lowest completion id of the group, never the learner (the map holds no personal data). Users
 * whose only completions were archived and whose course_completions rows are gone have no group; the
 * preflight counts them.
 *
 * A learner who already has a row for the skill (a native one) keeps it untouched: the group folds into it
 * (native_row_kept) and the missing history rows are still written. Deleted users are archived, users that do
 * not exist are skipped. Nothing is enrolled, completed, notified or recomputed: no course_completed event is
 * fired (cron must be off, see the feature notes) and skills_manager is not called.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_skills_step extends step {

    /** Table the learner's current levels go to. */
    private const USER_SKILLS = 'local_sentientia_user_skills';

    /** Table the level changes go to. */
    private const HISTORY = 'local_sentientia_user_skill_hist';

    /** Rows per page of the per-learner reads. */
    private const PAGE = 1000;

    /** @var course_index */
    private course_index $courses;

    /** @var array<int, int>|null The owner's level map. */
    private ?array $proficiency = null;

    public function __construct() {
        $this->courses = new course_index();
    }

    public function key(): string {
        return 'skills.user_skills';
    }

    public function sourcetable(): string {
        return '#course_completions.userid';
    }

    public function targettable(): string {
        return self::USER_SKILLS;
    }

    public function group_by(): array {
        // One group per learner.
        return ['userid'];
    }

    public function columns(): array {
        return ['id', 'userid', 'course', 'timecompleted'];
    }

    public function preload(): array {
        return [['local_skill', '']];
    }

    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $sourceid = (int) $first->id;
        $userid = (int) $first->userid;

        if (!$ctx->lookups->user_exists($userid)) {
            return [outcome::skip($sourceid, 'orphan_user', 'user_not_found')];
        }
        if (!$ctx->lookups->user_active($userid)) {
            return [outcome::archive($sourceid, 'user_deleted')];
        }

        // Events: every completion of a course that teaches a legacy skill, grouped by that skill.
        $events = [];
        foreach ($rows as $row) {
            $this->add_event($ctx, $events, (int) $row->course, (int) ($row->timecompleted ?? 0));
        }
        if ($this->use_archive($ctx)) {
            $after = 0;
            do {
                $page = $ctx->legacy->page('local_recompletion_cc', $after, self::PAGE,
                    ['id', 'userid', 'course', 'timecompleted'],
                    ['t.userid = :blmcu AND t.timecompleted > 0', ['blmcu' => $userid]]);
                foreach ($page as $id => $archived) {
                    $this->add_event($ctx, $events, (int) $archived->course, (int) $archived->timecompleted);
                    $after = (int) $id;
                }
            } while (count($page) === self::PAGE);
        }
        if (!$events) {
            return [outcome::archive($sourceid, 'no_skill_courses')];
        }

        ksort($events);
        $label = (string) $ctx->decision('skills.source_label');
        $existing = $this->native_rows($ctx, $userid);

        $new = [];
        $native = [];
        $history = [];
        $dropped = 0;
        foreach ($events as $legacyskill => $skillevents) {
            $skillid = $ctx->map->resolve('local_skill', $legacyskill);
            if ($skillid === null) {
                // The skill was not imported (no name, no category): its courses earn nothing here.
                $dropped += count($skillevents);
                continue;
            }
            usort($skillevents, static fn(array $a, array $b): int => [$a['time'], $a['course']] <=> [$b['time'], $b['course']]);

            $level = 0;
            $earliest = PHP_INT_MAX;
            $setcourse = 0;
            $settime = 0;
            $n = 0;
            foreach ($skillevents as $event) {
                $earliest = min($earliest, $event['time']);
                if ($event['level'] <= $level) {
                    continue;
                }
                $history[] = [$legacyskill . '_' . (++$n), (object) [
                    'userid' => $userid,
                    'skillid' => $skillid,
                    'previous_level' => $level,
                    'new_level' => $event['level'],
                    'source' => $label,
                    'source_id' => $event['course'],
                    'changed_by_userid' => null,
                    'timecreated' => $event['time'],
                ], $legacyskill];
                $level = $event['level'];
                $setcourse = $event['course'];
                $settime = $event['time'];
            }

            if (isset($existing[$skillid])) {
                $native[$legacyskill] = $existing[$skillid];
                continue;
            }
            $new[$legacyskill] = (object) [
                'userid' => $userid,
                'skillid' => $skillid,
                'current_level' => $level,
                'source' => $label,
                'source_id' => $setcourse,
                'timecreated' => $earliest,
                'timemodified' => $settime,
            ];
        }

        if (!$new && !$native) {
            return [outcome::archive($sourceid, 'no_skill_courses')->warn('events_without_imported_skill')];
        }

        $out = [];
        if ($new) {
            // The first new pair is the group's primary row; the others are sub-rows keyed by the legacy skill.
            foreach ($new as $legacyskill => $fields) {
                $out[] = $out ? outcome::insert($sourceid, self::USER_SKILLS, $fields, 'skill:' . $legacyskill)
                    : outcome::insert($sourceid, self::USER_SKILLS, $fields);
            }
        } else {
            $out[] = outcome::fold($sourceid, self::USER_SKILLS, (int) reset($native), 'native_row_kept');
        }
        foreach ($history as [$suffix, $fields]) {
            $out[] = outcome::insert($sourceid, self::HISTORY, $fields, 'hist:' . $suffix);
        }
        if ($dropped > 0) {
            $out[0]->warn('events_without_imported_skill');
        }
        return $out;
    }

    /**
     * Record one completion as an event of the legacy skill its course teaches.
     *
     * @param context $ctx
     * @param array $events Collects legacy skill id => events.
     * @param int $courseid
     * @param int $time timecompleted; a completion without one is not an event.
     * @return void
     */
    private function add_event(context $ctx, array &$events, int $courseid, int $time): void {
        if ($time <= 0) {
            return;
        }
        $link = $this->courses->skill_of_course($ctx, $courseid);
        if ($link === null) {
            return;
        }
        $events[$link['skill']][] = [
            'time' => $time,
            'course' => $courseid,
            'level' => level_map::proficiency($this->proficiency($ctx), $link['level']),
        ];
    }

    /**
     * Rows the learner already has (native ones, or from an earlier run), by target skill id.
     *
     * @param context $ctx
     * @param int $userid
     * @return array<int, int> target skill id => user_skills id
     */
    private function native_rows(context $ctx, int $userid): array {
        $have = [];
        if (!$ctx->legacy->exists(self::USER_SKILLS)) {
            return $have;
        }
        $after = 0;
        do {
            $page = $ctx->legacy->page(self::USER_SKILLS, $after, self::PAGE, ['id', 'skillid'],
                ['t.userid = :blmnu', ['blmnu' => $userid]]);
            foreach ($page as $id => $row) {
                $have[(int) $row->skillid] = (int) $id;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $have;
    }

    /**
     * Does the owner want archived completions as history, and is there an archive to read?
     *
     * @param context $ctx
     * @return bool
     */
    private function use_archive(context $ctx): bool {
        return filter_var($ctx->decision('skills.history_from_archive'), FILTER_VALIDATE_BOOLEAN)
            && $ctx->legacy->exists('local_recompletion_cc');
    }

    /**
     * @param context $ctx
     * @return array<int, int>
     */
    private function proficiency(context $ctx): array {
        if ($this->proficiency === null) {
            $this->proficiency = level_map::parse($ctx->decision('skills.level_proficiency'))['map'];
        }
        return $this->proficiency;
    }
}
