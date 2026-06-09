<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Skill detail view — standalone page with tabs:
 *   Overview     : description, category, max level
 *   Levels       : per-level definitions (links to level_definitions.php)
 *   Designations : which designations expect this skill
 *   Courses      : which courses develop this skill
 *   Learners     : users with this skill in their profile
 *
 * Closes Phase 3 B.7 deferred item.
 *
 * @package local_sentientia_skills
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id  = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'levels', 'designations', 'courses', 'learners'], true)) {
    $tab = 'overview';
}

$skill = $DB->get_record('local_sentientia_skills', ['id' => $id], '*', MUST_EXIST);
$category = $DB->get_record('local_sentientia_skill_cats',
    ['id' => $skill->categoryid]);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_skills/view.php',
    ['id' => $id, 'tab' => $tab]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Skill: ' . format_string($skill->name));
$PAGE->set_heading('Skill: ' . format_string($skill->name));
require_capability('local/sentientia_skills:view', $ctx);

// Counts for tab badges.
$count_levels = (int) $DB->count_records('local_sentientia_skill_levels',
    ['skillid' => $skill->id]);
$count_designations = (int) $DB->count_records('local_sentientia_role_skills',
    ['skillid' => $skill->id]);
$count_courses = (int) $DB->count_records('local_sentientia_course_skills',
    ['skillid' => $skill->id]);
$count_learners = (int) $DB->count_records('local_sentientia_user_skills',
    ['skillid' => $skill->id]);

