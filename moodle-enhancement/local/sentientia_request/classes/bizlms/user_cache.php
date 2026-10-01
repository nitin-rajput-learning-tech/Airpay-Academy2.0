<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

use local_sentientia_platform\bizlms\context;

defined('MOODLE_INTERNAL') || die();

/**
 * Bulk, read-only user rows for a step.
 *
 * The runner hands an ungrouped step one source row per transform() call, so a per-call lookup would be a
 * SELECT per row. A step instead primes this cache once, from the user ids the whole source table names,
 * and each lookup is then an array read. The rows come through the context's legacy reader, in chunks of a
 * thousand; columns the site's user table lacks (open_supervisorid on a vanilla Moodle) are dropped by the
 * reader and so read as absent.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_cache {

    /** Rows per keyset page when a source table is scanned for the user ids it names. */
    private const PAGE = 5000;

    /** @var array<int, \stdClass|null> User id => row, null when no such user. */
    private array $rows = [];

    /** @var string[] Columns to read. */
    private array $columns;

    /**
     * @param string[] $columns Columns of the user table to keep, besides id.
     */
    public function __construct(array $columns) {
        $this->columns = array_values(array_unique(array_merge(['id'], $columns)));
    }

    /**
     * Load the users a source table names, in one pass over the table.
     *
     * @param context $ctx
     * @param string $table Legacy table, without prefix.
     * @param string[] $idcolumns Columns of that table that hold user ids.
     * @return void
     */
    public function prime_from(context $ctx, string $table, array $idcolumns): void {
        if (!$ctx->legacy->exists($table)) {
            return;
        }
        $ids = [];
        $after = 0;
        do {
            $page = $ctx->legacy->page($table, $after, self::PAGE, array_merge(['id'], $idcolumns));
            foreach ($page as $id => $row) {
                $after = (int) $id;
                foreach ($idcolumns as $column) {
                    $value = (int) ($row->{$column} ?? 0);
                    if ($value > 0) {
                        $ids[$value] = $value;
                    }
                }
            }
        } while (count($page) === self::PAGE);
        $this->ensure($ctx, array_values($ids));
    }

    /**
     * Make sure these users are cached.
     *
     * @param context $ctx
     * @param int[] $ids
     * @return void
     */
    public function ensure(context $ctx, array $ids): void {
        $need = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !array_key_exists($id, $this->rows)) {
                $need[$id] = $id;
            }
        }
        if (!$need) {
            return;
        }
        $found = $ctx->legacy->fetch('user', array_values($need), $this->columns);
        foreach ($need as $id) {
            $this->rows[$id] = $found[$id] ?? null;
        }
    }

    /**
     * One user row.
     *
     * @param context $ctx
     * @param int $id
     * @return \stdClass|null Null when there is no such user.
     */
    public function get(context $ctx, int $id): ?\stdClass {
        $this->ensure($ctx, [$id]);
        return $this->rows[$id] ?? null;
    }
}
