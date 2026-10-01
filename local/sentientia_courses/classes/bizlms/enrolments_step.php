<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * Step 2 of the enrolments importer: every enrolment on a BizLMS instance becomes a manual enrolment in the same
 * course (mapping doc, section 21, gap G6; decision gap.orphan_enrol_instances = convert_to_manual).
 *
 * The unit is the legacy {user_enrolments} row (derived unit #user_enrolments.id, group_by id): one map row, and one
 * report line, per legacy enrolment. The source is a filtered core table, which is why the unit is derived (see the
 * importer's class comment). A learner can hold ONE enrolment per instance, and the same course is often in several
 * BizLMS plans, so a learner-course pair can have several legacy rows (4,031 of 12,565 pairs on the April 2026 dump).
 * The LOWEST id of the pair owns the conversion and the others follow it, which also guarantees the owner is mapped
 * before the rows that need it, because the runner processes groups in ascending id order.
 *
 * Owner row, in this order:
 *  - the BizLMS instance row, the learner's account or the course is gone: skipped (instance_missing, user_missing,
 *    user_deleted, course_missing). A deleted account has no access to keep;
 *  - the learner already has an enrolment on the course's enabled manual instance: folded into it (already_manual),
 *    nothing is written. Except when the legacy enrolment gives access today and that manual one does not (suspended,
 *    or outside its dates): skipped as manual_enrolment_inactive, which needs the owner. Changing an administrator's
 *    manual enrolment is not the import's decision, and converting would not keep the access. The same when the manual
 *    enrolment gives access today but ENDS BEFORE the legacy one does (the legacy one has no end, or a later one):
 *    skipped as manual_enrolment_ends_sooner. Folding there would silently shorten the access once the legacy
 *    instance is gone, and lengthening a manual enrolment is an administrator's decision too;
 *  - otherwise a new {user_enrolments} row on that instance (a reviewed core INSERT) with the legacy status, start,
 *    end, modifier and timestamps, plus one ledger row naming the original method and instance. The values come from
 *    the best row of the pair: one that gives access now, then an active one, then the latest end (none is the
 *    latest), then the earliest start, then the lowest id. For a pair whose rows agree (all of them, on the April 2026
 *    dump) that is simply the row itself.
 *
 * Status is the legacy status, except that a row on a DISABLED BizLMS instance is converted as suspended: BizLMS grants
 * nothing there, and the import never gives a learner access BizLMS did not give (reported as
 * status_from_disabled_instance).
 *
 * Other rows of the pair: folded into the owner's enrolment (duplicate_pair), or skipped with the owner's reason when
 * the owner has none.
 *
 * Nothing here enrols through the enrol API, assigns a role, fires an event or writes a legacy row.
 *
 * Reads. A per-row lookup through legacy_reader::page() orders by the primary key with a LIMIT, and MariaDB answers a
 * point lookup that way by walking the table (measured on the local MariaDB, loaded, with the April dump: about 20 ms a
 * query, four queries a row). So, as the ADR asks of every step ("no per-row SELECT"), the step reads what it needs
 * ONCE, by keyset scan, on its first row: the BizLMS instances, every BizLMS enrolment, and every enrolment on the
 * enabled manual instances of the courses concerned. Memory is a few small arrays per row (about 16 000 and 20 000
 * rows on the April dump); the
 * report's rows-per-second figure at the Stage B rehearsal sets the window. The snapshot of the manual enrolments is
 * taken before the step writes any, which is safe: only the owner of a pair writes for it, and a pair never looks at
 * another pair's manual enrolment.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolments_step extends step {

    /** @var string[] The user_enrolments columns every read of an enrolment asks for. */
    private const COLUMNS = ['id', 'enrolid', 'userid', 'status', 'timestart', 'timeend', 'modifierid', 'timecreated',
        'timemodified'];

    /** Rows per page of the one-off scans. */
    private const SCAN_PAGE = 5000;

    /** @var array<int, array{courseid: int, method: string, status: int}>|null BizLMS instance id => facts, read once. */
    private ?array $instances = null;

    /** @var array<int, array<int, array<int, array<string, int>>>>|null userid => courseid => id => row, read once. */
    private ?array $legacy = null;

    /** @var array<int, array<int, array<string, int>>>|null enrolid => userid => row, read once. */
    private ?array $manual = null;

    /** @var int|null The moment access is judged at, fixed on first use so one run judges every row alike. */
    private ?int $now = null;

    public function key(): string {
        return 'enrolments.enrolments';
    }

    public function sourcetable(): string {
        return enrolments_importer::UNIT_ENROLMENTS;
    }

    public function targettable(): string {
        return enrolments_importer::LEDGER;
    }

    public function group_by(): array {
        return ['id'];
    }

    public function columns(): array {
        return self::COLUMNS;
    }

    public function source_filter(): array {
        return enrolments_importer::enrolment_filter();
    }

    /**
     * @param \stdClass[] $rows The legacy enrolment of ONE group (one row).
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            foreach ($this->convert($row, $ctx) as $outcome) {
                $out[] = $outcome;
            }
        }
        return $out;
    }

    /**
     * @param \stdClass $row
     * @param context $ctx
     * @return outcome[]
     */
    private function convert(\stdClass $row, context $ctx): array {
        $id = (int) $row->id;
        $userid = (int) $row->userid;
        $instance = $this->instances($ctx)[(int) $row->enrolid] ?? null;
        if ($instance === null) {
            return [outcome::skip($id, 'instance_missing')];
        }
        $courseid = $instance['courseid'];

        if (!$ctx->lookups->user_exists($userid)) {
            return [outcome::skip($id, 'user_missing')];
        }
        if (!$ctx->lookups->user_active($userid)) {
            return [outcome::skip($id, 'user_deleted')];
        }
        if ($courseid <= 0 || !$ctx->lookups->course_exists($courseid)) {
            return [outcome::skip($id, 'course_missing')];
        }

        $pair = $this->pair($ctx, $row, $userid, $courseid);
        $ownerid = (int) array_key_first($pair);
        if ($id !== $ownerid) {
            return [$this->follow($ctx, $id, $ownerid)];
        }
        return $this->own($ctx, $row, $instance, $pair);
    }

    /**
     * The owner row of a pair: decide, and (when nothing already does the job) convert.
     *
     * @param context $ctx
     * @param \stdClass $row The owner's legacy row.
     * @param array{courseid: int, method: string, status: int} $instance Its BizLMS instance.
     * @param array<int, \stdClass> $pair Every legacy row of the pair, with effective status, ascending by id.
     * @return outcome[]
     */
    private function own(context $ctx, \stdClass $row, array $instance, array $pair): array {
        $id = (int) $row->id;
        $courseid = $instance['courseid'];
        $userid = (int) $row->userid;

        // The manual instance enrolments.instances decided on (an existing one, or the one it created).
        $unit = $ctx->map->entry(enrolments_importer::UNIT_INSTANCES, $courseid);
        if ($unit === null) {
            throw new bizlms_exception('instance_group_not_mapped:' . $id);
        }
        if ($unit['targetid'] === null) {
            return [outcome::skip($id, 'course_missing')];
        }
        // Negative only in a dry run, where the instance is decided and not written.
        $target = (int) $unit['targetid'];

        $best = null;
        foreach ($pair as $candidate) {
            if ($best === null || $this->better($candidate, $best)) {
                $best = $candidate;
            }
        }

        // An instance created in this run has no enrolments, and one that is only decided (dry run) has no id to look up.
        $existing = $target > 0 ? $this->existing($ctx, $target, $userid) : null;
        if ($existing !== null) {
            if ($this->grants_now($best) && !$this->grants_now($existing)) {
                return [outcome::skip($id, 'manual_enrolment_inactive')];
            }
            if ($this->grants_now($best) && $this->ends_sooner($existing, $best)) {
                return [outcome::skip($id, 'manual_enrolment_ends_sooner')];
            }
            return [outcome::fold($id, 'user_enrolments', (int) $existing->id, 'already_manual')];
        }

        $created = outcome::insert($id, 'user_enrolments', (object) [
            'status' => (int) $best->status,
            'enrolid' => $target,
            'userid' => $userid,
            'timestart' => (int) $best->timestart,
            'timeend' => (int) $best->timeend,
            'modifierid' => (int) $best->modifierid,
            'timecreated' => (int) $best->timecreated,
            'timemodified' => (int) $best->timemodified,
        ]);
        if (!empty($best->adjusted)) {
            $created->warn('status_from_disabled_instance');
        }
        // Where the enrolment came from, in ids: the map's detail may not carry one.
        $ledger = outcome::insert($id, enrolments_importer::LEDGER, (object) [
            'legacyueid' => $id,
            'legacyenrolid' => (int) $row->enrolid,
            'method' => $instance['method'],
            'courseid' => $courseid,
            'targetenrolid' => $target,
            'timecreated' => (int) $row->timecreated,
            'timemodified' => (int) $row->timemodified,
        ], 'ledger');
        return [$created, $ledger];
    }

    /**
     * A row of a pair that is not the owner: it goes where the owner went.
     *
     * @param context $ctx
     * @param int $id
     * @param int $ownerid
     * @return outcome
     */
    private function follow(context $ctx, int $id, int $ownerid): outcome {
        $entry = $ctx->map->entry(enrolments_importer::UNIT_ENROLMENTS, $ownerid);
        if ($entry === null) {
            // The owner has the lowest id and groups run in ascending order, so it is always mapped first.
            throw new bizlms_exception('pair_owner_not_mapped:' . $id);
        }
        if ($entry['targetid'] !== null) {
            return outcome::fold($id, 'user_enrolments', (int) $entry['targetid'], 'duplicate_pair');
        }
        $code = (string) ($entry['reason'] ?? '');
        if ($code === '') {
            throw new bizlms_exception('pair_owner_has_no_outcome:' . $id);
        }
        return outcome::skip($id, $code);
    }

    /**
     * Every legacy row of one learner in one course, ascending by id, with the status the learner effectively has: a
     * row on a disabled BizLMS instance is suspended.
     *
     * @param context $ctx
     * @param \stdClass $row The row being converted (always part of its pair).
     * @param int $userid
     * @param int $courseid
     * @return array<int, \stdClass> id => row
     */
    private function pair(context $ctx, \stdClass $row, int $userid, int $courseid): array {
        $instances = $this->instances($ctx);
        $found = $this->legacy_rows($ctx)[$userid][$courseid] ?? [];
        $found[(int) $row->id] ??= self::as_ints($row);
        ksort($found);

        $pair = [];
        foreach ($found as $id => $legacy) {
            $effective = (object) $legacy;
            $effective->adjusted = false;
            $status = $instances[(int) $legacy['enrolid']]['status'] ?? 0;
            if ($status !== 0 && (int) $effective->status === 0) {
                $effective->status = 1;
                $effective->adjusted = true;
            }
            $pair[(int) $id] = $effective;
        }
        return $pair;
    }

    /**
     * The learner's enrolment on an instance, if any (one per instance: the key is enrolid, userid).
     *
     * @param context $ctx
     * @param int $enrolid
     * @param int $userid
     * @return \stdClass|null
     */
    private function existing(context $ctx, int $enrolid, int $userid): ?\stdClass {
        $row = $this->manual_rows($ctx)[$enrolid][$userid] ?? null;
        return $row === null ? null : (object) $row;
    }

    /**
     * Facts of every BizLMS instance, read once: the step cannot read one instance per row.
     *
     * @param context $ctx
     * @return array<int, array{courseid: int, method: string, status: int}>
     */
    private function instances(context $ctx): array {
        if ($this->instances === null) {
            $this->instances = [];
            [$in, $params] = enrolments_importer::methods_in('blmi');
            $after = 0;
            do {
                $rows = $ctx->legacy->page('enrol', $after, self::SCAN_PAGE, ['id', 'courseid', 'enrol', 'status'],
                    ["t.enrol IN ({$in})", $params]);
                foreach ($rows as $id => $instance) {
                    $this->instances[(int) $id] = [
                        'courseid' => (int) $instance->courseid,
                        'method' => (string) $instance->enrol,
                        'status' => (int) $instance->status,
                    ];
                    $after = (int) $id;
                }
            } while (count($rows) === self::SCAN_PAGE);
        }
        return $this->instances;
    }

    /**
     * Every BizLMS enrolment, by learner and course, read once with the framework's keyset paging.
     *
     * @param context $ctx
     * @return array<int, array<int, array<int, array<string, int>>>> userid => courseid => id => row
     */
    private function legacy_rows(context $ctx): array {
        if ($this->legacy === null) {
            $this->legacy = [];
            $instances = $this->instances($ctx);
            $after = 0;
            do {
                $rows = $ctx->legacy->page('user_enrolments', $after, self::SCAN_PAGE, self::COLUMNS,
                    enrolments_importer::enrolment_filter());
                foreach ($rows as $id => $legacy) {
                    $courseid = $instances[(int) $legacy->enrolid]['courseid'] ?? 0;
                    $this->legacy[(int) $legacy->userid][$courseid][(int) $id] = self::as_ints($legacy);
                    $after = (int) $id;
                }
            } while (count($rows) === self::SCAN_PAGE);
        }
        return $this->legacy;
    }

    /**
     * Every enrolment on an enabled manual instance of a course that has a BizLMS enrolment, by instance and learner,
     * read once.
     *
     * @param context $ctx
     * @return array<int, array<int, array<string, int>>> enrolid => userid => row
     */
    private function manual_rows(context $ctx): array {
        if ($this->manual === null) {
            $this->manual = [];
            [$in, $params] = enrolments_importer::methods_in('blmj');
            $params['blmman'] = enrolments_importer::MANUAL;
            $filter = ["t.enrolid IN (SELECT m.id FROM {enrol} m WHERE m.enrol = :blmman AND m.status = 0
                                         AND m.courseid IN (SELECT bi.courseid FROM {enrol} bi WHERE bi.enrol IN ({$in})))",
                $params];
            $after = 0;
            do {
                $rows = $ctx->legacy->page('user_enrolments', $after, self::SCAN_PAGE, self::COLUMNS, $filter);
                foreach ($rows as $id => $row) {
                    $this->manual[(int) $row->enrolid][(int) $row->userid] = self::as_ints($row);
                    $after = (int) $id;
                }
            } while (count($rows) === self::SCAN_PAGE);
        }
        return $this->manual;
    }

    /**
     * A row as a small array of integers: what the step keeps in memory per enrolment.
     *
     * @param \stdClass $row
     * @return array<string, int>
     */
    private static function as_ints(\stdClass $row): array {
        $out = [];
        foreach (self::COLUMNS as $column) {
            $out[$column] = (int) $row->{$column};
        }
        return $out;
    }

    /**
     * Does this enrolment give access now: active, started, and not ended?
     *
     * @param \stdClass $ue An enrolment row (status already effective).
     * @return bool
     */
    private function grants_now(\stdClass $ue): bool {
        $now = $this->now ??= time();
        $end = (int) $ue->timeend;
        return (int) $ue->status === 0 && (int) $ue->timestart <= $now && ($end === 0 || $end > $now);
    }

    /**
     * Does the manual enrolment end before the one that would be carried over? A manual enrolment with no end never
     * does. Only meaningful when both give access now: the caller has checked that the carried-over one does and that
     * the manual one is not inactive.
     *
     * @param \stdClass $manual The learner's existing manual enrolment.
     * @param \stdClass $best The enrolment that would be carried over (status already effective).
     * @return bool
     */
    private function ends_sooner(\stdClass $manual, \stdClass $best): bool {
        $manualend = (int) $manual->timeend;
        if ($manualend === 0) {
            return false;
        }
        $bestend = (int) $best->timeend;
        return $bestend === 0 || $bestend > $manualend;
    }

    /**
     * Is $a a better enrolment to carry over than $b? Gives access now, then active, then the latest end (none is the
     * latest), then the earliest start, then the lowest id: a total order, so every run picks the same row.
     *
     * @param \stdClass $a
     * @param \stdClass $b
     * @return bool
     */
    private function better(\stdClass $a, \stdClass $b): bool {
        $ga = $this->grants_now($a);
        $gb = $this->grants_now($b);
        if ($ga !== $gb) {
            return $ga;
        }
        $aa = (int) $a->status === 0;
        $ab = (int) $b->status === 0;
        if ($aa !== $ab) {
            return $aa;
        }
        $ea = (int) $a->timeend === 0 ? PHP_INT_MAX : (int) $a->timeend;
        $eb = (int) $b->timeend === 0 ? PHP_INT_MAX : (int) $b->timeend;
        if ($ea !== $eb) {
            return $ea > $eb;
        }
        if ((int) $a->timestart !== (int) $b->timestart) {
            return (int) $a->timestart < (int) $b->timestart;
        }
        return (int) $a->id < (int) $b->id;
    }
}
