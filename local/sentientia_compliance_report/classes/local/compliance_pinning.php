<?php
// This file is part of Sentientia LMS.

/**
 * D4 data fix for the compliance list (post-demo plan, session 1, 2026-11-20).
 *
 * Nitin's decision D4: pin the active compliance rows for POSH 2025, AML,
 * Phishing and Infosec to Airpay; restrict "POSH for IC Members" to an IC
 * cohort; drop the deadline-based enrolment end date. The five rows were
 * tracked for "all tenants" (costcenterid 0), so the hourly refresh enrolled
 * every Public and ZEEA user into Airpay's courses, and everyone into the
 * IC-members-only course.
 *
 * Used by cli/pin_compliance_courses.php, dry run by default. Nothing here
 * runs from an upgrade step or a scheduled task.
 *
 * Courses are found by name, never by id: each active compliance row's course
 * is classified by its full name, short name and id number (classify()), and
 * everything found is printed for confirmation. A group that matches no
 * course, or more than one, is reported and left alone.
 *
 * The tenant a row is pinned to is the course's own tenant (the root of
 * course.open_path), unless the operator names one. A row pinned to some
 * other tenant already is reported and left alone.
 *
 * End dates: the old auto-enrol gave each enrolment timeend = timestart +
 * deadline days on the course's manual instance. Exactly those rows are
 * "deadline-based"; any other end date (set by an admin, imported from
 * BizLMS, or on another enrolment method) is counted and left alone. So is
 * the deadline end date of someone outside the course's tenants (enrolled by
 * the old cross-tenant refresh), and of someone outside the cohort the course
 * is restricted to (every Airpay employee the old refresh enrolled into the
 * IC-members-only course): it is what keeps them out of the course.
 *
 * @package    local_sentientia_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_compliance_report\local;

use local_sentientia_compliance_report\compliance_engine;

defined('MOODLE_INTERNAL') || die();

/**
 * Plan and apply the D4 pinning, cohort restriction and end-date clean-up.
 */
class compliance_pinning {

    /** The D4 groups, in print order. */
    public const KEYS = ['posh', 'infosec', 'aml', 'phishing', 'posh_ic'];

    /** Human names of the groups, for the operator's output. */
    public const LABELS = [
        'posh'     => 'POSH 2025',
        'infosec'  => 'Information security',
        'aml'      => 'Anti money laundering',
        'phishing' => 'Phishing awareness',
        'posh_ic'  => 'POSH for IC members',
    ];

    /** The group restricted to a cohort. */
    public const COHORT_KEY = 'posh_ic';

    /** Default id number of the IC cohort (looked up first, created only with --execute). */
    public const DEFAULT_COHORT_IDNUMBER = 'compliance_posh_ic_members';

    /** Default name of the IC cohort when it is created. */
    public const DEFAULT_COHORT_NAME = 'POSH Internal Committee members';

    /**
     * Which D4 groups a course belongs to, from its names.
     *
     * The IC course also says POSH; when the IC pattern matches, the plain
     * POSH group is dropped. More than one key back means the course is
     * ambiguous; the planner then leaves it alone.
     *
     * @param string $fullname
     * @param string $shortname
     * @param string $idnumber
     * @return string[] keys from self::KEYS
     */
    public static function classify(string $fullname, string $shortname = '', string $idnumber = ''): array {
        $text = trim($fullname . ' | ' . $shortname . ' | ' . $idnumber);
        $keys = [];
        $posh = (bool) preg_match('/\bPOSH\b/i', $text);
        // "Internal Committee" in any case; the abbreviation IC / ICC in capitals only.
        $ic = $posh && (preg_match('/internal\s+committee/i', $text) || preg_match('/\bICC?\b/', $text));
        if ($ic) {
            $keys[] = 'posh_ic';
        } else if ($posh && preg_match('/2025/', $text)) {
            $keys[] = 'posh';
        }
        if (preg_match('/information\s+security|\binfo-?sec\b|cyber\s*security/i', $text)) {
            $keys[] = 'infosec';
        }
        if (preg_match('/anti[\s-]*money[\s-]*laundering|\bAML\b/i', $text)) {
            $keys[] = 'aml';
        }
        if (preg_match('/phishing/i', $text)) {
            $keys[] = 'phishing';
        }
        return $keys;
    }

