<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\preflight;
use local_sentientia_platform\bizlms\reason;
use local_sentientia_platform\bizlms\source_spec;

/**
 * ADR-032 importer for the course_tags feature (mapping doc, section 7).
 *
 * BizLMS tagged courses in its own tag area, component local_courses and itemtype courses. Sentientia
 * reads the core area, component core and itemtype course. Once the BizLMS plugin is gone its area is
 * orphaned, and uninstalling it deletes every instance (tag/classes/area.php, called from
 * lib/adminlib.php). So every instance of the old area is moved to the core area IN PLACE: the row keeps
 * its id, tag, item, context, user, ordering and timestamps, and only component and itemtype change.
 * That is a reviewed core write (registry::CORE_WRITES_ALLOWED, tag_instance, UPDATE only), made with a
 * direct update: the core_tag_tag API would fire tag_added and tag_removed events.
 *
 * How this fits the frozen framework, because an in-place remap of a core table is not a shape it was
 * written for (see the "framework needs" recorded in the 2026-09-30 state card note):
 *
 *  - The only path to a core UPDATE is a recompute step, and a recompute step works on the rows the
 *    load steps imported into the importer's OWN target tables. So each instance that will move gets one
 *    row in a small trail table, local_sentientia_courses_tagmove (ids and timestamps only, no person).
 *    The trail is also what makes a rehearsal reversible, which --purge-feature refuses for a feature that
 *    writes a core table: see the state card for the statement that puts the rows back.
 *  - The load step rewrites its own source: after the remap the filter component = local_courses no
 *    longer matches the moved rows. The generic accounting identity (source rows with the step filter equal
 *    primary map rows) and the whole-table check (unmapped_rows) cannot hold for such a step, and core
 *    tag_instance keeps changing after go-live. The step is therefore a derived unit,
 *    #tag_instance.id, one group per instance, which the generic checks leave to the importer. verify()
 *    carries the real identity: every instance still in the old area is recorded folded or skipped, and
 *    every moved row is in the core area.
 *  - Because the step changes its own source, a batch-mode run cannot be resumed after the remap has
 *    started (resume compares the source fingerprint). The feature is atomic() and far below the
 *    atomic threshold, so it runs in one transaction: a crash leaves nothing. preflight() warns when the
 *    source is larger than the default threshold.
 *
 * local_tags (the tenant overlay of tag instances) and local_tag_mapping are declined. local_tags.taginstanceid
 * still points at the same ids after the remap. The tag areas of classroom, learning plan and evaluation are
 * counted in preflight and left alone (decision gaps.other_tag_areas).
 *
 * The importer never writes to a legacy table, never deletes a row, fires no event and calls no core_tag API.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_tags_importer implements importer {

    /** Feature key. */
    public const FEATURE = 'course_tags';

    /** Plugin version that carries the trail table (version.php, db/upgrade.php). */
    public const REQUIRES_VERSION = 2026100103;

    /** Tag area BizLMS used: component. */
    public const LEGACY_COMPONENT = 'local_courses';

    /** Tag area BizLMS used: item type. */
    public const LEGACY_ITEMTYPE = 'courses';

    /** Tag area Sentientia reads: component. */
    public const CORE_COMPONENT = 'core';

    /** Tag area Sentientia reads: item type. */
    public const CORE_ITEMTYPE = 'course';

    /** The physical table the load step reads (and the recompute step updates). */
    public const SOURCE_TABLE = 'tag_instance';

    /** The accounting unit of the load step: a derived unit, one group per tag instance. */
    public const SOURCE_UNIT = '#tag_instance.id';

    /** The trail table: one row per tag instance the import moves. */
    public const LEDGER = 'local_sentientia_courses_tagmove';

    /** Default --atomic-threshold of the CLI. Above it the feature runs in batch mode and cannot be resumed. */
    private const DEFAULT_ATOMIC_THRESHOLD = 50000;

    /**
     * Tag areas of the other BizLMS plugins: counted in preflight and left alone (gap G5).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const OTHER_AREAS = [
        'local_classroom/classroom' => ['local_classroom', 'classroom'],
        'local_learningplan/learningplan' => ['local_learningplan', 'learningplan'],
        'local_evaluation/evaluation' => ['local_evaluation', 'evaluation'],
    ];

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
        // No tenant column and no other feature's table: nothing to wait for.
        return [];
    }

    public function sources(): array {
        // A core table, read as a source like logstore_standard_log is. It always exists, so the feature is
        // applicable everywhere and simply finds nothing to move on a database that never had BizLMS.
        return [self::SOURCE_TABLE => new source_spec(self::SOURCE_TABLE)];
    }

    public function declined_tables(): array {
        return [
            'local_tags' => 'tenant overlay of tag instances: its tenant columns are corrupt by construction '
                . '(the course form passes a path string into an integer column); taginstanceid still points at the '
                . 'same ids after the in-place remap; nothing in Sentientia reads it',
            'local_tag_mapping' => 'no writer and no reader: the plugin only creates the table',
        ];
    }

    public function target_tables(): array {
        return [self::LEDGER];
    }

    public function core_writes(): array {
        return [
            'tag_instance' => 'course_tags: the in-place remap of the local_courses/courses tag area to core/course; '
                . 'component and itemtype only (mapping doc, section 7)',
        ];
    }

    public function tenant_columns(): array {
        return [];
    }

    public function reasons(): array {
        return [
            // An instance whose twin already exists in the core area: the legacy row stays where it is.
            new reason('duplicate_core_instance', false, false),
            // The item the instance tags is gone (BizLMS course deletion did not clear its own tag area).
            new reason('course_missing', false, false),
            // The tag the instance points at is gone.
            new reason('tag_missing', false, false),
        ];
    }

    public function decisions(): array {
        return [
            new decision('gaps.other_tag_areas',
                'Tag instances of the classroom, learning plan and evaluation tag areas (G5): counted and left in place',
                true, null, ['left_in_place']),
        ];
    }

    public function atomic(): bool {
        return true;
    }

    public function steps(): array {
        return [new course_tags_step(), new course_tags_remap_step()];
    }

    public function preflight(context $ctx): preflight {
        global $DB;
        $pf = new preflight();
        // gaps.other_tag_areas is enforced by the runner before this runs (present, accepted, left_in_place). What it
        // decides is what the loop at the end of this method does: count the other areas and change nothing.

        $legacy = [self::LEGACY_COMPONENT, self::LEGACY_ITEMTYPE];
        $total = $DB->count_records(self::SOURCE_TABLE, ['component' => $legacy[0], 'itemtype' => $legacy[1]]);
        $pf->count('legacy_tag_instances', $total);
        if ($total > self::DEFAULT_ATOMIC_THRESHOLD) {
            // The step rewrites its own source, so a batch-mode run cannot be resumed.
            $pf->warn('source_above_the_default_atomic_threshold:' . $total);
        }

        $this->preflight_areas($pf, $total);
        if ($total > 0) {
            $this->preflight_rows($pf, $total);
        }

        foreach (self::OTHER_AREAS as $label => [$component, $itemtype]) {
            $pf->count('other_area_left_in_place:' . $label,
                $DB->count_records(self::SOURCE_TABLE, ['component' => $component, 'itemtype' => $itemtype]));
        }
        return $pf;
    }

    public function verify(context $ctx): array {
        global $DB;
        $failures = [];
        $open = (int) get_config('local_sentientia_platform', 'bizlms_production_open') > 0;

        // Every instance still in the old area is accounted for as a duplicate or an orphan, never forgotten.
        $left = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::SOURCE_TABLE . '} ti
              WHERE ti.component = :lc AND ti.itemtype = :li
                AND NOT EXISTS (SELECT 1 FROM {' . legacymap::TABLE . "} m
                                 WHERE m.sourcetable = :st AND m.sourceid = ti.id AND m.subkey = :sk
                                   AND m.outcome IN ('folded', 'skipped'))",
            ['lc' => self::LEGACY_COMPONENT, 'li' => self::LEGACY_ITEMTYPE, 'st' => self::SOURCE_UNIT, 'sk' => '']);
        if ($left > 0) {
            $failures[] = 'old_area_rows_unaccounted:' . $left;
        }

        // The trail and the map agree.
        $trail = $DB->count_records(self::LEDGER);
        $imported = $DB->count_records(legacymap::TABLE, [
            'feature' => self::FEATURE, 'sourcetable' => self::SOURCE_UNIT, 'subkey' => '', 'outcome' => 'imported',
        ]);
        if ($trail !== $imported) {
            $failures[] = "trail_rows_differ_from_imported_map_rows: trail={$trail} imported={$imported}";
        }
        $unpaired = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . legacymap::TABLE . '} m
              WHERE m.feature = :f AND m.sourcetable = :st AND m.subkey = :sk AND m.outcome = :oc
                AND NOT EXISTS (SELECT 1 FROM {' . self::LEDGER . '} r
                                 WHERE r.id = m.targetid AND r.taginstanceid = m.sourceid)',
            ['f' => self::FEATURE, 'st' => self::SOURCE_UNIT, 'sk' => '', 'oc' => 'imported']);
        if ($unpaired > 0) {
            $failures[] = 'imported_map_rows_without_a_matching_trail_row:' . $unpaired;
        }

        // Every instance the import moved is in the core area. Skipped once the site is open: an administrator
        // may delete a course's tags after go-live, and that is not an import failure.
        if (!$open) {
            $notmoved = $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . self::LEDGER . '} r
                  WHERE NOT EXISTS (SELECT 1 FROM {' . self::SOURCE_TABLE . '} ti
                                     WHERE ti.id = r.taginstanceid AND ti.component = :cc AND ti.itemtype = :ci)',
                ['cc' => self::CORE_COMPONENT, 'ci' => self::CORE_ITEMTYPE]);
            if ($notmoved > 0) {
                $failures[] = 'trail_rows_not_in_the_core_area:' . $notmoved;
            }
        }
        return $failures;
    }

    public function finalise(context $ctx): void {
        // Nothing to reset, copy or purge: the trail table has no sequence that matters, and Moodle's tag caches
        // are purged by the cutover runbook (step 7, purge caches).
    }

    /**
     * Compare the two tag areas. The instances are remapped without touching their tags, so a tag that lives in a
     * different tag collection than the core course area would end up in the wrong collection.
     *
     * @param preflight $pf
     * @param int $total Legacy instances.
     * @return void
     */
    private function preflight_areas(preflight $pf, int $total): void {
        global $DB;
        $core = $DB->get_record('tag_area', ['component' => self::CORE_COMPONENT, 'itemtype' => self::CORE_ITEMTYPE],
            'id, enabled, tagcollid');
        if (!$core) {
            if ($total > 0) {
                $pf->block('core_course_tag_area_missing');
            }
            return;
        }
        $legacy = $DB->get_record('tag_area', ['component' => self::LEGACY_COMPONENT, 'itemtype' => self::LEGACY_ITEMTYPE],
            'id, enabled, tagcollid');
        if ($legacy) {
            if ((int) $legacy->tagcollid !== (int) $core->tagcollid) {
                $pf->block('tag_collection_differs:legacy=' . (int) $legacy->tagcollid . ':core=' . (int) $core->tagcollid);
            }
            if ((int) $legacy->enabled !== (int) $core->enabled) {
                $pf->warn('tag_area_enabled_differs:legacy=' . (int) $legacy->enabled . ':core=' . (int) $core->enabled);
            }
        } else if ($total > 0) {
            $pf->warn('legacy_tag_area_row_missing');
        }

        // The area row may be missing on the restored database, so look at the tags themselves as well.
        $elsewhere = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::SOURCE_TABLE . '} ti
               JOIN {tag} tg ON tg.id = ti.tagid
              WHERE ti.component = :lc AND ti.itemtype = :li AND tg.tagcollid <> :coll',
            ['lc' => self::LEGACY_COMPONENT, 'li' => self::LEGACY_ITEMTYPE, 'coll' => (int) $core->tagcollid]);
        if ($elsewhere > 0) {
            $pf->block('tag_collection_mismatch:' . $elsewhere);
        }
    }

    /**
     * Count what the load step will do with the legacy instances, in the order it decides: a missing course, then a
     * missing tag, then a twin in the core area, else a move. A dry run reports the exact figures; these are the
     * early warning.
     *
     * @param preflight $pf
     * @param int $total Legacy instances.
     * @return void
     */
    private function preflight_rows(preflight $pf, int $total): void {
        global $DB;
        $base = ['lc' => self::LEGACY_COMPONENT, 'li' => self::LEGACY_ITEMTYPE];
        $legacywhere = 'ti.component = :lc AND ti.itemtype = :li';
        $from = '{' . self::SOURCE_TABLE . '} ti';

        $nocourse = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$from} WHERE {$legacywhere}
                AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = ti.itemid)", $base);
        $notag = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$from} WHERE {$legacywhere}
                AND EXISTS (SELECT 1 FROM {course} c WHERE c.id = ti.itemid)
                AND NOT EXISTS (SELECT 1 FROM {tag} tg WHERE tg.id = ti.tagid)", $base);
        // A twin in the core area: same item, user, tag and context (two missing contexts count as equal).
        $twinexists = "EXISTS (SELECT 1 FROM {" . self::SOURCE_TABLE . "} cr
                                WHERE cr.component = :cc AND cr.itemtype = :ci
                                  AND cr.itemid = ti.itemid AND cr.tiuserid = ti.tiuserid AND cr.tagid = ti.tagid
                                  AND (cr.contextid = ti.contextid OR (cr.contextid IS NULL AND ti.contextid IS NULL)))";
        $twinparams = ['cc' => self::CORE_COMPONENT, 'ci' => self::CORE_ITEMTYPE];
        $twins = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$from} WHERE {$legacywhere}
                AND EXISTS (SELECT 1 FROM {course} c WHERE c.id = ti.itemid)
                AND EXISTS (SELECT 1 FROM {tag} tg WHERE tg.id = ti.tagid)
                AND {$twinexists}",
            $base + $twinparams);
        $offcontext = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$from}
               JOIN {course} c ON c.id = ti.itemid
               LEFT JOIN {context} cx ON cx.id = ti.contextid AND cx.contextlevel = :lvl AND cx.instanceid = ti.itemid
              WHERE {$legacywhere} AND cx.id IS NULL", $base + ['lvl' => CONTEXT_COURSE]);

        $pf->count('will_skip_course_missing', $nocourse);
        $pf->count('will_skip_tag_missing', $notag);
        $pf->count('will_fold_duplicate_core_instance', $twins);
        $pf->count('will_move', max(0, $total - $nocourse - $notag - $twins));
        if ($offcontext > 0) {
            // Kept as they are (the map says context is unchanged), but worth knowing before the course tag page is read.
            $pf->warn('instances_not_in_the_course_context:' . $offcontext);
        }

        // local_sentientia_lifecycle reads the core/course tag instances to find the courses a joiner is enrolled in
        // automatically (observer::enrol_in_mandatory_courses, tag name from its mandatory_tag setting, default
        // "mandatory", visible courses only). After this move a BizLMS course that carries that tag becomes such a
        // course. The trigger stays off while sentientia.lifecycle.autoenrol.enabled is off, but the count is worth
        // knowing before it is switched on. It is read as a setting only: this plugin does not depend on lifecycle.
        $mandatory = \core_text::strtolower(trim((string) get_config('local_sentientia_lifecycle', 'mandatory_tag')));
        if ($mandatory === '') {
            $mandatory = 'mandatory';
        }
        $becomesmandatory = $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$from}
               JOIN {course} c ON c.id = ti.itemid AND c.id > 1 AND c.visible = 1
               JOIN {tag} tg ON tg.id = ti.tagid
              WHERE {$legacywhere} AND tg.name = :mname AND NOT {$twinexists}",
            $base + $twinparams + ['mname' => $mandatory]);
        $pf->count('will_move_with_the_lifecycle_mandatory_tag', $becomesmandatory);
        if ($becomesmandatory > 0) {
            $pf->warn('moved_instances_carry_the_lifecycle_mandatory_tag:' . $becomesmandatory);
        }
    }
}
