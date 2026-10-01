<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Second pass of the course_tags feature: the reviewed in-place UPDATE of tag_instance.
 *
 * It works on the trail rows the load step imported (recompute steps run over every imported row of the feature, so
 * it is idempotent by construction) and moves the tag instance each one names from the old BizLMS area to the core
 * course area. Only component and itemtype change. A row that already sits in the core area is left alone, which
 * makes a repeat run write nothing, and a row that is in neither area is never touched.
 *
 * The update is a direct one, made by the writer. The core_tag_tag API is not used: it would fire tag_added and
 * tag_removed events and could renumber the tags of the item.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_tags_remap_step extends recompute_step {

    public function key(): string {
        return course_tags_importer::FEATURE . '.remap';
    }

    public function targettable(): string {
        return course_tags_importer::LEDGER;
    }

    public function recompute(array $targetids, context $ctx): array {
        $trail = $ctx->legacy->fetch(course_tags_importer::LEDGER, $targetids, ['taginstanceid']);
        $instanceids = [];
        foreach ($trail as $move) {
            $instanceids[] = (int) $move->taginstanceid;
        }
        $instances = $ctx->legacy->fetch(course_tags_importer::SOURCE_TABLE, $instanceids, ['component', 'itemtype']);

        $out = [];
        foreach ($instances as $id => $row) {
            if ($row->component !== course_tags_importer::LEGACY_COMPONENT
                    || $row->itemtype !== course_tags_importer::LEGACY_ITEMTYPE) {
                // Already moved (a repeat run), or not an instance of the old area at all: nothing to write.
                continue;
            }
            $out[] = outcome::update(course_tags_importer::SOURCE_TABLE, (int) $id, (object) [
                'component' => course_tags_importer::CORE_COMPONENT,
                'itemtype' => course_tags_importer::CORE_ITEMTYPE,
            ]);
        }
        return $out;
    }
}