    /**
     * Work out what the fix would change. Writes nothing.
     *
     * @param array $options 'tenant' (int, 0 = each course's own tenant),
     *                       'cohortidnumber' (string), 'cohortname' (string)
     * @return array plan, see render()
     */
    public static function plan(array $options = []): array {
        global $DB;
        $forcedtenant = (int) ($options['tenant'] ?? 0);
        $cohortidnumber = trim((string) ($options['cohortidnumber'] ?? '')) ?: self::DEFAULT_COHORT_IDNUMBER;
        $cohortname = trim((string) ($options['cohortname'] ?? '')) ?: self::DEFAULT_COHORT_NAME;

        $plan = [
            'groups'    => [],
            'problems'  => [],
            'warnings'  => [],
            'unmatched' => [],
            'cohort'    => null,
            // Filled last: which end dates may be cleared depends on the
            // cohort planned for the IC course below.
            'enddates'  => [],
        ];

        // Every compliance row, with its course. Active rows are classified;
        // inactive rows only matter as merge targets for a pin.
        $rows = $DB->get_records('local_compliance_courses', null, 'sort_order, id');
        $courses = self::load_courses(array_map(fn($r) => (int) $r->courseid, $rows));

        // key => [courseid => true], from active rows only.
        $candidates = [];
        foreach ($rows as $row) {
            if (empty($row->is_active)) {
                continue;
            }
            $course = $courses[(int) $row->courseid] ?? null;
            if (!$course) {
                $plan['problems'][] = "Compliance row {$row->id} names course {$row->courseid}, which does not exist; left as is.";
                continue;
            }
            $keys = self::classify((string) $course->fullname, (string) $course->shortname, (string) $course->idnumber);
            if (count($keys) > 1) {
                $plan['problems'][] = 'Course ' . self::describe_course($course) . ' matches several groups ('
                    . implode(', ', $keys) . '); left as is.';
                continue;
            }
            if (!$keys) {
                $plan['unmatched'][(int) $course->id] = $course;
                continue;
            }
            $candidates[$keys[0]][(int) $course->id] = true;
        }

        foreach (self::KEYS as $key) {
            $ids = array_keys($candidates[$key] ?? []);
            if (!$ids) {
                $plan['problems'][] = 'No active compliance row found for ' . self::LABELS[$key] . '; nothing to do for it.';
                continue;
            }
            if (count($ids) > 1) {
                $names = array_map(fn($id) => self::describe_course($courses[$id]), $ids);
                $plan['problems'][] = 'Several courses match ' . self::LABELS[$key] . ': ' . implode('; ', $names)
                    . '. Left as is: fix the extra rows on the Configure tab, then run this again.';
                continue;
            }
            $course = $courses[$ids[0]];
            $group = self::plan_group($key, $course, $rows, $forcedtenant, $plan['problems'], $plan['warnings']);
            if ($group !== null) {
                $plan['groups'][$key] = $group;
            }
        }

        // The IC cohort, when the IC group is being fixed.
        if (isset($plan['groups'][self::COHORT_KEY])) {
            $cohort = $DB->get_record('cohort', ['idnumber' => $cohortidnumber], 'id, name, idnumber, contextid');
            $plan['cohort'] = [
                'id'       => $cohort ? (int) $cohort->id : 0,
                'idnumber' => $cohortidnumber,
                'name'     => $cohort ? (string) $cohort->name : $cohortname,
                'exists'   => (bool) $cohort,
                'members'  => $cohort ? $DB->count_records('cohort_members', ['cohortid' => $cohort->id]) : 0,
            ];
            $group = $plan['groups'][self::COHORT_KEY];
            $plan['cohort']['enrolled_outside'] = self::count_enrolled_outside_cohort(
                (int) $group['course']->id, $plan['cohort']['id']);
        }

        // The IC course's end dates are judged against the cohort execute()
        // restricts it to, not the row as it is now (unrestricted): id 0 when
        // execute() has yet to create the cohort, so nobody is a member.
        $plannedcohorts = [];
        if ($plan['cohort'] !== null) {
            $plannedcohorts[(int) $plan['groups'][self::COHORT_KEY]['course']->id] = (int) $plan['cohort']['id'];
        }
        $plan['enddates'] = self::deadline_enddates($plannedcohorts);

        return $plan;
    }

