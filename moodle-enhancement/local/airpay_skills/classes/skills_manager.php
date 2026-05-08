<?php
/**
 * Skills Manager — competency framework operations.
 *
 * @package    local_airpay_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_skills;

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
               FROM {local_airpay_role_skills} rs
               JOIN {local_airpay_skills} s ON s.id = rs.skillid
               JOIN {local_airpay_skill_cats} sc ON sc.id = s.categoryid
              WHERE rs.designation = :desig
           ORDER BY sc.sort_order, s.sort_order",
            ['desig' => $designation]);

        if (empty($required)) {
            return ['skills' => [], 'summary' => self::empty_summary(), 'has_data' => false];
        }

        // Get user's current skill levels.
        $current = $DB->get_records_menu('local_airpay_user_skills',
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
        $course_skills = $DB->get_records('local_airpay_course_skills', ['courseid' => $courseid]);

        foreach ($course_skills as $cs) {
            $existing = $DB->get_record('local_airpay_user_skills', [
                'userid'  => $userid,
                'skillid' => $cs->skillid,
            ]);

            if ($existing) {
                // Only upgrade, never downgrade.
                if ($cs->teaches_level > $existing->current_level) {
                    $existing->current_level = $cs->teaches_level;
                    $existing->source        = 'course';
                    $existing->source_id     = $courseid;
                    $existing->timemodified   = time();
                    $DB->update_record('local_airpay_user_skills', $existing);
                }
            } else {
                $DB->insert_record('local_airpay_user_skills', (object)[
                    'userid'        => $userid,
                    'skillid'       => $cs->skillid,
                    'current_level' => $cs->teaches_level,
                    'source'        => 'course',
                    'source_id'     => $courseid,
                    'timecreated'   => time(),
                    'timemodified'  => time(),
                ]);
            }
        }
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
               FROM {local_airpay_role_skills} rs
               JOIN {local_airpay_skills} s ON s.id = rs.skillid
               JOIN {local_airpay_skill_cats} sc ON sc.id = s.categoryid
              WHERE rs.designation $insql
           ORDER BY sc.sort_order, s.sort_order",
            $params);

        // Build heat map: member × skill → level.
        $members = [];
        foreach ($team as $member) {
            $user_skills = $DB->get_records_menu('local_airpay_user_skills',
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
                   FROM {local_airpay_course_skills} cs
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

    private const CAT_TABLE = 'local_airpay_skill_cats';
    private const SKILL_TABLE = 'local_airpay_skills';
    private const ROLE_SKILL_TABLE = 'local_airpay_role_skills';
    private const COURSE_SKILL_TABLE = 'local_airpay_course_skills';
    private const USER_SKILL_TABLE = 'local_airpay_user_skills';
    private const SKILL_LEVELS_TABLE = 'local_airpay_skill_levels';

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
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_skills');
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
            throw new \moodle_exception('categoryinuse', 'local_airpay_skills');
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
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_skills');
        }

        // Validate category exists.
        if (!$DB->record_exists(self::CAT_TABLE, ['id' => $data->categoryid])) {
            throw new \moodle_exception('invalidcategory', 'local_airpay_skills');
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
                throw new \moodle_exception('invalidcategory', 'local_airpay_skills');
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
