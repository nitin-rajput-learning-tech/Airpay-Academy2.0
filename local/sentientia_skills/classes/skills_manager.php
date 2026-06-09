<?php
/**
 * Skills Manager — competency framework operations.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

class skills_manager {

    /** Skill level labels. */
    const LEVELS = [
        0 => 'Not Started',
        1 => 'Awareness',
        2 => 'Basic',
        3 => 'Intermediate',
        4 => 'Advanced',
        5 => 'Expert',
    ];

    /**
     * Get gap analysis for a user — compares current skills vs required for their role.
     *
     * @return array {skills: [{name, category, required, current, gap, status}], summary: {total, met, gaps, percentage}}
     */
    public static function get_gap_analysis(int $userid): array {
        global $DB, $USER;

        $user = $DB->get_record('user', ['id' => $userid], 'id, open_designation');
        $designation = $user->open_designation ?? '';

        if (empty($designation)) {
            return ['skills' => [], 'summary' => self::empty_summary(), 'has_data' => false];
        }

        // Get required skills for this designation.
        $required = $DB->get_records_sql(
            "SELECT rs.skillid, rs.required_level, s.name as skill_name, s.categoryid,
                    sc.name as category_name, sc.color as category_color
               FROM {local_sentientia_role_skills} rs
               JOIN {local_sentientia_skills} s ON s.id = rs.skillid
               JOIN {local_sentientia_skill_cats} sc ON sc.id = s.categoryid
              WHERE rs.designation = :desig
           ORDER BY sc.sort_order, s.sort_order",
            ['desig' => $designation]);

        if (empty($required)) {
            return ['skills' => [], 'summary' => self::empty_summary(), 'has_data' => false];
        }

        // Get user's current skill levels.
        $current = $DB->get_records_menu('local_sentientia_user_skills',
            ['userid' => $userid], '', 'skillid, current_level');

        $skills = [];
        $total = 0;
        $met = 0;

        foreach ($required as $r) {
            $currentlvl = $current[$r->skillid] ?? 0;
            $gap = max(0, $r->required_level - $currentlvl);
            $status = ($gap === 0) ? 'met' : (($currentlvl > 0) ? 'partial' : 'missing');

            $skills[] = [
                'skill_name'     => format_string($r->skill_name),
                'category_name'  => format_string($r->category_name),
                'category_color' => $r->category_color,
                'required_level' => $r->required_level,
                'required_label' => self::LEVELS[$r->required_level] ?? '',
                'current_level'  => $currentlvl,
                'current_label'  => self::LEVELS[$currentlvl] ?? 'Not Started',
                'gap'            => $gap,
                'status'         => $status,
                'is_met'         => ($status === 'met'),
                'is_partial'     => ($status === 'partial'),
                'is_missing'     => ($status === 'missing'),
                'progress_pct'   => $r->required_level > 0
                    ? round(($currentlvl / $r->required_level) * 100) : 0,
            ];

            $total++;
            if ($gap === 0) {
                $met++;
            }
        }

        return [
            'skills'   => $skills,
            'summary'  => [
                'total'      => $total,
                'met'        => $met,
                'gaps'       => $total - $met,
                'percentage' => $total > 0 ? round(($met / $total) * 100) : 0,
            ],
            'has_data'     => true,
            'designation'  => format_string($designation),
        ];
    }

    /**
     * Get radar chart data for a user's skills (for profile page).
     * Returns labels + current values + required values for Chart.js radar.
     */
    public static function get_radar_data(int $userid): array {
        $analysis = self::get_gap_analysis($userid);
        if (!$analysis['has_data']) {
            return ['has_radar' => false];
        }

        $labels = [];
        $current = [];
        $required = [];

        foreach ($analysis['skills'] as $skill) {
            $labels[] = $skill['skill_name'];
            $current[] = $skill['current_level'];
            $required[] = $skill['required_level'];
        }

        return [
            'has_radar'       => true,
            'radar_labels'    => json_encode($labels),
            'radar_current'   => json_encode($current),
            'radar_required'  => json_encode($required),
            'designation'     => $analysis['designation'],
        ];
    }

    /**
     * Update user's skill level after course completion.
     * Called by event observer when a course is completed.
     */
    public static function update_from_course(int $userid, int $courseid): void {
        global $DB;

        // Get skills this course teaches.
        $course_skills = $DB->get_records('local_sentientia_course_skills', ['courseid' => $courseid]);

        foreach ($course_skills as $cs) {
            $existing = $DB->get_record('local_sentientia_user_skills', [
                'userid'  => $userid,
                'skillid' => $cs->skillid,
            ]);

            if ($existing) {
                // Only upgrade, never downgrade.
                if ($cs->teaches_level > $existing->current_level) {
                    $previous_level = (int) $existing->current_level;
                    $existing->current_level = $cs->teaches_level;
                    $existing->source        = 'course';
                    $existing->source_id     = $courseid;
                    $existing->timemodified   = time();
                    $DB->update_record('local_sentientia_user_skills', $existing);

                    // P1 #22 — audit log.
                    self::record_skill_change(
                        $userid, (int) $cs->skillid,
                        $previous_level, (int) $cs->teaches_level,
                        'course', $courseid, null);
                }
            } else {
                $DB->insert_record('local_sentientia_user_skills', (object)[
                    'userid'        => $userid,
                    'skillid'       => $cs->skillid,
                    'current_level' => $cs->teaches_level,
                    'source'        => 'course',
                    'source_id'     => $courseid,
                    'timecreated'   => time(),
                    'timemodified'  => time(),
                ]);

                // P1 #22 — first-time grant. previous_level = 0 (no prior).
                self::record_skill_change(
                    $userid, (int) $cs->skillid,
                    0, (int) $cs->teaches_level,
                    'course', $courseid, null);
            }
        }
    }

    /**
     * P1 #22 (2026-05-16) — append a row to local_sentientia_user_skill_hist.
     *
     * Public so future callers (admin manual override, self-rating
     * workflow, HRMS import) can write history rows in one consistent
     * place. The schema enforces append-only via convention; no UPDATE
     * or DELETE outside the privacy provider's user-erasure path.
     *
     * Idempotency: a noop change (previous_level === new_level) is
     * NOT recorded, so spammy re-application of the same course
     * completion doesn't bloat the history table.
     *
     * @param int      $userid           User whose level changed.
     * @param int      $skillid          Skill being changed.
     * @param int      $previous_level   Level before the change (0 if none).
     * @param int      $new_level        Level after the change.
     * @param string   $source           One of course|assessment|manual|import.
     * @param int|null $source_id        Course/assessment id (null for manual).
     * @param int|null $changed_by_userid  Acting user id (null for auto/cron).
     */
    public static function record_skill_change(int $userid, int $skillid,
                                                 int $previous_level,
                                                 int $new_level,
                                                 string $source = 'course',
                                                 ?int $source_id = null,
                                                 ?int $changed_by_userid = null): void {
        global $DB;

        // Skip noop "changes" — saves a row per stale-cache re-apply.
        if ($previous_level === $new_level) {
            return;
        }

        $DB->insert_record(self::USER_SKILL_HIST_TABLE, (object) [
            'userid'             => $userid,
            'skillid'            => $skillid,
            'previous_level'     => $previous_level,
            'new_level'          => $new_level,
            'source'             => $source,
            'source_id'          => $source_id,
            'changed_by_userid'  => $changed_by_userid,
            'timecreated'        => time(),
        ]);
    }

    /**
     * P1 #22 — query the history table.
     *
     * @param int $userid     Required.
     * @param int $skillid    Optional — 0 returns all skills for the user.
     * @param int $limit      0 = no limit.
     * @return array<int, \stdClass>  Most recent first.
     */
    public static function get_user_skill_history(int $userid,
                                                    int $skillid = 0,
                                                    int $limit = 0): array {
        global $DB;
        $where  = ['userid' => $userid];
        if ($skillid > 0) {
            $where['skillid'] = $skillid;
        }
        return $DB->get_records(self::USER_SKILL_HIST_TABLE, $where,
            'timecreated DESC, id DESC', '*', 0, $limit);
    }

    /**
     * P1 #25 (2026-05-20) — learner self-attestation.
     *
     * Closes audit item #26 from
     * parity-audit-2026-05-15/sentientia_skills.md.
     *
     * Persists the self-rated level via the same user_skills upsert
     * pattern as `update_from_course()`, but tags the source as
     * `'self'` so HR can distinguish self-attested vs course-earned
     * levels in the analysis. The history table records both the
     * acting user AND the subject — for self-rates these are the same
     * id, but the call signature lets admins backfill on behalf of
     * a learner during onboarding (with their consent).
     *
     * Design choices that fall out of L&D practice:
     *   - DOWNGRADES allowed. If a learner reflects and realises they
     *     over-rated themselves ("I marked myself L5 Python but on
     *     reflection I'm really L3"), the workflow should let them
     *     correct it. The audit-log row captures the truthful change.
     *   - Refuses to upgrade above the parent skill's `max_level`.
     *   - Noop self-rates (same level as current) write a history
     *     row tagged 'self' anyway, because a re-attestation is itself
     *     a signal of confidence — BUT this is handled by
     *     `record_skill_change()` which skips identical-level writes.
     *     If we want to record reaffirmations explicitly we'd need a
     *     separate hist row type; for now the noop-skip is the simpler
     *     contract.
     *
     * @param int      $userid          The learner whose level is being set.
     * @param int      $skillid         FK to local_sentientia_skills.
     * @param int      $new_level       Target level (1..max_level).
     * @param int|null $acting_userid   Who hit the button — null = same as
     *                                   $userid (true self-rate). When an
     *                                   admin backfills, pass their id here.
     * @return int  The user_skills row id (after upsert).
     * @throws \moodle_exception on invalid level / unknown skill
     */
    public static function self_rate_skill(int $userid, int $skillid,
                                             int $new_level,
                                             ?int $acting_userid = null): int {
        global $DB;

        $skill = $DB->get_record(self::SKILL_TABLE, ['id' => $skillid],
            'id, max_level', MUST_EXIST);
        $maxlevel = max(1, (int) $skill->max_level);

        if ($new_level < 1 || $new_level > $maxlevel) {
            $a = (object) ['level' => $new_level, 'max' => $maxlevel];
            throw new \moodle_exception('self_rate_level_invalid',
                'local_sentientia_skills', '', $a);
        }

        $existing = $DB->get_record(self::USER_SKILL_TABLE, [
            'userid' => $userid, 'skillid' => $skillid]);

        $previous_level = (int) ($existing->current_level ?? 0);
        $now = time();
        $actor = $acting_userid ?? $userid;

        if ($existing) {
            $existing->current_level = $new_level;
            $existing->source        = 'self';
            $existing->source_id     = null;
            $existing->timemodified  = $now;
            $DB->update_record(self::USER_SKILL_TABLE, $existing);
            $rowid = (int) $existing->id;
        } else {
            $rowid = (int) $DB->insert_record(self::USER_SKILL_TABLE, (object) [
                'userid'        => $userid,
                'skillid'       => $skillid,
                'current_level' => $new_level,
                'source'        => 'self',
                'source_id'     => null,
                'timecreated'   => $now,
                'timemodified'  => $now,
            ]);
        }

        // record_skill_change skips noop changes automatically, so a
        // re-affirmation of the same level doesn't add a history row.
        // changed_by_userid = the actor (matches userid for self, admin
        // id when backfilling).
        self::record_skill_change(
            $userid, $skillid,
            $previous_level, $new_level,
            'self', null, $actor);

        return $rowid;
    }

    /**
     * Get team skills heat map for managers.
     * Shows all team members and their skill levels.
     */
    public static function get_team_heatmap(int $managerid): array {
        global $DB;

        // Get team members.
        $team = $DB->get_records_select('user',
            'open_supervisorid = :mgr AND deleted = 0 AND suspended = 0',
            ['mgr' => $managerid], 'lastname ASC',
            'id, firstname, lastname, open_designation');

        if (empty($team)) {
            return ['has_team' => false, 'members' => [], 'skills' => []];
        }

        // Get all unique skills from team members' designations.
        $designations = array_unique(array_filter(array_column($team, 'open_designation')));
        if (empty($designations)) {
            return ['has_team' => true, 'members' => array_values($team), 'skills' => [], 'has_skills' => false];
        }

        [$insql, $params] = $DB->get_in_or_equal($designations, SQL_PARAMS_NAMED, 'desig');
        $all_skills = $DB->get_records_sql(
            "SELECT DISTINCT s.id, s.name, sc.name as category_name
               FROM {local_sentientia_role_skills} rs
               JOIN {local_sentientia_skills} s ON s.id = rs.skillid
               JOIN {local_sentientia_skill_cats} sc ON sc.id = s.categoryid
              WHERE rs.designation $insql
           ORDER BY sc.sort_order, s.sort_order",
            $params);

        // Build heat map: member × skill → level.
        $members = [];
        foreach ($team as $member) {
            $user_skills = $DB->get_records_menu('local_sentientia_user_skills',
                ['userid' => $member->id], '', 'skillid, current_level');

            $skill_data = [];
            foreach ($all_skills as $skill) {
                $level = $user_skills[$skill->id] ?? 0;
                $skill_data[] = [
                    'skill_name' => format_string($skill->name),
                    'level'      => $level,
                    'level_label' => self::LEVELS[$level] ?? '',
                    'heat_class' => 'heat-' . min($level, 5),
                ];
            }

            $members[] = [
                'firstname' => format_string($member->firstname),
                'lastname'  => format_string($member->lastname),
                'initials'  => strtoupper(substr($member->firstname, 0, 1) . substr($member->lastname, 0, 1)),
                'skills'    => $skill_data,
            ];
        }

        $skill_headers = array_map(fn($s) => [
            'name' => format_string($s->name),
            'category' => format_string($s->category_name),
        ], array_values($all_skills));

        return [
            'has_team'    => true,
            'has_skills'  => !empty($skill_headers),
            'members'     => $members,
            'skills'      => $skill_headers,
            'member_count' => count($members),
            'skill_count'  => count($skill_headers),
        ];
    }

    /**
     * Get recommended courses to fill skill gaps.
     */
    public static function get_gap_courses(int $userid, int $limit = 5): array {
        global $DB;

        $analysis = self::get_gap_analysis($userid);
        if (!$analysis['has_data']) {
            return [];
        }

        $gap_skill_ids = [];
        foreach ($analysis['skills'] as $skill) {
            if (!$skill['is_met']) {
                $gap_skill_ids[] = $skill; // We need the skill info.
            }
        }

        if (empty($gap_skill_ids)) {
            return [];
        }

        // Find courses that teach the gap skills and the user hasn't completed.
        $recommendations = [];
        foreach ($gap_skill_ids as $gap) {
            $courses = $DB->get_records_sql(
                "SELECT cs.courseid, c.fullname, c.shortname, cs.teaches_level, cs.skillid
                   FROM {local_sentientia_course_skills} cs
                   JOIN {course} c ON c.id = cs.courseid AND c.visible = 1
              LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = :uid
                  WHERE cs.skillid = :sid AND cs.teaches_level >= :targetlvl
                    AND (cc.timecompleted IS NULL)
               ORDER BY cs.teaches_level DESC
                  LIMIT 2",
                ['uid' => $userid, 'sid' => $gap['skillid'] ?? 0, 'targetlvl' => $gap['required_level'] ?? 1]);

            foreach ($courses as $course) {
                $recommendations[] = [
                    'courseid'    => $course->courseid,
                    'fullname'    => format_string($course->fullname),
                    'shortname'   => format_string($course->shortname),
                    'skill_name'  => $gap['skill_name'],
                    'gap_level'   => $gap['required_level'] - $gap['current_level'],
                    'viewurl'     => (new \moodle_url('/course/view.php', ['id' => $course->courseid]))->out(false),
                ];
            }
        }

        return array_slice($recommendations, 0, $limit);
    }

    private static function empty_summary(): array {
        return ['total' => 0, 'met' => 0, 'gaps' => 0, 'percentage' => 0];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Admin CRUD operations — categories
    // ═══════════════════════════════════════════════════════════════════

    private const CAT_TABLE = 'local_sentientia_skill_cats';
    private const SKILL_TABLE = 'local_sentientia_skills';
    private const ROLE_SKILL_TABLE = 'local_sentientia_role_skills';
    private const COURSE_SKILL_TABLE = 'local_sentientia_course_skills';
    private const USER_SKILL_TABLE = 'local_sentientia_user_skills';
    private const SKILL_LEVELS_TABLE = 'local_sentientia_skill_levels';
    // P1 #22 (2026-05-16) — audit log of level changes.
    private const USER_SKILL_HIST_TABLE = 'local_sentientia_user_skill_hist';

    /** Get all categories for dropdowns. */
    public static function get_categories_options(): array {
        global $DB;
        $cats = $DB->get_records(self::CAT_TABLE, null, 'sort_order ASC, name ASC',
            'id, name');
        $options = [];
        foreach ($cats as $c) {
            $options[$c->id] = format_string($c->name);
        }
        return $options;
    }

    /** Count totals for KPI cards. */
    public static function count_categories(): int {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists(self::CAT_TABLE) ? $DB->count_records(self::CAT_TABLE) : 0;
    }

    public static function count_skills(): int {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists(self::SKILL_TABLE) ? $DB->count_records(self::SKILL_TABLE) : 0;
    }

    public static function count_role_mappings(): int {
        global $DB;
        $dbman = $DB->get_manager();
        return $dbman->table_exists(self::ROLE_SKILL_TABLE) ? $DB->count_records(self::ROLE_SKILL_TABLE) : 0;
    }

    /** Create a skill category. */
    public static function create_category(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_skills');
        }

        $record = (object) [
            'name'        => trim($data->name),
            'description' => $data->description ?? '',
            'icon'        => $data->icon ?? 'fa-cogs',
            'color'       => $data->color ?? '#0066A7',
            'sort_order'  => (int) ($data->sort_order ?? 0),
            'timecreated' => time(),
        ];

        return $DB->insert_record(self::CAT_TABLE, $record);
    }

    public static function update_category(int $id, object $data): bool {
        global $DB;
        $existing = $DB->get_record(self::CAT_TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id];
        foreach (['name', 'description', 'icon', 'color', 'sort_order'] as $field) {
            if (isset($data->$field)) {
                $record->$field = $data->$field;
            }
        }
        $DB->update_record(self::CAT_TABLE, $record);
        return true;
    }

    /**
     * Delete a category — only if no skills reference it.
     */
    public static function delete_category(int $id): bool {
        global $DB;
        $existing = $DB->get_record(self::CAT_TABLE, ['id' => $id], '*', MUST_EXIST);

        // Block delete if skills exist in this category.
        if ($DB->record_exists(self::SKILL_TABLE, ['categoryid' => $id])) {
            throw new \moodle_exception('categoryinuse', 'local_sentientia_skills');
        }

        $DB->delete_records(self::CAT_TABLE, ['id' => $id]);
        return true;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Admin CRUD operations — skills
    // ═══════════════════════════════════════════════════════════════════

    /** Create a skill. */
    public static function create_skill(object $data): int {
        global $DB;

        if (empty($data->name) || empty($data->categoryid)) {
            throw new \moodle_exception('missingrequiredfields', 'local_sentientia_skills');
        }

        // Validate category exists.
        if (!$DB->record_exists(self::CAT_TABLE, ['id' => $data->categoryid])) {
            throw new \moodle_exception('invalidcategory', 'local_sentientia_skills');
        }

        $record = (object) [
            'categoryid'  => (int) $data->categoryid,
            'name'        => trim($data->name),
            'description' => $data->description ?? '',
            'max_level'   => max(1, min(5, (int) ($data->max_level ?? 5))),
            'sort_order'  => (int) ($data->sort_order ?? 0),
            'timecreated' => time(),
        ];

        return $DB->insert_record(self::SKILL_TABLE, $record);
    }

    public static function update_skill(int $id, object $data): bool {
        global $DB;
        $existing = $DB->get_record(self::SKILL_TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id];

        if (isset($data->name))         $record->name = trim($data->name);
        if (isset($data->description))  $record->description = $data->description;
        if (isset($data->categoryid)) {
            if (!$DB->record_exists(self::CAT_TABLE, ['id' => $data->categoryid])) {
                throw new \moodle_exception('invalidcategory', 'local_sentientia_skills');
            }
            $record->categoryid = (int) $data->categoryid;
        }
        if (isset($data->max_level))    $record->max_level = max(1, min(5, (int) $data->max_level));
        if (isset($data->sort_order))   $record->sort_order = (int) $data->sort_order;

        $DB->update_record(self::SKILL_TABLE, $record);
        return true;
    }

    /**
     * Delete a skill and all its role/course/user/level mappings.
     */
    public static function delete_skill(int $id): bool {
        global $DB;
        $existing = $DB->get_record(self::SKILL_TABLE, ['id' => $id], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records(self::ROLE_SKILL_TABLE, ['skillid' => $id]);
            $DB->delete_records(self::COURSE_SKILL_TABLE, ['skillid' => $id]);
            $DB->delete_records(self::USER_SKILL_TABLE, ['skillid' => $id]);
            // Phase A — also clean up level definitions if present.
            if ($DB->get_manager()->table_exists(self::SKILL_LEVELS_TABLE)) {
                $DB->delete_records(self::SKILL_LEVELS_TABLE, ['skillid' => $id]);
            }
            $DB->delete_records(self::SKILL_TABLE, ['id' => $id]);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────
    // Phase A (2026-05-08) — skill-level definitions admin
    // ─────────────────────────────────────────────────────────────────

    /**
     * Get the list of level definitions for one skill.
     *
     * Returns an array of length skill.max_level — one entry per level
     * (1..max_level). Slots without a saved row return defaults so the
     * admin UI can show an "empty" row for unfilled levels.
     *
     * @return array<int,array{level:int, label:string, description:string,
     *                          saved:bool, id:int}>
     */
    public static function get_skill_levels(int $skillid): array {
        global $DB;
        $skill = $DB->get_record(self::SKILL_TABLE, ['id' => $skillid], '*', MUST_EXIST);
        $maxlevel = max(1, min(10, (int) $skill->max_level));

        $saved = $DB->get_records(self::SKILL_LEVELS_TABLE,
            ['skillid' => $skillid], 'level ASC');
        $byLevel = [];
        foreach ($saved as $row) {
            $byLevel[(int) $row->level] = $row;
        }

        $defaults = [
            1 => 'Awareness',
            2 => 'Basic',
            3 => 'Intermediate',
            4 => 'Advanced',
            5 => 'Expert',
        ];

        $out = [];
        for ($lvl = 1; $lvl <= $maxlevel; $lvl++) {
            $row = $byLevel[$lvl] ?? null;
            $out[] = [
                'level'       => $lvl,
                'id'          => $row ? (int) $row->id : 0,
                'label'       => $row ? (string) $row->label : ($defaults[$lvl] ?? ''),
                'description' => $row ? (string) ($row->description ?? '') : '',
                'saved'       => $row !== null,
            ];
        }
        return $out;
    }

    /**
     * Upsert one level definition. Validates level is within
     * (1..skill.max_level) and label is non-empty.
     *
     * @return int the ID of the saved row
     */
    public static function save_skill_level(int $skillid, int $level,
                                             string $label, string $description = ''): int {
        global $DB;
        $skill = $DB->get_record(self::SKILL_TABLE, ['id' => $skillid], '*', MUST_EXIST);
        if ($level < 1 || $level > (int) $skill->max_level) {
            throw new \invalid_parameter_exception(
                'Level must be between 1 and ' . (int) $skill->max_level);
        }
        if (trim($label) === '') {
            throw new \invalid_parameter_exception('Label is required.');
        }

        $now = time();
        $existing = $DB->get_record(self::SKILL_LEVELS_TABLE,
            ['skillid' => $skillid, 'level' => $level]);
        if ($existing) {
            $existing->label = $label;
            $existing->description = $description;
            $existing->timemodified = $now;
            $DB->update_record(self::SKILL_LEVELS_TABLE, $existing);
            return (int) $existing->id;
        }
        return (int) $DB->insert_record(self::SKILL_LEVELS_TABLE, (object) [
            'skillid'      => $skillid,
            'level'        => $level,
            'label'        => $label,
            'description'  => $description,
            'timemodified' => $now,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Phase A (2026-05-08) — designation-skill matrix admin
    // ─────────────────────────────────────────────────────────────────

    /**
     * List distinct designations that have at least one role-skill row,
     * plus distinct designations from the user table (so admins can
     * pre-fill from existing user data without typing).
     *
     * @return list<string>
     */
    public static function list_designations(): array {
        global $DB;
        $rows = $DB->get_fieldset_sql("
            SELECT DISTINCT designation FROM {" . self::ROLE_SKILL_TABLE . "}
             WHERE designation IS NOT NULL AND designation <> ''");
        // Also pull distinct designations from user table (BizLMS field).
        try {
            if ($DB->get_manager()->field_exists('user',
                    new \xmldb_field('open_designation', XMLDB_TYPE_CHAR, '200'))) {
                $userdesigs = $DB->get_fieldset_sql("
                    SELECT DISTINCT open_designation FROM {user}
                     WHERE open_designation IS NOT NULL AND open_designation <> ''
                       AND deleted = 0
                  ORDER BY open_designation ASC");
                $rows = array_unique(array_merge($rows, $userdesigs));
            }
        } catch (\Throwable $e) {
            // Field may not exist on stock Moodle — fine.
        }
        sort($rows, SORT_STRING | SORT_FLAG_CASE);
        return array_values($rows);
    }

    /**
     * Get the required-skill rows for one designation, joined with
     * skill name + max_level + category.
     */
    public static function get_designation_skills(string $designation): array {
        global $DB;
        $rows = $DB->get_records_sql("
            SELECT rs.id, rs.designation, rs.skillid, rs.required_level,
                   s.name AS skill_name, s.max_level,
                   c.name AS category_name, c.color AS category_color
              FROM {" . self::ROLE_SKILL_TABLE . "} rs
              JOIN {" . self::SKILL_TABLE . "} s ON s.id = rs.skillid
         LEFT JOIN {" . self::CAT_TABLE . "} c ON c.id = s.categoryid
             WHERE rs.designation = :d
          ORDER BY c.sort_order ASC, s.name ASC",
            ['d' => $designation]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int) $r->id,
                'skillid'        => (int) $r->skillid,
                'skill_name'     => format_string($r->skill_name),
                'required_level' => (int) $r->required_level,
                'max_level'      => (int) $r->max_level,
                'category_name'  => format_string((string) ($r->category_name ?? '')),
                'category_color' => s((string) ($r->category_color ?? '#0066A7')),
            ];
        }
        return $out;
    }

    /**
     * Upsert one designation-skill requirement.
     *
     * @return int  ID of the row
     */
    public static function save_designation_skill(string $designation, int $skillid,
                                                   int $required_level): int {
        global $DB;
        if (trim($designation) === '') {
            throw new \invalid_parameter_exception('Designation is required.');
        }
        $skill = $DB->get_record(self::SKILL_TABLE, ['id' => $skillid], '*', MUST_EXIST);
        if ($required_level < 1 || $required_level > (int) $skill->max_level) {
            throw new \invalid_parameter_exception(
                'Required level must be between 1 and ' . (int) $skill->max_level);
        }
        $existing = $DB->get_record(self::ROLE_SKILL_TABLE,
            ['designation' => $designation, 'skillid' => $skillid]);
        if ($existing) {
            $existing->required_level = $required_level;
            $DB->update_record(self::ROLE_SKILL_TABLE, $existing);
            return (int) $existing->id;
        }
        return (int) $DB->insert_record(self::ROLE_SKILL_TABLE, (object) [
            'designation'    => $designation,
            'skillid'        => $skillid,
            'required_level' => $required_level,
            'timecreated'    => time(),
        ]);
    }

    /**
     * Remove one designation-skill row.
     */
    public static function delete_designation_skill(int $id): bool {
        global $DB;
        $DB->delete_records(self::ROLE_SKILL_TABLE, ['id' => $id]);
        return true;
    }

    // ─────────────────────────────────────────────────────────────────
    // Phase A.2 (2026-05-08) — course-skill mapping admin
    // ─────────────────────────────────────────────────────────────────

    /**
     * List the course-skill mapping rows for one course, joined with
     * skill name + max_level + category.
     *
     * @return list<array{id:int, skillid:int, skill_name:string,
     *                    teaches_level:int, max_level:int,
     *                    category_name:string, category_color:string}>
     */
    public static function list_course_skills(int $courseid): array {
        global $DB;
        $rows = $DB->get_records_sql("
            SELECT cs.id, cs.courseid, cs.skillid, cs.teaches_level,
                   s.name AS skill_name, s.max_level,
                   c.name AS category_name, c.color AS category_color
              FROM {" . self::COURSE_SKILL_TABLE . "} cs
              JOIN {" . self::SKILL_TABLE . "} s ON s.id = cs.skillid
         LEFT JOIN {" . self::CAT_TABLE . "} c ON c.id = s.categoryid
             WHERE cs.courseid = :cid
          ORDER BY c.sort_order ASC, s.name ASC",
            ['cid' => $courseid]);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => (int) $r->id,
                'skillid'        => (int) $r->skillid,
                'skill_name'     => format_string($r->skill_name),
                'teaches_level'  => (int) $r->teaches_level,
                'max_level'      => (int) $r->max_level,
                'category_name'  => format_string((string) ($r->category_name ?? '')),
                'category_color' => s((string) ($r->category_color ?? '#0066A7')),
            ];
        }
        return $out;
    }

    /**
     * Upsert one course-skill mapping row.
     *
     * @param int $courseid       Course to map skills onto
     * @param int $skillid        Skill being taught
     * @param int $teaches_level  Level (1..max_level) granted on completion
     * @return int  Row ID
     */
    public static function save_course_skill(int $courseid, int $skillid,
                                              int $teaches_level): int {
        global $DB;
        // Validate course exists.
        $course = $DB->get_record('course', ['id' => $courseid], 'id', MUST_EXIST);
        $skill = $DB->get_record(self::SKILL_TABLE, ['id' => $skillid], '*', MUST_EXIST);
        if ($teaches_level < 1 || $teaches_level > (int) $skill->max_level) {
            throw new \invalid_parameter_exception(
                'teaches_level must be between 1 and ' . (int) $skill->max_level);
        }
        $existing = $DB->get_record(self::COURSE_SKILL_TABLE,
            ['courseid' => $courseid, 'skillid' => $skillid]);
        if ($existing) {
            $existing->teaches_level = $teaches_level;
            $DB->update_record(self::COURSE_SKILL_TABLE, $existing);
            return (int) $existing->id;
        }
        return (int) $DB->insert_record(self::COURSE_SKILL_TABLE, (object) [
            'courseid'      => $courseid,
            'skillid'       => $skillid,
            'teaches_level' => $teaches_level,
            'timecreated'   => time(),
        ]);
    }

    /**
     * Remove one course-skill mapping by row ID.
     */
    public static function delete_course_skill(int $id): bool {
        global $DB;
        $DB->delete_records(self::COURSE_SKILL_TABLE, ['id' => $id]);
        return true;
    }

    /**
     * Lookup courses by name/shortname for the picker.
     * Returns up to $limit visible courses matching the search term.
     *
     * @return list<array{id:int, fullname:string, shortname:string,
     *                    mapped_count:int}>
     */
    public static function search_courses(string $q, int $limit = 25): array {
        global $DB;
        $q = trim($q);
        $like = '%' . $DB->sql_like_escape($q) . '%';
        $where = $q !== ''
            ? 'AND (' . $DB->sql_like('c.fullname', ':q1', false)
              . ' OR ' . $DB->sql_like('c.shortname', ':q2', false) . ')'
            : '';
        $params = ['siteid' => SITEID];
        if ($q !== '') {
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        $sql = "SELECT c.id, c.fullname, c.shortname,
                       (SELECT COUNT(*) FROM {" . self::COURSE_SKILL_TABLE . "} cs
                         WHERE cs.courseid = c.id) AS mapped_count
                  FROM {course} c
                 WHERE c.id <> :siteid AND c.visible = 1 $where
              ORDER BY c.fullname ASC";
        $rows = $DB->get_records_sql($sql, $params, 0, $limit);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'           => (int) $r->id,
                'fullname'     => format_string($r->fullname),
                'shortname'    => format_string($r->shortname),
                'mapped_count' => (int) $r->mapped_count,
            ];
        }
        return $out;
    }

    /**
     * Fetch a single course's basic info for header rendering.
     */
    public static function get_course_summary(int $courseid): ?array {
        global $DB;
        $c = $DB->get_record('course', ['id' => $courseid],
            'id, fullname, shortname, summary');
        if (!$c) {
            return null;
        }
        return [
            'id'        => (int) $c->id,
            'fullname'  => format_string($c->fullname),
            'shortname' => format_string($c->shortname),
        ];
    }

    /**
     * Bulk: copy all rows from one designation to another.
     * Useful when a new designation has the same requirements as an
     * existing one. Skips any (target,skillid) pair that already exists.
     *
     * @return int  number of rows copied
     */
    public static function copy_designation(string $from, string $to): int {
        global $DB;
        if (trim($to) === '' || $from === $to) {
            return 0;
        }
        $rows = $DB->get_records(self::ROLE_SKILL_TABLE, ['designation' => $from]);
        $copied = 0;
        $now = time();
        $tx = $DB->start_delegated_transaction();
        try {
            foreach ($rows as $row) {
                if ($DB->record_exists(self::ROLE_SKILL_TABLE,
                        ['designation' => $to, 'skillid' => $row->skillid])) {
                    continue;
                }
                $DB->insert_record(self::ROLE_SKILL_TABLE, (object) [
                    'designation'    => $to,
                    'skillid'        => (int) $row->skillid,
                    'required_level' => (int) $row->required_level,
                    'timecreated'    => $now,
                ]);
                $copied++;
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
        return $copied;
    }
}