    /**
     * The pin actions for one group's course.
     *
     * @param string $key
     * @param \stdClass $course
     * @param \stdClass[] $rows every compliance row
     * @param int $forcedtenant 0 = the course's own tenant
     * @param string[] $problems appended to: the group is left alone
     * @param string[] $warnings appended to: the group is fixed, but read this
     * @return array|null null when the group cannot be fixed
     */
    private static function plan_group(string $key, \stdClass $course, array $rows, int $forcedtenant,
            array &$problems, array &$warnings): ?array {
        $owner = compliance_engine::tenant_id_from_path((string) ($course->open_path ?? ''));
        $tenant = $forcedtenant > 0 ? $forcedtenant : $owner;
        if ($tenant <= 0) {
            $problems[] = self::LABELS[$key] . ': course ' . self::describe_course($course)
                . ' has no tenant (no open_path). Pass --tenant=N to pin it; left as is.';
            return null;
        }
        try {
            \local_sentientia_platform\tenant::assert_valid($tenant);
        } catch (\Exception $e) {
            $problems[] = self::LABELS[$key] . ": tenant {$tenant} is not a known tenant; left as is.";
            return null;
        }
        if ($owner > 0 && $owner !== $tenant) {
            $warnings[] = self::LABELS[$key] . ': course ' . self::describe_course($course)
                . " belongs to tenant {$owner}, not {$tenant}. The refresh only reaches tenant {$tenant} if the"
                . ' course is shared to it.';
        }

        $courserows = array_filter($rows, fn($r) => (int) $r->courseid === (int) $course->id);
        $pinned = null;
        foreach ($courserows as $r) {
            if ((int) $r->costcenterid === $tenant) {
                $pinned = $r;
            }
        }

        $actions = [];
        foreach ($courserows as $r) {
            if (empty($r->is_active)) {
                continue;
            }
            $pin = (int) $r->costcenterid;
            if ($pin === $tenant) {
                $actions[] = ['type' => 'keep', 'rowid' => (int) $r->id, 'target' => (int) $r->id];
            } else if ($pin === 0 && $pinned === null) {
                $actions[] = ['type' => 'pin', 'rowid' => (int) $r->id, 'tenant' => $tenant, 'target' => (int) $r->id];
            } else if ($pin === 0) {
                // A pinned row exists already (unique on courseid + costcenterid):
                // switch the global row off and that one on.
                $actions[] = ['type' => 'merge', 'rowid' => (int) $r->id, 'into' => (int) $pinned->id,
                    'reactivate' => empty($pinned->is_active), 'target' => (int) $pinned->id];
            } else {
                $actions[] = ['type' => 'foreign', 'rowid' => (int) $r->id, 'pin' => $pin, 'target' => 0];
            }
        }

        return [
            'key'        => $key,
            'course'     => $course,
            'owner'      => $owner,
            'tenant'     => $tenant,
            'tenantname' => self::tenant_name($tenant),
            'actions'    => $actions,
        ];
    }

    /**
     * Apply a plan from plan(). Compliance rows and the cohort change in one
     * transaction; end dates are cleared (only when $clearenddates) through
     * the manual enrolment plugin, so the usual enrolment events fire.
     *
     * @param array $plan
     * @param bool $clearenddates
     * @return string[] what was done, one line each
     */
    public static function execute(array $plan, bool $clearenddates = false): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $done = [];
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $cohortid = 0;
        if ($plan['cohort'] !== null) {
            $cohortid = (int) $plan['cohort']['id'];
            if (!$plan['cohort']['exists']) {
                $cohortid = (int) cohort_add_cohort((object) [
                    'contextid'         => \context_system::instance()->id,
                    'name'              => $plan['cohort']['name'],
                    'idnumber'          => $plan['cohort']['idnumber'],
                    'description'       => 'Members of the POSH Internal Committee. The compliance report '
                        . 'assigns the POSH for IC Members course to this cohort only (decision D4, 2026-11-20).',
                    'descriptionformat' => FORMAT_HTML,
                    'visible'           => 1,
                    'component'         => '',
                ]);
                $done[] = "Created cohort '{$plan['cohort']['name']}' (id {$cohortid}, id number "
                    . "{$plan['cohort']['idnumber']}), with no members.";
            }
        }

