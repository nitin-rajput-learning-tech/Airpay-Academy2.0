<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * ADR-032 importer for gap G6, the orphaned BizLMS enrol instances (mapping doc, section 21; decision
 * gap.orphan_enrol_instances = convert_to_manual).
 *
 * BizLMS enrolled learners through three enrol methods of its own, enrol_classroom, enrol_program and
 * enrol_learningplan. Their rows are plain core rows: an {enrol} instance with enrol = classroom, program or
 * learningplan, and the {user_enrolments} on it. The BizLMS code is not deployed after cutover, so those
 * instances are orphans. Each enrolment on one becomes a MANUAL enrolment in the same course, with the same
 * status, start and end, so the learner does not depend on code that is no longer there:
 *
 *  - step enrolments.instances ensures each course that has such enrolments also has an ENABLED manual
 *    instance (the existing one, else a new row in {enrol});
 *  - step enrolments.enrolments gives each learner a row in {user_enrolments} on that instance.
 *
 * What it never does: delete or change a legacy instance or enrolment, fire an event, send a message, call the
 * enrol API, or touch {role_assignments}. The role the legacy instance gave the learner stays as it is
 * (BizLMS wrote its role assignments with component empty and itemid 0, roles_protected() false, so they do
 * not belong to the instance either): preflight counts what the restored database holds and warns about the
 * learners and components that would surprise.
 *
 * How this fits the frozen framework (the "framework needs" are in the 2026-09-30 state card note):
 *
 *  - Both sources are core tables with a filter (the BizLMS methods), so the generic accounting identity and the
 *    whole-table check unmapped_rows cannot hold for them (core user_enrolments keeps changing after go-live).
 *    Each step is therefore a derived unit and verify() carries the real identity. enrolments.enrolments is one
 *    unit per legacy row (group_by id), which keeps one map row, and one report line, per legacy enrolment.
 *  - The registry wants a step's declared target to be a table of the plugin's own schema, and core tables only
 *    in core_writes(). The primary outcome of each step is the reviewed core INSERT (or a fold into the row that
 *    already does the job); the declared target is the ledger local_sentientia_courses_enrolmove, which gets one
 *    row per converted enrolment: the original method and instance, in ids only. The map's detail column may
 *    not carry an id, so the ledger is the one durable place that says which BizLMS instance an enrolment came
 *    from once the legacy tables are ever archived.
 *  - A learner can hold ONE enrolment per instance (unique enrolid, userid), and the same course is often in
 *    several BizLMS plans, so several legacy rows can lead to the same manual enrolment. The lowest id of the
 *    learner-course pair owns the conversion; the others fold into what it produced.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolments_importer implements importer {

    /** Feature key. */
    public const FEATURE = 'enrolments';

    /** Plugin version that carries the ledger table (version.php, db/upgrade.php). */
    public const REQUIRES_VERSION = 2026100101;

    /** The owner decision this importer carries out. */
    public const DECISION = 'gap.orphan_enrol_instances';

    /** The only value of that decision this importer implements. */
    public const DECISION_VALUE = 'convert_to_manual';

    /**
     * The three BizLMS enrol methods, as stored in {enrol}.enrol.
     *
     * @var string[]
     */
    public const METHODS = ['classroom', 'learningplan', 'program'];

    /** The enrol method the learners are converted to. */
    public const MANUAL = 'manual';

    /** The accounting unit of enrolments.instances: one group per course. */
    public const UNIT_INSTANCES = '#enrol.courseid';

    /** The accounting unit of enrolments.enrolments: one group per legacy user_enrolments row. */
    public const UNIT_ENROLMENTS = '#user_enrolments.id';

    /** The ledger: one row per enrolment this import converted. */
    public const LEDGER = 'local_sentientia_courses_enrolmove';

    /** Default --atomic-threshold of the CLI: above it the feature runs in batch mode. */
    private const DEFAULT_ATOMIC_THRESHOLD = 50000;

    public function feature(): string {
        return self::FEATURE;
    }

    public function component(): string {
        return 'local_sentientia_courses';
    }

    public function requires_version(): int {
        return self::REQUIRES_VERSION;
    }

    public function depends(): array {
        // No tenant column and no other feature's table: the BizLMS instance rows are read from core, and the
        // classroom, program and learning plan importers do not rewrite them (their enrol references stay valid).
        return [];
    }

    public function sources(): array {
        // Two core tables read as sources, like logstore_standard_log is. They always exist, so the feature is
        // applicable everywhere and simply finds nothing to convert on a database that never had BizLMS.
        return [
            'enrol' => new source_spec('enrol'),
            'user_enrolments' => new source_spec('user_enrolments'),
        ];
    }

    public function declined_tables(): array {
        return [];
    }

    public function target_tables(): array {
        return [self::LEDGER];
    }

    public function core_writes(): array {
        return [
            'enrol' => 'gap.orphan_enrol_instances (G6): a new enabled manual instance for a course that has none; '
                . 'insert only, an existing instance is never changed',
            'user_enrolments' => 'gap.orphan_enrol_instances (G6): one manual enrolment per orphaned BizLMS enrolment; '
                . 'insert only, with the legacy status, start and end',
        ];
    }

    public function tenant_columns(): array {
        return [];
    }

    public function reasons(): array {
        return [
            // The course already has an enabled manual instance: the learners go on it, nothing is created.
            new reason('manual_instance_exists', false, false),
            // The course the BizLMS instance belongs to is gone: there is nothing to enrol into.
            new reason('course_missing', false, false),
            // The learner row is gone, or the account is deleted: no access to carry over.
            new reason('user_missing', false, false),
            new reason('user_deleted', false, false),
            // The BizLMS instance row is gone between the source scan and the transform. Cannot happen under the
            // source filter; kept so an impossible row is recorded, not guessed.
            new reason('instance_missing', false, false),
            // The learner is already enrolled manually and that enrolment does the job.
            new reason('already_manual', false, false),
            // Another legacy row of the same learner and course owns the conversion; this row follows it.
            new reason('duplicate_pair', false, false),
            // The learner has a manual enrolment that does not give access today (suspended or outside its dates)
            // while the legacy one does: converting would not keep the access, and changing the manual enrolment is
            // an administrator's decision, not the import's.
            new reason('manual_enrolment_inactive', false, true),
        ];
    }

    public function decisions(): array {
        return [
            new decision(self::DECISION,
                'Orphaned BizLMS enrol instances (gap G6): convert each enrolment on them to a manual enrolment in the '
                . 'same course, keeping status, start and end',
                true, null, [self::DECISION_VALUE]),
        ];
    }

    public function atomic(): bool {
        return true;
    }

    public function steps(): array {
        return [new enrolments_instances_step(), new enrolments_step()];
    }

    /**
     * An IN list over the three BizLMS methods, with named parameters.
     *
     * @param string $prefix Parameter name prefix. A query that lists the methods twice needs two prefixes.
     * @return array{0: string, 1: array<string, string>} [placeholders, params]
     */
    public static function methods_in(string $prefix = 'blmm'): array {
        $names = [];
        $params = [];
        foreach (self::METHODS as $i => $method) {
            $name = $prefix . $i;
            $names[] = ':' . $name;
            $params[$name] = $method;
        }
        return [implode(', ', $names), $params];
    }

    /**
     * Rows of {user_enrolments} on a BizLMS instance. The source filter of enrolments.enrolments.
     *
     * @param string $alias Alias of user_enrolments in the caller's query.
     * @return array{0: string, 1: array} [sql, params]
     */
    public static function enrolment_filter(string $alias = 't'): array {
        [$in, $params] = self::methods_in('blmf');
        return ["{$alias}.enrolid IN (SELECT bi.id FROM {enrol} bi WHERE bi.enrol IN ({$in}))", $params];
    }

    /**
     * Rows of {enrol} that are BizLMS instances with at least one enrolment. The source filter of
     * enrolments.instances: an instance nobody is enrolled on needs no manual instance.
     *
     * @param string $alias Alias of enrol in the caller's query.
     * @return array{0: string, 1: array} [sql, params]
     */
    public static function instance_filter(string $alias = 't'): array {
        [$in, $params] = self::methods_in('blmg');
        return ["{$alias}.enrol IN ({$in}) AND EXISTS (SELECT 1 FROM {user_enrolments} bue WHERE bue.enrolid = {$alias}.id)",
            $params];
    }

    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();

        // The runner records a missing or unaccepted decision as a blocker and still calls this method, and
        // context::decision() throws in exactly those cases. The blocker is already there: say nothing more.
        try {
            $value = $ctx->decision(self::DECISION);
        } catch (blocked $e) {
            return $pf;
        }
        // Reading the value ties the code below to it: a future second value must not run this importer's code.
        if ($value !== self::DECISION_VALUE) {
            $pf->block('decision_value_not_supported:' . self::DECISION);
            return $pf;
        }

        // A manual enrolment on a site whose manual enrol plugin is off grants nothing: the learners would
        // lose exactly what this import exists to keep.
        if (!array_key_exists(self::MANUAL, enrol_get_plugins(true))) {
            $pf->block('manual_enrolment_plugin_disabled');
        }

        [$in, $mp] = self::methods_in('blmp');
        $rows = $DB->get_records_sql(
            "SELECT MIN(e.id) AS k, e.enrol AS v, COUNT(1) AS n FROM {enrol} e WHERE e.enrol IN ({$in}) GROUP BY e.enrol",
            $mp);
        $bymethod = [];
        foreach ($rows as $row) {
            $bymethod[(string) $row->v] = (int) $row->n;
        }
        $pf->histogram('enrol.enrol[bizlms instances]', $bymethod);
        $pf->count('bizlms_instances', array_sum($bymethod));

        [$filter, $fp] = self::enrolment_filter('t');
        $total = $this->count("SELECT COUNT(1) FROM {user_enrolments} t WHERE {$filter}", $fp);
        $pf->count('bizlms_enrolments', $total);
        if ($total === 0) {
            return $pf;
        }
        if ($total > self::DEFAULT_ATOMIC_THRESHOLD) {
            $pf->warn('source_above_the_default_atomic_threshold:' . $total);
        }

        // The status of the rows the import will copy. Moodle defines 0 (active) and 1 (suspended); a plugin may
        // define its own above 10. The BizLMS methods define none, and an unknown value is never guessed.
        $statuses = $DB->get_records_sql(
            "SELECT MIN(t.id) AS k, t.status AS v, COUNT(1) AS n FROM {user_enrolments} t WHERE {$filter} GROUP BY t.status",
            $fp);
        $histogram = [];
        foreach ($statuses as $row) {
            $histogram[(string) $row->v] = (int) $row->n;
            if (!in_array((int) $row->v, [0, 1], true)) {
                $pf->block('unknown_enum:user_enrolments.status=' . (int) $row->v);
            }
        }
        $pf->histogram('user_enrolments.status[bizlms enrolments]', $histogram);

        $pairs = $this->pairs_sql('blmq');
        $pf->count('pairs', $this->count("SELECT COUNT(1) FROM ({$pairs[0]}) p", $pairs[1]));
        $dup = $this->pairs_sql('blmr', 'HAVING COUNT(1) > 1');
        $pf->count('pairs_with_several_legacy_rows', $this->count("SELECT COUNT(1) FROM ({$dup[0]}) p", $dup[1]));

        // The figure the migration plan calls "enrolled only through a BizLMS enrol method": the learners who
        // would have no other way into the course.
        [$in1, $p1] = self::methods_in('blms');
        [$in2, $p2] = self::methods_in('blmt');
        $only = $this->count(
            "SELECT COUNT(1) FROM (
                SELECT 1 AS x FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.enrol IN ({$in1})
                   AND NOT EXISTS (SELECT 1 FROM {user_enrolments} ou JOIN {enrol} oe ON oe.id = ou.enrolid
                                    WHERE oe.courseid = e.courseid AND ou.userid = ue.userid
                                      AND oe.enrol NOT IN ({$in2}))
              GROUP BY e.courseid, ue.userid) p", $p1 + $p2);
        $pf->count('pairs_enrolled_only_through_bizlms', $only);

        $this->preflight_instances($pf);
        $this->preflight_roles($pf);

        [$in3, $p3] = self::methods_in('blmu');
        $disabled = $this->count(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol IN ({$in3}) AND e.status <> 0", $p3);
        if ($disabled > 0) {
            // BizLMS grants nothing on a disabled instance, so these rows are converted as suspended: the import never
            // gives a learner access that BizLMS did not give.
            $pf->warn('enrolments_on_disabled_bizlms_instances:' . $disabled);
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $open = (int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0;
        $map = '{' . legacymap::TABLE . '}';
        $now = time();

        // Accounting for the two derived units, which the generic check leaves to the importer: every course with
        // a BizLMS instance that has enrolments, and every BizLMS enrolment, has exactly one primary map row.
        [$ifilter, $ip] = self::instance_filter('e');
        $courses = "SELECT e.courseid FROM {enrol} e WHERE {$ifilter} GROUP BY e.courseid";
        $source = $this->count("SELECT COUNT(1) FROM ({$courses}) c", $ip);
        $mapped = $DB->count_records(legacymap::TABLE, [
            'feature' => self::FEATURE, 'sourcetable' => self::UNIT_INSTANCES, 'subkey' => '']);
        if ($source !== $mapped) {
            $failures[] = 'accounting:' . self::UNIT_INSTANCES . ": source={$source} mapped={$mapped}";
        }
        $unmapped = $this->count(
            "SELECT COUNT(1) FROM ({$courses}) c WHERE NOT EXISTS (SELECT 1 FROM {$map} m
              WHERE m.sourcetable = :blmst AND m.subkey = :blmsk AND m.sourceid = c.courseid)",
            $ip + ['blmst' => self::UNIT_INSTANCES, 'blmsk' => '']);
        if ($unmapped > 0) {
            $failures[] = 'unmapped_source_rows:' . self::UNIT_INSTANCES . ':' . $unmapped;
        }

        [$efilter, $ep] = self::enrolment_filter('t');
        $source = $this->count("SELECT COUNT(1) FROM {user_enrolments} t WHERE {$efilter}", $ep);
        $mapped = $DB->count_records(legacymap::TABLE, [
            'feature' => self::FEATURE, 'sourcetable' => self::UNIT_ENROLMENTS, 'subkey' => '']);
        if ($source !== $mapped) {
            $failures[] = 'accounting:' . self::UNIT_ENROLMENTS . ": source={$source} mapped={$mapped}";
        }
        $unmapped = $this->count(
            "SELECT COUNT(1) FROM {user_enrolments} t WHERE {$efilter} AND NOT EXISTS (SELECT 1 FROM {$map} m
              WHERE m.sourcetable = :blmst AND m.subkey = :blmsk AND m.sourceid = t.id)",
            $ep + ['blmst' => self::UNIT_ENROLMENTS, 'blmsk' => '']);
        if ($unmapped > 0) {
            $failures[] = 'unmapped_source_rows:' . self::UNIT_ENROLMENTS . ':' . $unmapped;
        }

        // The ledger and the map agree one to one: a ledger row for every converted enrolment and no other.
        $ledger = $DB->count_records(self::LEDGER);
        $imported = $this->count(
            "SELECT COUNT(1) FROM {$map} m WHERE m.feature = :blmf AND m.sourcetable = :blmst AND m.subkey = :blmsk
                AND m.outcome = :blmoc AND m.targettable = :blmtt",
            ['blmf' => self::FEATURE, 'blmst' => self::UNIT_ENROLMENTS, 'blmsk' => '', 'blmoc' => 'imported',
                'blmtt' => 'user_enrolments']);
        if ($ledger !== $imported) {
            $failures[] = "ledger_rows_differ_from_imported_map_rows: ledger={$ledger} imported={$imported}";
        }
        $strays = $this->count(
            'SELECT COUNT(1) FROM {' . self::LEDGER . "} r WHERE NOT EXISTS (SELECT 1 FROM {$map} m
              WHERE m.feature = :blmf AND m.sourcetable = :blmst AND m.subkey = :blmsk AND m.outcome = :blmoc
                AND m.sourceid = r.legacyueid)",
            ['blmf' => self::FEATURE, 'blmst' => self::UNIT_ENROLMENTS, 'blmsk' => '', 'blmoc' => 'imported']);
        if ($strays > 0) {
            $failures[] = 'ledger_rows_without_an_imported_map_row:' . $strays;
        }

        if ($open) {
            // Once the site is open an administrator may suspend, move or remove any enrolment, and disable an
            // instance: what follows is a statement about the moment of the import, not about the site today.
            return $failures;
        }

        // Every manual instance the map names is an enabled manual instance of the right course.
        $badinstance = $this->count(
            "SELECT COUNT(1) FROM {$map} m LEFT JOIN {enrol} ne ON ne.id = m.targetid
              WHERE m.feature = :blmf AND m.sourcetable = :blmst AND m.subkey = :blmsk AND m.targettable = :blmtt
                AND m.outcome IN ('imported', 'folded')
                AND (ne.id IS NULL OR ne.enrol <> :blmman OR ne.status <> 0 OR ne.courseid <> m.sourceid)",
            ['blmf' => self::FEATURE, 'blmst' => self::UNIT_INSTANCES, 'blmsk' => '', 'blmtt' => 'enrol',
                'blmman' => self::MANUAL]);
        if ($badinstance > 0) {
            $failures[] = 'map_names_an_instance_that_is_not_an_enabled_manual_instance_of_the_course:' . $badinstance;
        }

        // Every enrolment the map says was converted or followed is a manual enrolment of the same learner in the
        // same course.
        $joins = "FROM {$map} m
                  JOIN {user_enrolments} lue ON lue.id = m.sourceid
                  JOIN {enrol} le ON le.id = lue.enrolid
             LEFT JOIN {user_enrolments} nue ON nue.id = m.targetid
             LEFT JOIN {enrol} ne ON ne.id = nue.enrolid
                 WHERE m.feature = :blmf AND m.sourcetable = :blmst AND m.subkey = :blmsk AND m.targettable = :blmtt
                   AND m.outcome IN ('imported', 'folded')";
        $base = ['blmf' => self::FEATURE, 'blmst' => self::UNIT_ENROLMENTS, 'blmsk' => '', 'blmtt' => 'user_enrolments'];
        $wrong = $this->count(
            "SELECT COUNT(1) {$joins}
                AND (nue.id IS NULL OR ne.enrol <> :blmman OR ne.courseid <> le.courseid OR nue.userid <> lue.userid)",
            $base + ['blmman' => self::MANUAL]);
        if ($wrong > 0) {
            $failures[] = 'map_names_an_enrolment_that_is_not_a_manual_enrolment_of_the_same_learner_and_course:' . $wrong;
        }

        // The point of the whole import: a learner whose legacy enrolment gives access now (active, enabled instance,
        // inside its dates) has a manual enrolment that gives access now too.
        $lost = $this->count(
            "SELECT COUNT(1) FROM {$map} m
               JOIN {user_enrolments} lue ON lue.id = m.sourceid
               JOIN {enrol} le ON le.id = lue.enrolid
               JOIN {user_enrolments} nue ON nue.id = m.targetid
               JOIN {enrol} ne ON ne.id = nue.enrolid
              WHERE m.feature = :blmf AND m.sourcetable = :blmst AND m.subkey = :blmsk AND m.targettable = :blmtt
                AND m.outcome IN ('imported', 'folded')
                AND lue.status = 0 AND le.status = 0 AND lue.timestart <= :blmn1 AND (lue.timeend = 0 OR lue.timeend > :blmn2)
                AND NOT (nue.status = 0 AND ne.status = 0 AND nue.timestart <= :blmn3
                         AND (nue.timeend = 0 OR nue.timeend > :blmn4))",
            $base + ['blmn1' => $now, 'blmn2' => $now, 'blmn3' => $now, 'blmn4' => $now]);
        if ($lost > 0) {
            $failures[] = 'learners_whose_access_was_not_kept:' . $lost;
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        // Nothing to reset, copy or purge: every target id is a new one (MAP), so there is no sequence to move, and
        // the cutover runbook purges caches at step 7 (a new enrol row and new user_enrolments rows are cached by
        // nothing that outlives a session).
    }

    /**
     * What the run will do to the instances, and whether a course has an instance the import must not rely on.
     *
     * @param preflight $pf
     * @return void
     */
    private function preflight_instances(preflight $pf): void {
        [$in, $params] = self::methods_in('blmw');
        $courses = "SELECT e.courseid FROM {enrol} e WHERE e.enrol IN ({$in})
                       AND EXISTS (SELECT 1 FROM {user_enrolments} bue WHERE bue.enrolid = e.id) GROUP BY e.courseid";
        $pf->count('courses', $this->count("SELECT COUNT(1) FROM ({$courses}) c", $params));
        $pf->count('courses_missing',
            $this->count("SELECT COUNT(1) FROM ({$courses}) c WHERE NOT EXISTS (SELECT 1 FROM {course} k WHERE k.id = c.courseid)",
                $params));

        $man = ['blmx' => self::MANUAL];
        $without = $this->count(
            "SELECT COUNT(1) FROM ({$courses}) c WHERE EXISTS (SELECT 1 FROM {course} k WHERE k.id = c.courseid)
                AND NOT EXISTS (SELECT 1 FROM {enrol} m WHERE m.courseid = c.courseid AND m.enrol = :blmx AND m.status = 0)",
            $params + $man);
        $pf->count('will_create_manual_instances', $without);
        $pf->count('will_reuse_manual_instances',
            $this->count("SELECT COUNT(1) FROM ({$courses}) c WHERE EXISTS (SELECT 1 FROM {enrol} m
                WHERE m.courseid = c.courseid AND m.enrol = :blmx AND m.status = 0)", $params + $man));
        $onlydisabled = $this->count(
            "SELECT COUNT(1) FROM ({$courses}) c WHERE EXISTS (SELECT 1 FROM {course} k WHERE k.id = c.courseid)
                AND NOT EXISTS (SELECT 1 FROM {enrol} m WHERE m.courseid = c.courseid AND m.enrol = :blmx AND m.status = 0)
                AND EXISTS (SELECT 1 FROM {enrol} d WHERE d.courseid = c.courseid AND d.enrol = :blmy AND d.status <> 0)",
            $params + $man + ['blmy' => self::MANUAL]);
        if ($onlydisabled > 0) {
            // A disabled manual instance means an administrator switched manual enrolment off for the course. It is
            // left as it is and a new enabled instance is added beside it, because the learners must keep access.
            $pf->warn('courses_with_only_a_disabled_manual_instance:' . $onlydisabled);
        }
    }

    /**
     * Record what the restored database holds for the roles of the learners: the import adds none and changes none.
     *
     * @param preflight $pf
     * @return void
     */
    private function preflight_roles(preflight $pf): void {
        global $DB;
        $pairs = $this->pairs_sql('blmy');
        $none = $this->count(
            "SELECT COUNT(1) FROM ({$pairs[0]}) p WHERE NOT EXISTS (SELECT 1 FROM {role_assignments} ra
               JOIN {context} cx ON cx.id = ra.contextid
              WHERE cx.contextlevel = :blmlvl AND cx.instanceid = p.courseid AND ra.userid = p.userid)",
            $pairs[1] + ['blmlvl' => CONTEXT_COURSE]);
        $pf->count('pairs_without_a_role_in_the_course', $none);
        if ($none > 0) {
            // They are enrolled and hold no role in the course today; the conversion keeps them exactly so.
            $pf->warn('legacy_enrolments_without_a_course_role:' . $none);
        }

        $pairs = $this->pairs_sql('blmz');
        $rows = $DB->get_records_sql(
            "SELECT MIN(ra.id) AS k, ra.component AS v, COUNT(1) AS n
               FROM {role_assignments} ra
               JOIN {context} cx ON cx.id = ra.contextid AND cx.contextlevel = :blmlvl2
               JOIN ({$pairs[0]}) p ON p.courseid = cx.instanceid AND p.userid = ra.userid
           GROUP BY ra.component",
            $pairs[1] + ['blmlvl2' => CONTEXT_COURSE]);
        $histogram = [];
        $owned = 0;
        foreach ($rows as $row) {
            $component = (string) $row->v;
            $histogram[$component === '' ? '(none)' : $component] = (int) $row->n;
            if ($component !== '') {
                $owned += (int) $row->n;
            }
        }
        $pf->histogram('role_assignments.component[converted learners, course context]', $histogram);
        if ($owned > 0) {
            // A role assignment that belongs to an enrol component is removed by Moodle when that component's
            // instance loses the enrolment. BizLMS wrote none (roles_protected() is false), so this is new.
            $pf->warn('role_assignments_owned_by_a_component:' . $owned);
        }
    }

    /**
     * The distinct learner-course pairs that have a BizLMS enrolment, as a derived-table body.
     *
     * @param string $prefix Parameter prefix, unique within the caller's query.
     * @param string $having Optional HAVING clause.
     * @return array{0: string, 1: array} [sql selecting courseid and userid, params]
     */
    private function pairs_sql(string $prefix, string $having = ''): array {
        [$in, $params] = self::methods_in($prefix);
        return ["SELECT e.courseid AS courseid, ue.userid AS userid FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid WHERE e.enrol IN ({$in}) GROUP BY e.courseid, ue.userid {$having}",
            $params];
    }

    /**
     * @param string $sql
     * @param array $params
     * @return int
     */
    private function count(string $sql, array $params = []): int {
        global $DB;
        return (int) $DB->count_records_sql($sql, $params);
    }
}
