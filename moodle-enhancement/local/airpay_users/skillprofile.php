<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-user skill profile — standalone page with skill radar + gap analysis.
 *
 * Closes Phase 3 B.1 deferred item. Shows:
 *   - Skill radar (vs designation-expected levels)
 *   - Gap table (skills below expected)
 *   - Recommended courses (linked to skills with gaps)
 *
 * @package local_airpay_users
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $OUTPUT, $PAGE;

$userid = optional_param('id', $USER->id, PARAM_INT);
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

$is_self = ((int) $userid === (int) $USER->id);
$ctx = context_system::instance();
if (!$is_self
    && !is_siteadmin()
    && !has_capability('local/airpay_users:view', $ctx)) {
    throw new \moodle_exception('nopermissions', 'error', '',
        "view another user's skill profile");
}

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_users/skillprofile.php', ['id' => $userid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Skill profile — ' . fullname($user));
$PAGE->set_heading('Skill profile — ' . fullname($user));

// Load user's current skill levels (joined to skill metadata).
$user_skills = $DB->get_records_sql(
    "SELECT us.id, us.skillid, us.current_level, us.timemodified,
            s.name AS skill_name, s.max_level, s.categoryid,
            c.name AS category_name
       FROM {local_airpay_user_skills} us
       JOIN {local_airpay_skills} s ON s.id = us.skillid
  LEFT JOIN {local_airpay_skill_cats} c ON c.id = s.categoryid
      WHERE us.userid = :uid
      ORDER BY c.name ASC, s.name ASC",
    ['uid' => $userid]);

// What does the user's designation expect?
$expected_by_skill = [];
if (!empty($user->open_designation)) {
    $expectations = $DB->get_records('local_airpay_role_skills',
        ['designation' => $user->open_designation]);
    foreach ($expectations as $exp) {
        $expected_by_skill[(int) $exp->skillid] = (int) $exp->required_level;
    }
}

// Build display data — for each expected skill, show current vs expected.
$skill_rows = [];
$gap_rows = [];
$radar_labels = [];
$radar_current = [];
$radar_expected = [];

// Include every expected skill, even if user has no record yet.
$all_skill_ids = array_unique(array_merge(
    array_keys($expected_by_skill),
    array_map(fn($us) => (int) $us->skillid, $user_skills)
));

foreach ($all_skill_ids as $sid) {
    $skill = $DB->get_record('local_airpay_skills', ['id' => $sid]);
    if (!$skill) continue;
    $current = 0;
    foreach ($user_skills as $us) {
        if ((int) $us->skillid === $sid) {
            $current = (int) $us->current_level;
            break;
        }
    }
    $expected = $expected_by_skill[$sid] ?? 0;
    $max = (int) ($skill->max_level ?: 5);

    $skill_rows[] = [
        'skillid'  => $sid,
        'name'     => format_string($skill->name),
        'current'  => $current,
        'expected' => $expected,
        'max'      => $max,
        'gap'      => max(0, $expected - $current),
        'view_url' => (new moodle_url('/local/airpay_skills/view.php',
            ['id' => $sid]))->out(false),
    ];
    if ($expected > 0 && $current < $expected) {
        $gap_rows[] = [
            'skillid'  => $sid,
            'name'     => format_string($skill->name),
            'current'  => $current,
            'expected' => $expected,
            'gap'      => $expected - $current,
            'view_url' => (new moodle_url('/local/airpay_skills/view.php',
                ['id' => $sid]))->out(false),
        ];
    }
    $radar_labels[]   = format_string($skill->name);
    $radar_current[]  = $current;
    $radar_expected[] = $expected;
}

// Recommended courses (link to skills with gaps).
$recs = [];
if (!empty($gap_rows)) {
    [$insql, $inparams] = $DB->get_in_or_equal(
        array_map(fn($g) => $g['skillid'], $gap_rows), SQL_PARAMS_NAMED, 'sid');
    $course_recs = $DB->get_records_sql(
        "SELECT DISTINCT c.id, c.fullname, c.shortname
           FROM {local_airpay_course_skills} cs
           JOIN {course} c ON c.id = cs.courseid
          WHERE cs.skillid $insql
            AND c.visible = 1
          LIMIT 20", $inparams);
    foreach ($course_recs as $c) {
        $recs[] = [
            'courseid'  => (int) $c->id,
            'fullname'  => format_string($c->fullname),
            'shortname' => format_string($c->shortname),
            'view_url'  => (new moodle_url('/course/view.php',
                ['id' => $c->id]))->out(false),
        ];
    }
}

// Grades — embed link to Moodle core gradebook for this user.
$grade_url = (new moodle_url('/grade/report/user/index.php',
    ['userid' => $userid]))->out(false);

$data = [
    'userid'           => (int) $userid,
    'fullname'         => fullname($user),
    'designation'      => (string) ($user->open_designation ?? '(no designation set)'),
    'has_designation'  => !empty($user->open_designation),

    'has_skills'       => count($skill_rows) > 0,
    'skill_rows'       => $skill_rows,
    'skill_count'      => count($skill_rows),
    'gap_count'        => count($gap_rows),
    'gap_rows'         => $gap_rows,
    'has_gaps'         => count($gap_rows) > 0,
    'has_recs'         => count($recs) > 0,
    'recommendations'  => $recs,

    'radar_labels_json'   => json_encode($radar_labels),
    'radar_current_json'  => json_encode($radar_current),
    'radar_expected_json' => json_encode($radar_expected),

    'grade_url'        => $grade_url,
    'profile_url'      => (new moodle_url('/local/airpay_users/profile.php',
        ['id' => $userid]))->out(false),
    'is_self'          => $is_self,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_users/skillprofile', $data);
echo $OUTPUT->footer();
