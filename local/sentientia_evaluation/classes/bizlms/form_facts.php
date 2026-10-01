<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\legacy_reader;

/**
 * Facts about a BizLMS evaluation form that more than one step needs (ADR-032, mapping doc section 18).
 *
 * The steps of this importer are pure and see the database only through their context. What they share is
 * read here, through $ctx->legacy and $ctx->map, and kept for the life of one run: the legacy tables do not
 * change while an import runs (the framework fingerprints them and refuses to resume over a change), so a
 * value read once stays true. One instance is made per importer::steps() call and handed to every step of it.
 *
 * Nothing here writes, and nothing here opens a recordset: every read is a bounded page of the framework's
 * legacy_reader.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class form_facts {

    /** Rows per page of the one-off scans. */
    private const PAGE = 5000;

    /** Entries kept per cache that is keyed by something that grows with the data. */
    private const KEEP = 64;

    /** @var array<int, array{anon: bool, guest: bool, mincompleted: int, minusers: int}>|null Per form. */
    private ?array $aggregates = null;

    /** @var array<int, \stdClass|null> Legacy form rows by id. */
    private array $forms = [];

    /** @var array<string, \stdClass> "form|user" => completion and assignment facts. */
    private array $pairs = [];

    /** @var array<int, array<int, \stdClass>> Form id => item id => {qid, shape, position}. */
    private array $questions = [];

    /** @var array<int, array<int, true>> Form id => every item id of that form (layout items and unimported ones too). */
    private array $items = [];

    /** @var array<int, \stdClass> Completed id => {data, accepted, rejected}. */
    private array $answers = [];

    /** @var array<int, \stdClass|null> Legacy completion rows by id. */
    private array $completed = [];

    /**
     * The legacy form row.
     *
     * @param context $ctx
     * @param int $id
     * @return \stdClass|null Null when there is no such row.
     */
    public function form(context $ctx, int $id): ?\stdClass {
        if (!array_key_exists($id, $this->forms)) {
            $rows = $ctx->legacy->fetch(importer::SRC_FORMS, [$id]);
            $this->forms[$id] = $rows[$id] ?? null;
        }
        return $this->forms[$id];
    }

    /**
     * Where a row that belongs to a form goes: the form's Sentientia id, or why it cannot go anywhere.
     *
     * @param context $ctx
     * @param int $formid The legacy form id.
     * @return array{0: int|null, 1: string|null} [target id, null] or [null, reason]: orphan_form when no form
     *         row was mapped (the form does not exist), parent_deleted when it was mapped but not imported.
     */
    public function form_target(context $ctx, int $formid): array {
        $entry = $ctx->map->entry(importer::SRC_FORMS, $formid);
        if ($entry === null) {
            return [null, 'orphan_form'];
        }
        if (!in_array($entry['outcome'], ['imported', 'adopted'], true) || $entry['targetid'] === null) {
            return [null, 'parent_deleted'];
        }
        return [(int) $entry['targetid'], null];
    }

    /**
     * Must this form keep its respondents hidden?
     *
     * The form's own flag (BizLMS: 1 is anonymous, 2 is not), OR any completion was stored as anonymous. The
     * second half is sticky on purpose, as evaluation_manager::identity_protected() is: a form that once
     * promised anonymity keeps it, whatever its flag says today.
     *
     * @param context $ctx
     * @param \stdClass $form Legacy form row.
     * @return bool
     */
    public function anonymous_final(context $ctx, \stdClass $form): bool {
        if ((int) ($form->anonymous ?? 0) === 1) {
            return true;
        }
        return $this->aggregate($ctx, (int) $form->id)['anon'];
    }

    /**
     * Will Sentientia treat this form's respondents as protected (evaluation_manager::identity_protected())?
     * True when it is anonymous in the sense above, or when any completion will be stored with user id 0
     * (a guest), because Sentientia reads a stored user id of 0 as "this form was answered anonymously".
     *
     * @param context $ctx
     * @param \stdClass $form Legacy form row.
     * @return bool
     */
    public function identity_protected(context $ctx, \stdClass $form): bool {
        return $this->anonymous_final($ctx, $form) || $this->aggregate($ctx, (int) $form->id)['guest'];
    }

    /**
     * When the form came into being. BizLMS has no creation time for a form, so it is the earliest time any
     * record of it carries: the form's own modification time, its first assignment, its first completion.
     *
     * @param context $ctx
     * @param \stdClass $form Legacy form row.
     * @return int Unix time; 0 when no record carries one.
     */
    public function created(context $ctx, \stdClass $form): int {
        $aggregate = $this->aggregate($ctx, (int) $form->id);
        $times = array_filter([(int) ($form->timemodified ?? 0), $aggregate['mincompleted'], $aggregate['minusers']],
            static fn(int $t): bool => $t > 0);
        return $times ? min($times) : 0;
    }

    /**
     * Completions of one form by one person, and whether the form's assignment table names that person.
     *
     * @param context $ctx
     * @param int $formid
     * @param int $userid
     * @return \stdClass count, firstid (the lowest completion id, 0 for none), firsttime, lasttime and
     *         hasusers (a local_evaluation_users row exists for the pair).
     */
    public function pair(context $ctx, int $formid, int $userid): \stdClass {
        $key = $formid . '|' . $userid;
        if (isset($this->pairs[$key])) {
            return $this->pairs[$key];
        }
        if (count($this->pairs) >= self::KEEP) {
            $this->pairs = [];
        }
        $rows = $ctx->legacy->page(importer::SRC_COMPLETED, 0, legacy_reader::MAX_PAGE, ['id', 'timemodified'],
            ['t.evaluation = :evpf AND t.userid = :evpu', ['evpf' => $formid, 'evpu' => $userid]]);
        $facts = (object) ['count' => count($rows), 'firstid' => 0, 'firsttime' => 0, 'lasttime' => 0, 'hasusers' => false];
        foreach ($rows as $id => $row) {
            if ($facts->firstid === 0) {
                $facts->firstid = (int) $id;
                $facts->firsttime = (int) $row->timemodified;
            }
            $facts->lasttime = max($facts->lasttime, (int) $row->timemodified);
        }
        $facts->hasusers = $ctx->legacy->count(importer::SRC_USERS,
            ['t.evaluationid = :evua AND t.userid = :evub', ['evua' => $formid, 'evub' => $userid]]) > 0;
        $this->pairs[$key] = $facts;
        return $facts;
    }

    /**
     * The questions of a form that the import created, by BizLMS item id, in form order.
     *
     * An item that is not a question, or that was not imported, is absent. The caller can rely on this being the
     * set whose answers go into response_data.
     *
     * @param context $ctx
     * @param int $formid Legacy form id.
     * @return array<int, \stdClass> item id => {qid (the Sentientia question id), shape (item_shape), position}
     */
    public function questions(context $ctx, int $formid): array {
        if (isset($this->questions[$formid])) {
            return $this->questions[$formid];
        }
        if (count($this->questions) >= self::KEEP) {
            // Both caches go together: answers() reads $items right after calling this.
            $this->questions = [];
            $this->items = [];
        }
        $rows = $ctx->legacy->page(importer::SRC_ITEMS, 0, legacy_reader::MAX_PAGE,
            ['id', 'typ', 'presentation', 'position'], ['t.evaluation = :evqf', ['evqf' => $formid]]);
        // Every item of the form, whether or not it becomes a question: answers() tells "an item of this form that
        // is not a question" (nothing to carry) from "not an item of this form at all" (an answer that is lost).
        $this->items[$formid] = array_fill_keys(array_map('intval', array_keys($rows)), true);
        $targets = $ctx->map->resolve_many(importer::SRC_ITEMS, array_keys($rows));
        $index = [];
        foreach ($rows as $id => $row) {
            $shape = answer_mapper::describe($row);
            $target = $targets[$id] ?? null;
            if ($shape === null || $target === null) {
                continue;
            }
            $index[(int) $id] = (object) ['qid' => (int) $target, 'shape' => $shape, 'position' => (int) $row->position];
        }
        uasort($index, static fn(\stdClass $a, \stdClass $b): int => [$a->position, $a->qid] <=> [$b->position, $b->qid]);
        $this->questions[$formid] = $index;
        return $index;
    }

    /**
     * What one completion says, question by question, and which of its stored values made it into that.
     *
     * This is the single place that decides, so the response step (which writes the answers) and the value step
     * (which accounts for each stored value) cannot disagree.
     *
     * @param context $ctx
     * @param \stdClass $completed Legacy completion row.
     * @param \stdClass $form Legacy form row of that completion.
     * @return \stdClass data (Sentientia question id => answer, every imported question present, null for no
     *         answer), accepted (value id => true) and rejected (value id => reason code): item_not_imported (an
     *         item of this form that is not a question: a layout item), foreign_item (the value names an item of
     *         ANOTHER form, or of a template), missing_item (the item does not exist), duplicate_value or
     *         value_not_valid. The last four are answers that are lost, so they are for the owner to look at.
     */
    public function answers(context $ctx, \stdClass $completed, \stdClass $form): \stdClass {
        $cid = (int) $completed->id;
        if (isset($this->answers[$cid])) {
            return $this->answers[$cid];
        }
        if (count($this->answers) >= self::KEEP) {
            // Not array_shift(): it would renumber the integer keys, which are completion ids.
            unset($this->answers[array_key_first($this->answers)]);
        }
        $index = $this->questions($ctx, (int) $form->id);
        $formitems = $this->items[(int) $form->id] ?? [];
        $values = $ctx->legacy->page(importer::SRC_VALUES, 0, legacy_reader::MAX_PAGE, ['id', 'item', 'completed', 'value'],
            ['t.completed = :evvc', ['evvc' => $cid]]);

        $set = (object) ['data' => [], 'accepted' => [], 'rejected' => []];
        foreach ($index as $question) {
            $set->data[$question->qid] = null;
        }
        $seen = [];
        $strangers = [];
        foreach ($values as $valueid => $value) {
            $itemid = (int) $value->item;
            if (!isset($index[$itemid])) {
                if (isset($formitems[$itemid])) {
                    // A layout item (or one the import did not keep) of this very form: not an answer.
                    $set->rejected[(int) $valueid] = 'item_not_imported';
                } else {
                    // Not an item of this form at all: another form's, or none. Told apart below.
                    $strangers[(int) $valueid] = $itemid;
                }
                continue;
            }
            if (isset($seen[$itemid])) {
                // The unique key is (completed, item, course_id), so a second value for the same item can exist.
                $set->rejected[(int) $valueid] = 'duplicate_value';
                continue;
            }
            [$ok, $mapped] = answer_mapper::map_value($index[$itemid]->shape, (string) $value->value);
            if (!$ok) {
                $set->rejected[(int) $valueid] = 'value_not_valid';
                continue;
            }
            $seen[$itemid] = true;
            $set->data[$index[$itemid]->qid] = $mapped;
            $set->accepted[(int) $valueid] = true;
        }
        if ($strangers) {
            // One read for the completion: an item row that exists but belongs elsewhere is a value of another form's
            // item (BizLMS never joined the two); no row at all is a value whose item was deleted.
            $found = $ctx->legacy->fetch(importer::SRC_ITEMS, array_values($strangers), ['id', 'evaluation']);
            foreach ($strangers as $valueid => $itemid) {
                $set->rejected[$valueid] = isset($found[$itemid]) ? 'foreign_item' : 'missing_item';
            }
        }
        $this->answers[$cid] = $set;
        return $set;
    }

    /**
     * A legacy completion row.
     *
     * @param context $ctx
     * @param int $id
     * @return \stdClass|null
     */
    public function completed(context $ctx, int $id): ?\stdClass {
        if (!array_key_exists($id, $this->completed)) {
            if (count($this->completed) >= self::KEEP) {
                $this->completed = [];
            }
            $rows = $ctx->legacy->fetch(importer::SRC_COMPLETED, [$id]);
            $this->completed[$id] = $rows[$id] ?? null;
        }
        return $this->completed[$id];
    }

    /**
     * Midnight, in the server's time zone, of the day a time falls in.
     *
     * Used where a time could be matched to an anonymous answer's submission time (an assignment row beside an
     * identity-protected response): the day is kept, the minute is not.
     *
     * @param context $ctx
     * @param int $time
     * @return int
     */
    public function day_start(context $ctx, int $time): int {
        return (new \DateTimeImmutable('@' . $time))->setTimezone($ctx->servertz())->setTime(0, 0, 0)->getTimestamp();
    }

    /**
     * The per-form aggregate of every completion and assignment row, read once for the whole table.
     *
     * One pass over each table, however many forms there are: a per-form query would scan the table once per form.
     *
     * @param context $ctx
     * @param int $formid
     * @return array{anon: bool, guest: bool, mincompleted: int, minusers: int}
     */
    private function aggregate(context $ctx, int $formid): array {
        if ($this->aggregates === null) {
            $this->aggregates = [];
            $after = 0;
            do {
                $rows = $ctx->legacy->page(importer::SRC_COMPLETED, $after, self::PAGE,
                    ['id', 'evaluation', 'userid', 'timemodified', 'anonymous_response']);
                foreach ($rows as $id => $row) {
                    $after = (int) $id;
                    $aggregate = $this->aggregates[(int) $row->evaluation] ?? self::blank();
                    if ((int) ($row->anonymous_response ?? 0) === 1) {
                        $aggregate['anon'] = true;
                    }
                    if ((int) $row->userid <= 0) {
                        $aggregate['guest'] = true;
                    }
                    $aggregate['mincompleted'] = self::earliest($aggregate['mincompleted'], (int) $row->timemodified);
                    $this->aggregates[(int) $row->evaluation] = $aggregate;
                }
            } while (count($rows) === self::PAGE);

            $after = 0;
            do {
                $rows = $ctx->legacy->page(importer::SRC_USERS, $after, self::PAGE, ['id', 'evaluationid', 'timecreated']);
                foreach ($rows as $id => $row) {
                    $after = (int) $id;
                    $aggregate = $this->aggregates[(int) $row->evaluationid] ?? self::blank();
                    $aggregate['minusers'] = self::earliest($aggregate['minusers'], (int) $row->timecreated);
                    $this->aggregates[(int) $row->evaluationid] = $aggregate;
                }
            } while (count($rows) === self::PAGE);
        }
        return $this->aggregates[$formid] ?? self::blank();
    }

    /**
     * @return array{anon: bool, guest: bool, mincompleted: int, minusers: int}
     */
    private static function blank(): array {
        return ['anon' => false, 'guest' => false, 'mincompleted' => 0, 'minusers' => 0];
    }

    /**
     * The earlier of two times, where 0 means "none yet".
     *
     * @param int $current
     * @param int $candidate
     * @return int
     */
    private static function earliest(int $current, int $candidate): int {
        if ($candidate <= 0) {
            return $current;
        }
        return $current <= 0 ? $candidate : min($current, $candidate);
    }
}