        foreach ($plan['groups'] as $key => $group) {
            $label = self::LABELS[$key];
            foreach ($group['actions'] as $action) {
                switch ($action['type']) {
                    case 'pin':
                        $DB->update_record('local_compliance_courses', (object) [
                            'id' => $action['rowid'], 'costcenterid' => $action['tenant'], 'timemodified' => $now]);
                        $done[] = "{$label}: row {$action['rowid']} pinned to tenant {$action['tenant']}.";
                        break;
                    case 'merge':
                        $DB->update_record('local_compliance_courses', (object) [
                            'id' => $action['rowid'], 'is_active' => 0, 'timemodified' => $now]);
                        if ($action['reactivate']) {
                            $DB->update_record('local_compliance_courses', (object) [
                                'id' => $action['into'], 'is_active' => 1, 'timemodified' => $now]);
                        }
                        $done[] = "{$label}: all-tenant row {$action['rowid']} switched off; tenant row "
                            . "{$action['into']} " . ($action['reactivate'] ? 'switched on.' : 'kept.');
                        break;
                }
                if ($key === self::COHORT_KEY && $action['target'] > 0 && $cohortid > 0) {
                    $current = (int) $DB->get_field('local_compliance_courses', 'cohortid', ['id' => $action['target']]);
                    if ($current !== $cohortid) {
                        $DB->update_record('local_compliance_courses', (object) [
                            'id' => $action['target'], 'cohortid' => $cohortid, 'timemodified' => $now]);
                        $done[] = "{$label}: row {$action['target']} restricted to cohort {$cohortid}.";
                    }
                }
            }
        }

        $transaction->allow_commit();

        if ($clearenddates) {
            $cleared = self::clear_enddates($plan['enddates']);
            $done[] = "Cleared {$cleared} deadline-based enrolment end dates.";
        }

