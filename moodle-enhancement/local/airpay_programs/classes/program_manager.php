<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

namespace local_airpay_programs;

defined('MOODLE_INTERNAL') || die();

/**
 * Program manager — CRUD for certification programs.
 *
 * Handles top-level program operations. Levels and course assignments
 * are managed via separate methods within this class for now (can be
 * extracted to level_manager.php as the feature grows).
 *
 * @package    local_airpay_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_manager {

    private const TABLE          = 'local_airpay_programs';
    private const LEVELS_TABLE   = 'local_airpay_programs_levels';
    private const COURSES_TABLE  = 'local_airpay_programs_courses';
    private const USERS_TABLE    = 'local_airpay_programs_users';

    /** Status values matching install.xml. */
    public const STATUS_DRAFT    = 0;
    public const STATUS_ACTIVE   = 1;
    public const STATUS_ARCHIVED = 2;

    /**
     * Get a program by ID.
     */
    public static function get(int $id) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id]);
    }

    /**
     * Count programs, optionally tenant-scoped.
     */
    public static function count_programs(string $pathfilter = ''): int {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::TABLE)) {
            return 0;
        }
        if (!empty($pathfilter)) {
            return $DB->count_records_select(self::TABLE, "open_path LIKE :p", ['p' => $pathfilter]);
        }
        return $DB->count_records(self::TABLE);
    }

    /**
     * Count levels for a program.
     */
    public static function count_levels(int $programid): int {
        global $DB;
        return $DB->count_records(self::LEVELS_TABLE, ['programid' => $programid]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CRUD operations
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Create a new program.
     *
     * @param object $data  name, description, costcenterid, completion_required
     * @return int  New program ID
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB;

        if (empty($data->name)) {
            throw new \moodle_exception('missingrequiredfields', 'local_airpay_programs');
        }

        $record = new \stdClass();
        $record->name                = trim($data->name);
        $record->description         = $data->description ?? '';
        $record->costcenterid        = (int) ($data->costcenterid ?? 0);
        $record->status              = (int) ($data->status ?? self::STATUS_DRAFT);
        $record->visible             = isset($data->visible) ? (int) $data->visible : 1;
        $record->completion_required = isset($data->completion_required) ? (int) $data->completion_required : 1;
        $record->timecreated         = time();
        $record->timemodified        = time();

        if ($record->costcenterid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            if ($org) {
                $record->open_path = $org->path;
            }
        }

        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Update an existing program.
     */
    public static function update(int $id, object $data): bool {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $record = (object) ['id' => $id, 'timemodified' => time()];
        $fields = ['name', 'description', 'costcenterid', 'status', 'visible', 'completion_required'];
        foreach ($fields as $field) {
            if (isset($data->$field)) {
                $record->$field = $data->$field;
            }
        }

        if (isset($record->costcenterid) && $record->costcenterid != $existing->costcenterid) {
            $org = $DB->get_record('local_airpay_org', ['id' => $record->costcenterid]);
            $record->open_path = $org ? $org->path : '';
        }

        $DB->update_record(self::TABLE, $record);
        return true;
    }

    /**
     * Change program status.
     */
    public static function change_status(int $id, int $status): int {
        global $DB;

        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            throw new \moodle_exception('invalidstatus', 'local_airpay_programs');
        }

        $DB->update_record(self::TABLE, (object) [
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
        ]);
        return $status;
    }

    /**
     * Delete a program and all its levels, course assignments, enrollments.
     */
    public static function delete(int $id): bool {
        global $DB;

        $DB->get_record(self::TABLE, ['id' => $id], '*', MUST_EXIST);

        $transaction = $DB->start_delegated_transaction();
        try {
            // Get level IDs for cascade.
            $levelids = $DB->get_fieldset_select(self::LEVELS_TABLE,
                'id', 'programid = :pid', ['pid' => $id]);

            // Delete course assignments per level.
            if (!empty($levelids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($levelids, SQL_PARAMS_NAMED, 'lid');
                $DB->delete_records_select(self::COURSES_TABLE, "levelid $insql", $inparams);
            }

            // Delete levels.
            $DB->delete_records(self::LEVELS_TABLE, ['programid' => $id]);

            // Delete enrollments.
            $DB->delete_records(self::USERS_TABLE, ['programid' => $id]);

            // Delete program.
            $DB->delete_records(self::TABLE, ['id' => $id]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return true;
    }
}