// Per-tab data.
$tab_data = [];
switch ($tab) {
    case 'levels':
        $levels = $DB->get_records('local_sentientia_skill_levels',
            ['skillid' => $skill->id], 'level_index ASC');
        $tab_data['levels'] = array_values(array_map(fn($l) => [
            'level_index' => (int) ($l->level_index ?? 0),
            'name'        => format_string($l->name ?? ''),
            'description' => format_text($l->description ?? '', FORMAT_HTML),
        ], $levels));
        $tab_data['has_levels'] = count($levels) > 0;
        $tab_data['max_level'] = (int) ($skill->max_level ?: 5);
        $tab_data['edit_url'] = (new moodle_url('/local/sentientia_skills/level_definitions.php',
            ['skillid' => $skill->id]))->out(false);
        break;

    case 'designations':
        $records = $DB->get_records_sql(
            "SELECT rs.id, rs.designation, rs.required_level
               FROM {local_sentientia_role_skills} rs
              WHERE rs.skillid = :sid
              ORDER BY rs.designation ASC", ['sid' => $skill->id]);
        $tab_data['designations'] = array_values(array_map(fn($r) => [
            'designation'    => (string) ($r->designation ?? ''),
            'required_level' => (int) ($r->required_level ?? 0),
        ], $records));
        $tab_data['has_designations'] = count($records) > 0;
        $tab_data['matrix_url'] = (new moodle_url('/local/sentientia_skills/designation_matrix.php'))->out(false);
        break;

    case 'courses':
        $records = $DB->get_records_sql(
            "SELECT cs.id, c.id AS courseid, c.fullname, c.shortname, c.visible
               FROM {local_sentientia_course_skills} cs
               JOIN {course} c ON c.id = cs.courseid
              WHERE cs.skillid = :sid
              ORDER BY c.fullname ASC", ['sid' => $skill->id]);
        $tab_data['courses'] = array_values(array_map(fn($r) => [
            'courseid'  => (int) $r->courseid,
            'fullname'  => format_string($r->fullname),
            'shortname' => format_string($r->shortname),
            'visible'   => (bool) $r->visible,
            'view_url'  => (new \moodle_url('/local/sentientia_courses/enrolledusers.php',
                ['id' => (int) $r->courseid]))->out(false),
        ], $records));
        $tab_data['has_courses'] = count($records) > 0;
        $tab_data['map_url'] = (new moodle_url('/local/sentientia_skills/course_mapping.php'))->out(false);
        break;

    case 'learners':
        $records = $DB->get_records_sql(
            "SELECT us.id, us.userid, us.current_level, us.timemodified,
                    u.firstname, u.lastname, u.email
               FROM {local_sentientia_user_skills} us
               JOIN {user} u ON u.id = us.userid
              WHERE us.skillid = :sid
                AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC
              LIMIT 200", ['sid' => $skill->id]);
        $tab_data['learners'] = array_values(array_map(fn($r) => [
            'userid'        => (int) $r->userid,
            'fullname'      => trim($r->firstname . ' ' . $r->lastname),
            'email'         => (string) $r->email,
            'current_level' => (int) ($r->current_level ?? 0),
            'updated_on'    => $r->timemodified ? userdate($r->timemodified, '%d %b %Y') : '',
        ], $records));
        $tab_data['has_learners'] = count($records) > 0;
        break;
}

// P1 #26 (2026-05-20) — self-rate context. Show the user their current
// level + a "Self-rate" button when they have the :self_rate capability.
// The level dropdown is populated from local_sentientia_skill_levels so
// learners see the same "Awareness / Basic / Intermediate / ..." labels
// the L&D team curated — not raw numbers.
global $USER;
$can_self_rate = has_capability('local/sentientia_skills:self_rate', $ctx);
$user_skill_row = $DB->get_record('local_sentientia_user_skills',
    ['userid' => $USER->id, 'skillid' => $skill->id],
    'id, current_level, source, timemodified');
$current_level = (int) ($user_skill_row->current_level ?? 0);

// Build the level dropdown: 1..max_level. Use admin-curated labels
// when available, fall back to "Level N" so the picker always works.
$level_labels = $DB->get_records_menu('local_sentientia_skill_levels',
    ['skillid' => $skill->id], 'level ASC', 'level, label');
$level_options = [];
$maxlevel = (int) ($skill->max_level ?: 5);
for ($n = 1; $n <= $maxlevel; $n++) {
    $label = trim((string) ($level_labels[$n] ?? ''));
    $level_options[] = [
        'value'   => $n,
        'label'   => $label !== '' ? format_string($label) : "Level $n",
        'selected' => ($current_level === $n),
    ];
}

$data = [
    'skillid'      => (int) $skill->id,
    'name'         => format_string($skill->name),
    'description'  => format_text($skill->description ?? '', FORMAT_HTML),
    'has_description' => !empty(trim($skill->description ?? '')),
    'category_name' => $category ? format_string($category->name) : 'Uncategorised',
    'max_level'    => $maxlevel,

    // P1 #26 — self-rate panel context.
    'can_self_rate'        => $can_self_rate,
    'self_rate_current_level' => $current_level,
    'self_rate_has_current' => $current_level > 0,
    'self_rate_level_options' => $level_options,
    'self_rate_source'        => (string) ($user_skill_row->source ?? ''),

    'count_levels'       => $count_levels,
    'count_designations' => $count_designations,
    'count_courses'      => $count_courses,
    'count_learners'     => $count_learners,

    'tab_overview_active'    => $tab === 'overview',
    'tab_levels_active'      => $tab === 'levels',
    'tab_designations_active' => $tab === 'designations',
    'tab_courses_active'     => $tab === 'courses',
    'tab_learners_active'    => $tab === 'learners',

    'tab_overview_url'    => (new moodle_url('/local/sentientia_skills/view.php', ['id' => $id, 'tab' => 'overview']))->out(false),
    'tab_levels_url'      => (new moodle_url('/local/sentientia_skills/view.php', ['id' => $id, 'tab' => 'levels']))->out(false),
    'tab_designations_url' => (new moodle_url('/local/sentientia_skills/view.php', ['id' => $id, 'tab' => 'designations']))->out(false),
    'tab_courses_url'     => (new moodle_url('/local/sentientia_skills/view.php', ['id' => $id, 'tab' => 'courses']))->out(false),
    'tab_learners_url'    => (new moodle_url('/local/sentientia_skills/view.php', ['id' => $id, 'tab' => 'learners']))->out(false),

    'back_url' => (new moodle_url('/local/sentientia_skills/index.php'))->out(false),

    'tab' => $tab,
];
$data = array_merge($data, $tab_data);

// P1 #26 — initialise the self-rate JS only when the user can actually
// use it. Avoids loading the AMD module for read-only viewers.
if ($can_self_rate) {
    $PAGE->requires->js_call_amd('local_sentientia_skills/skill_actions',
        'init', [['page' => 'skill_view']]);
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_skills/view', $data);
echo $OUTPUT->footer();