        compliance_engine::purge_kpi_cache();
        return $done;
    }

    /**
     * Enrolments with a deadline-based end date, per tracked course.
     *
     * Every compliance row counts here, active or not, because the old
     * auto-enrol set these end dates for any tracked course.
     *
     * Only the end dates of people the course reaches are cleared: their own
     * tenant is the course's tenant, a tenant it is shared to, or the course
     * has no owner (compliance_engine::course_reaches_tenant()). The old
     * refresh also auto-enrolled every Public and ZEEA user into Airpay's
     * courses (the A1 defect). Their expired end date is what keeps them out
     * of a course that was never theirs, so it is counted as 'outside' and
     * left alone; their enrolment is not removed either (decision D3).
     *
     * A course restricted to a cohort (cohort_gates()) is cleared for that
     * cohort's members only. The old refresh enrolled every Airpay employee
     * into "POSH for IC Members" with a deadline end date; clearing it for a
     * non-member would open the IC-only course to them (D4). Those are
     * counted as 'noncohort' and left alone. The IC cohort starts empty, so
     * the first run clears no IC end date; run it again once L&D has added
     * the members. On a course with no cohort the tenant pin is not checked:
     * it decides who is tracked, not who may open a course their tenant owns
     * or is shared (a share to Public lets Public users' end dates go).
     *
     * A deadline-based enrolment that is already suspended (the site's
     * "expired enrolment" action) is still cleared, but clearing the end
     * date does not reactivate it; it is counted as 'suspended'.
     *
     * @param array $plannedcohorts courseid => the cohort id plan() restricts the
     *              course to (0 = a cohort execute() has yet to create: no members)
     * @return array courseid => ['course' => \stdClass|null, 'matching' => [ue rows],
     *               'expired' => int, 'suspended' => int, 'outside' => int,
     *               'noncohort' => int, 'other' => int,
     *               'gate' => '' | 'cohort' | 'unrestricted' (see cohort_gates())]
     */
    public static function deadline_enddates(array $plannedcohorts = []): array {
        global $DB;
        $spans = [];
        $rows = $DB->get_records('local_compliance_courses', null, '',
            'id, courseid, costcenterid, deadline_days, is_active, cohortid');
        foreach ($rows as $row) {
            $spans[(int) $row->courseid][(int) $row->deadline_days * DAYSECS] = true;
        }
        if (!$spans) {
            return [];
        }
        $courses = self::load_courses(array_keys($spans));
        $audiences = compliance_engine::course_audiences($rows);
        $gates = self::cohort_gates($rows, $courses, $plannedcohorts);
        $gatecohorts = [];
        foreach ($gates as $gate) {
            foreach ($gate['rules'] as $rule) {
                if ((int) $rule['cohort'] > 0) {
                    $gatecohorts[(int) $rule['cohort']] = (object) ['cohortid' => (int) $rule['cohort']];
                }
            }
        }
        // cohortid => [userid => true]; a deleted cohort has no members (fail closed).
        $members = compliance_engine::cohort_members($gatecohorts);
        $userpath = array_key_exists('open_path', $DB->get_columns('user')) ? 'u.open_path' : "''";
        $now = time();
        $out = [];
        foreach ($spans as $courseid => $wanted) {
            $gate = $gates[$courseid] ?? null;
            $entry = ['course' => $courses[$courseid] ?? null, 'matching' => [], 'expired' => 0,
                'suspended' => 0, 'outside' => 0, 'noncohort' => 0, 'other' => 0,
                'gate' => $gate['reason'] ?? ''];
            $rs = $DB->get_recordset_sql(
                "SELECT ue.id, ue.userid, ue.enrolid, ue.status, ue.timestart, ue.timeend, e.enrol,
                        {$userpath} AS userpath
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {user} u ON u.id = ue.userid
                  WHERE e.courseid = :courseid AND ue.timeend > 0
               ORDER BY ue.id",
                ['courseid' => $courseid]);
            foreach ($rs as $ue) {
                $span = (int) $ue->timeend - (int) $ue->timestart;
                if ($ue->enrol !== 'manual' || (int) $ue->timestart <= 0 || !isset($wanted[$span])) {
                    $entry['other']++;
                    continue;
                }
                $usertenant = compliance_engine::tenant_id_from_path((string) ($ue->userpath ?? ''));
                if (!compliance_engine::course_reaches_tenant((int) $courseid, $usertenant, $audiences)) {
                    $entry['outside']++;
                    continue;
                }
                if ($gate !== null && !self::cohort_admits($gate['rules'], $usertenant, (int) $ue->userid, $members)) {
                    $entry['noncohort']++;
                    continue;
                }
                unset($ue->userpath);
                $entry['matching'][] = $ue;
                if ((int) $ue->timeend <= $now) {
                    $entry['expired']++;
                }
                if ((int) $ue->status !== ENROL_USER_ACTIVE) {
                    $entry['suspended']++;
                }
            }
            $rs->close();
            $out[$courseid] = $entry;
        }
        return $out;
    }

    /**
     * Which tracked courses only a cohort may keep open, for deadline_enddates().
     *
     * 'cohort': the course plan() restricts to the IC cohort (the planned
     * cohort counts, even before execute() writes it, and for every tenant),
     * or a course with a cohort on at least one of its rows (its active rows,
     * or all its rows when none is active); each row then admits its own pin's
     * people (cohort_admits()). 'unrestricted': a course named for IC members
     * (classify()) that is not restricted to any cohort yet, for instance
     * because the plan left the IC group alone over a problem; nobody's end
     * date on it is cleared until it is. A course absent from the result has
     * no cohort on any row and admits everyone it reaches.
     *
     * @param \stdClass[] $rows every compliance row (courseid, costcenterid, is_active, cohortid)
     * @param \stdClass[] $courses from load_courses()
     * @param array $plannedcohorts courseid => cohort id (0 = not created yet)
     * @return array courseid => ['reason' => 'cohort'|'unrestricted',
     *               'rules' => [['pin' => int, 'cohort' => int|null]]]: cohort null = no
     *               cohort, 0 = a cohort with no members yet
     */
    private static function cohort_gates(array $rows, array $courses, array $plannedcohorts): array {
        $bycourse = [];
        foreach ($rows as $row) {
            $bycourse[(int) $row->courseid][] = $row;
        }
        $gates = [];
        foreach ($bycourse as $courseid => $courserows) {
            if (array_key_exists($courseid, $plannedcohorts)) {
                $gates[$courseid] = ['reason' => 'cohort',
                    'rules' => [['pin' => 0, 'cohort' => (int) $plannedcohorts[$courseid]]]];
                continue;
            }
            $active = array_filter($courserows, fn($r) => !empty($r->is_active));
            $rules = [];
            $restricted = false;
            foreach ($active ?: $courserows as $r) {
                $cohortid = (int) ($r->cohortid ?? 0);
                $restricted = $restricted || $cohortid > 0;
                $rules[] = ['pin' => (int) $r->costcenterid, 'cohort' => $cohortid > 0 ? $cohortid : null];
            }
            if ($restricted) {
                $gates[$courseid] = ['reason' => 'cohort', 'rules' => $rules];
                continue;
            }
            $course = $courses[$courseid] ?? null;
            if ($course && in_array(self::COHORT_KEY, self::classify((string) $course->fullname,
                    (string) $course->shortname, (string) ($course->idnumber ?? '')), true)) {
                $gates[$courseid] = ['reason' => 'unrestricted', 'rules' => [['pin' => 0, 'cohort' => 0]]];
            }
        }
        return $gates;
    }

    /**
     * Does a cohort-restricted course admit this user?
     *
     * The rules for the user's tenant (pin 0 or the user's tenant) decide;
     * when none is for that tenant (a tenant the course is shared to, while
     * its rows are pinned elsewhere), all of them do. One rule with no
     * cohort, or with a cohort that holds the user, admits.
     *
     * @param array $rules from cohort_gates()
     * @param int $usertenant
     * @param int $userid
     * @param array $members from compliance_engine::cohort_members()
     * @return bool
     */
    private static function cohort_admits(array $rules, int $usertenant, int $userid, array $members): bool {
        $fortenant = array_filter($rules, fn($r) => $r['pin'] === 0 || $r['pin'] === $usertenant);
        foreach ($fortenant ?: $rules as $rule) {
            if ($rule['cohort'] === null || !empty($members[$rule['cohort']][$userid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove the end date from each deadline-based enrolment.
     *
     * @param array $enddates from deadline_enddates()
     * @return int enrolments changed
     */
    public static function clear_enddates(array $enddates): int {
        global $DB;
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return 0;
        }
        $instances = [];
        $count = 0;
        foreach ($enddates as $entry) {
            foreach ($entry['matching'] as $ue) {
                $enrolid = (int) $ue->enrolid;
                if (!array_key_exists($enrolid, $instances)) {
                    $instances[$enrolid] = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'manual']);
                }
                if (!$instances[$enrolid]) {
                    continue;
                }
                // Re-read: skip a row changed since the plan was made.
                $current = $DB->get_record('user_enrolments', ['id' => $ue->id], 'id, timestart, timeend');
                if (!$current || (int) $current->timeend !== (int) $ue->timeend
                        || (int) $current->timestart !== (int) $ue->timestart) {
                    continue;
                }
                $plugin->update_user_enrol($instances[$enrolid], (int) $ue->userid, null, null, 0);
                $count++;
            }
        }
        return $count;
    }

    /**
     * The plan as text for the operator.
     *
     * @param array $plan from plan()
     * @param bool $execute whether execute() will follow
     * @param bool $clearenddates whether end dates will be cleared
     * @return string[] lines
     */
    public static function render(array $plan, bool $execute, bool $clearenddates): array {
        $will = $execute ? 'WILL' : 'would';
        $lines = [];
        $lines[] = $execute ? '== D4 compliance fix: EXECUTE ==' : '== D4 compliance fix: DRY RUN (nothing is written) ==';
        $lines[] = '';
        $lines[] = 'Courses found (check each one before running with --execute):';
        foreach (self::KEYS as $key) {
            if (!isset($plan['groups'][$key])) {
                continue;
            }
            $g = $plan['groups'][$key];
            $lines[] = sprintf('  [%s] %s', self::LABELS[$key], self::describe_course($g['course']));
            $lines[] = sprintf('      course tenant: %s; pin to: %d (%s)',
                $g['owner'] > 0 ? (string) $g['owner'] : 'none', $g['tenant'], $g['tenantname']);
            foreach ($g['actions'] as $a) {
                switch ($a['type']) {
                    case 'keep':
                        $lines[] = "      row {$a['rowid']}: already pinned to tenant {$g['tenant']}; no change.";
                        break;
                    case 'pin':
                        $lines[] = "      row {$a['rowid']}: all tenants -> tenant {$a['tenant']} ({$will} change).";
                        break;
                    case 'merge':
                        $lines[] = "      row {$a['rowid']}: all tenants; tenant row {$a['into']} exists, so row {$a['rowid']}"
                            . " {$will} be switched off" . ($a['reactivate'] ? " and row {$a['into']} switched on." : '.');
                        break;
                    case 'foreign':
                        $lines[] = "      row {$a['rowid']}: pinned to tenant {$a['pin']}; left as is.";
                        break;
                }
            }
        }

        if ($plan['cohort'] !== null) {
            $c = $plan['cohort'];
            $lines[] = '';
            $lines[] = 'IC cohort:';
            if ($c['exists']) {
                $lines[] = "  cohort '{$c['name']}' (id {$c['id']}, id number {$c['idnumber']}) exists with {$c['members']} members.";
            } else {
                $lines[] = "  no cohort with id number {$c['idnumber']}: {$will} create '{$c['name']}' in the system context,"
                    . ' with no members.';
            }
            $lines[] = "  The POSH for IC members row {$will} be restricted to it: from the next hourly refresh only its"
                . ' members are tracked, auto-enrolled and reminded.';
            if ($c['members'] === 0) {
                $lines[] = '  The cohort has no members: until L&D adds the IC members (Site administration > Users >'
                    . ' Cohorts > Assign), the course is tracked for nobody.';
            }
            $lines[] = "  {$c['enrolled_outside']} people are enrolled in the IC course but not in the cohort. Their enrolments"
                . ' are not removed (decision D3); review them on the course Participants page.';
        }

        $lines[] = '';
        $lines[] = 'Deadline-based enrolment end dates (manual enrolment, end = start + deadline days):';
        $total = 0;
        $outside = 0;
        $noncohort = 0;
        $unrestricted = 0;
        $suspended = 0;
        foreach ($plan['enddates'] as $courseid => $e) {
            $n = count($e['matching']);
            $total += $n;
            $outside += $e['outside'];
            $suspended += $e['suspended'];
            $name = $e['course'] ? self::describe_course($e['course']) : "course {$courseid} (missing)";
            $gate = '';
            if ($e['gate'] === 'cohort') {
                $noncohort += $e['noncohort'];
                $gate = " {$e['noncohort']} not in the course's cohort;";
            } else if ($e['gate'] === 'unrestricted') {
                $unrestricted += $e['noncohort'];
                $gate = " {$e['noncohort']} kept because this IC-members course is not restricted to a cohort yet;";
            }
            $lines[] = "  {$name}: {$n} with a deadline end date ({$e['expired']} already ended, learner locked out;"
                . " {$e['suspended']} suspended); {$e['outside']} outside the course's tenants;{$gate}"
                . " {$e['other']} other end dates left as is.";
        }
        if ($clearenddates) {
            $lines[] = "  {$total} end dates {$will} be cleared.";
        } else {
            $lines[] = "  {$total} in all. Not cleared: add --clear-enddates (with --execute) to clear them.";
        }
        if ($suspended > 0) {
            $lines[] = "  {$suspended} of them are suspended (the site's expired-enrolment action). Clearing the end"
                . ' date does not reactivate them: review them on the course Participants page.';
        }
        if ($outside > 0) {
            $lines[] = "  {$outside} deadline end dates belong to people outside the course's tenants (the old refresh"
                . ' enrolled other tenants\' users into these courses). They are never cleared: the ended enrolment'
                . ' keeps them out. Their enrolments are not removed (decision D3); review them on the course'
                . ' Participants page.';
        }
        if ($noncohort > 0) {
            $lines[] = "  {$noncohort} deadline end dates belong to people who are not in the cohort a course is restricted"
                . ' to (the old refresh enrolled every employee into the POSH for IC members course). They are never'
                . ' cleared: the ended enrolment keeps them out of a course for cohort members only. Their enrolments'
                . ' are not removed (decision D3). Once L&D has added the IC members to the cohort, run'
                . ' --execute --clear-enddates again to clear the members\' end dates.';
        }
        if ($unrestricted > 0) {
            $lines[] = "  {$unrestricted} deadline end dates are on an IC-members course that is not restricted to a cohort."
                . ' They are kept until it is (see the Problems section for POSH for IC members).';
        }
        $lines[] = '  New auto-enrolments no longer get an end date (code change in version 2026111901).';

        if ($plan['unmatched']) {
            $lines[] = '';
            $lines[] = 'Other active compliance courses (not part of D4, left as is):';
            foreach ($plan['unmatched'] as $course) {
                $lines[] = '  ' . self::describe_course($course);
            }
        }
        if ($plan['warnings']) {
            $lines[] = '';
            $lines[] = 'Warnings:';
            foreach ($plan['warnings'] as $w) {
                $lines[] = '  - ' . $w;
            }
        }
        if ($plan['problems']) {
            $lines[] = '';
            $lines[] = 'Problems (nothing is changed for these):';
            foreach ($plan['problems'] as $p) {
                $lines[] = '  - ' . $p;
            }
        }
        return $lines;
    }

    /**
     * Courses by id, with open_path where the column exists.
     *
     * @param int[] $ids
     * @return \stdClass[] id => record
     */
    private static function load_courses(array $ids): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $fields = 'id, fullname, shortname, idnumber';
        if (array_key_exists('open_path', $DB->get_columns('course'))) {
            $fields .= ', open_path';
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'pinc');
        return $DB->get_records_select('course', "id {$insql}", $params, '', $fields);
    }

    /**
     * People enrolled in a course (any method) who are not in a cohort.
     *
     * @param int $courseid
     * @param int $cohortid 0 = a cohort that does not exist yet (no members)
     * @return int
     */
    private static function count_enrolled_outside_cohort(int $courseid, int $cohortid): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND NOT EXISTS (SELECT 1 FROM {cohort_members} cm
                                 WHERE cm.cohortid = :cohortid AND cm.userid = ue.userid)",
            ['courseid' => $courseid, 'cohortid' => $cohortid]);
    }

    /**
     * "id 383, PT2025, idnumber '', 'POSH Training 2025', open_path /1".
     *
     * @param \stdClass $course
     * @return string
     */
    private static function describe_course(\stdClass $course): string {
        return sprintf("id %d, shortname '%s', idnumber '%s', '%s', open_path %s",
            (int) $course->id, (string) $course->shortname, (string) ($course->idnumber ?? ''),
            (string) $course->fullname,
            ((string) ($course->open_path ?? '')) !== '' ? (string) $course->open_path : '(none)');
    }

    /**
     * A tenant's name for the output, or "tenant N".
     *
     * @param int $tenant
     * @return string
     */
    private static function tenant_name(int $tenant): string {
        $name = '';
        if (class_exists('\local_sentientia_org\org_manager')) {
            $name = (string) \local_sentientia_org\org_manager::get_name($tenant);
        }
        return $name !== '' ? $name : "tenant {$tenant}";
    }
}
