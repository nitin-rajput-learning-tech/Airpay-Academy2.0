<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * Load step of the course_tags feature: decides, for every tag instance of the old BizLMS course tag area,
 * whether it moves to the core course tag area.
 *
 * The step writes no tag row. A row that will move gets one trail row (the importer's own target table), and the
 * recompute step that follows does the reviewed UPDATE of tag_instance. A row that cannot move is recorded with a
 * reason and left exactly where it is:
 *
 *   course_missing            the tagged course no longer exists
 *   tag_missing               the tag no longer exists
 *   duplicate_core_instance   the core area already holds the same item, context, user and tag (folded into that
 *                             row; the legacy row is left untouched and nothing is deleted)
 *
 * The unit is derived (#tag_instance.id, one group per instance) because the step changes its own source: see the
 * importer's docblock. Each group holds exactly one row, and its key is the instance id, which is not personal data.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_tags_step extends step {

    /** Rows per page when the core area's existing instances are read. */
    private const PAGE = 5000;

    /** @var array<string, int>|null "item|context|user|tag" => id of the core instance, loaded on first use. */
    private ?array $twins = null;

    public function key(): string {
        return course_tags_importer::FEATURE . '.instances';
    }

    public function sourcetable(): string {
        return course_tags_importer::SOURCE_UNIT;
    }

    public function targettable(): string {
        return course_tags_importer::LEDGER;
    }

    public function group_by(): array {
        // One group per instance, so the derived unit still gives every source row its own primary outcome.
        return ['id'];
    }

    public function columns(): array {
        return ['id', 'tagid', 'itemid', 'contextid', 'tiuserid', 'timecreated', 'timemodified'];
    }

    public function source_filter(): array {
        return [
            't.component = :blmtagcomp AND t.itemtype = :blmtagtype',
            ['blmtagcomp' => course_tags_importer::LEGACY_COMPONENT, 'blmtagtype' => course_tags_importer::LEGACY_ITEMTYPE],
        ];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if (!$ctx->lookups->course_exists((int) $row->itemid)) {
                $out[] = outcome::skip($id, 'course_missing');
                continue;
            }
            if (!$ctx->lookups->exists('tag', (int) $row->tagid)) {
                $out[] = outcome::skip($id, 'tag_missing');
                continue;
            }
            $twin = $this->twins($ctx)[self::twin_key($row)] ?? null;
            if ($twin !== null) {
                $out[] = outcome::fold($id, course_tags_importer::SOURCE_TABLE, $twin, 'duplicate_core_instance');
                continue;
            }
            // Source timestamps are kept. The trail holds no user: tiuserid stays in tag_instance.
            $out[] = outcome::insert($id, course_tags_importer::LEDGER, (object) [
                'taginstanceid' => $id,
                'timecreated' => (int) $row->timecreated,
                'timemodified' => (int) $row->timemodified,
            ]);
        }
        return $out;
    }

    /**
     * The instances the core course area already holds, keyed the way its unique index sees a row (component,
     * itemtype, item, context, user, tag) with component and itemtype fixed. A missing context counts as a value:
     * two rows without one are the same tag on the same item, though the index would let both in.
     *
     * Read once through the context's bounded reader. The remaps of this run never add a twin: two legacy rows
     * cannot share a key, because the old area has the same unique index.
     *
     * @param context $ctx
     * @return array<string, int>
     */
    private function twins(context $ctx): array {
        if ($this->twins !== null) {
            return $this->twins;
        }
        $this->twins = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page(course_tags_importer::SOURCE_TABLE, $after, self::PAGE,
                ['itemid', 'contextid', 'tiuserid', 'tagid'],
                ['t.component = :blmcorecomp AND t.itemtype = :blmcoretype', [
                    'blmcorecomp' => course_tags_importer::CORE_COMPONENT,
                    'blmcoretype' => course_tags_importer::CORE_ITEMTYPE,
                ]]);
            foreach ($page as $id => $row) {
                $this->twins[self::twin_key($row)] ??= (int) $id;
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        return $this->twins;
    }

    /**
     * @param \stdClass $row A tag_instance row with itemid, contextid, tiuserid and tagid.
     * @return string
     */
    private static function twin_key(\stdClass $row): string {
        return (int) $row->itemid . '|' . ($row->contextid === null ? 'none' : (int) $row->contextid)
            . '|' . (int) $row->tiuserid . '|' . (int) $row->tagid;
    }
}
