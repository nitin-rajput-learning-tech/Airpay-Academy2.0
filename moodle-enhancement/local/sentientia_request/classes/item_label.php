<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

/**
 * The name of the thing a request is about.
 *
 * A request is polymorphic (item_type course | path | classroom | program | certification, plus itemid), but
 * the three lists joined only {course}, so every request that was not for a course read "(deleted course)".
 * That was already true of native learning-path requests; the BizLMS import (ADR-032, request feature code
 * fix 1) adds classroom, program and certification rows to the same lists.
 *
 * sql() gives the LEFT JOINs and the extra SELECT and search columns; name() turns a joined row into the label.
 * The joins to the classroom and program tables are added only when those plugins' tables exist, because this
 * plugin depends on the learning-path plugin but not on them.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class item_label {

    /** @var array<string, array{table: string, alias: string, select: string}> item_type => where its name lives. */
    private const SOURCES = [
        'path' => ['table' => 'local_sentientia_learningpath', 'alias' => 'lp', 'select' => 'path_name'],
        'classroom' => ['table' => 'local_sentientia_classroom', 'alias' => 'cl', 'select' => 'classroom_name'],
        'program' => ['table' => 'local_sentientia_programs', 'alias' => 'pr', 'select' => 'program_name'],
    ];

    /**
     * SQL pieces for a query over the request table aliased r, which already joins {course} as c.
     *
     * @return array{select: string, joins: string, search: string[]} select starts with a comma; joins start
     *         with a space; search lists the name columns to add to a free-text search.
     */
    public static function sql(): array {
        global $DB;
        $dbman = $DB->get_manager();
        $select = '';
        $joins = '';
        $search = [];
        foreach (self::SOURCES as $type => $source) {
            if (!$dbman->table_exists($source['table'])) {
                continue;
            }
            $alias = $source['alias'];
            $select .= ", {$alias}.name AS {$source['select']}";
            $joins .= " LEFT JOIN {" . $source['table'] . "} {$alias}"
                . " ON r.item_type = '{$type}' AND {$alias}.id = r.itemid";
            $search[] = "{$alias}.name";
        }
        return ['select' => $select, 'joins' => $joins, 'search' => $search];
    }

    /**
     * The label of a joined request row (not yet passed through format_string).
     *
     * @param \stdClass $r A request row with course_name and the sql() select columns.
     * @return string
     */
    public static function name(\stdClass $r): string {
        $type = (string) ($r->item_type ?? 'course');
        $found = null;
        if ($type === 'course') {
            $found = $r->course_name ?? null;
        } else if (isset(self::SOURCES[$type])) {
            $found = $r->{self::SOURCES[$type]['select']} ?? null;
        } else if ($type === 'certification') {
            // No Sentientia entity exists for a certification yet (mapping doc, gap G3): the legacy id is all
            // there is, so say so instead of calling it a deleted course.
            return get_string('item_certification', 'local_sentientia_request', (int) ($r->itemid ?? 0));
        }
        if ($found !== null && $found !== '') {
            return (string) $found;
        }
        return get_string($type === 'course' ? 'item_deleted_course' : 'item_deleted', 'local_sentientia_request');
    }
}
