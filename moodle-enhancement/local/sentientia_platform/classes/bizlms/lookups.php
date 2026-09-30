<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only, bulk-loaded lookups of rows the restored database keeps (ADR-032,
 * "Id strategy" 2): users, courses, course modules, organisations and so on.
 * Core ids are never mapped, so a step only needs to know they exist.
 *
 * Each id set is loaded once, by keyset paging, into an integer-keyed array, so
 * no step ever runs a per-row SELECT.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lookups {

    /** Rows fetched per keyset page. */
    private const PAGE = 5000;

    /** Organisation table of the Sentientia org engine. */
    private const ORG_TABLE = 'local_sentientia_org';

    /** @var array<string, array<int, bool>> Table => set of ids. */
    private array $idsets = [];

    /** @var array<int, int>|null User id => deleted flag. */
    private ?array $users = null;

    /** @var array<int, string>|null User id => raw open_path (only users that have one). */
    private ?array $userpaths = null;

    /** @var array<int, \stdClass>|null Organisation id => row (id, parentid, path, depth, shortname). */
    private ?array $orgs = null;

    /** @var array<string, \stdClass>|null Organisation path => row. */
    private ?array $orgbypath = null;

    /** @var \Closure|null function(): void, throws when the running feature may not read organisations. */
    private ?\Closure $orgrule = null;

    /**
     * Set the rule every read of the organisation table goes through (the runner sets it once; it knows
     * which feature is running). Organisations are the target of the org importer, so a feature that reads
     * them without depending on it can run before them and see none.
     *
     * @param \Closure|null $rule function(): void that throws when the read is not allowed; null removes it.
     * @return void
     */
    public function guard_org_reads(?\Closure $rule): void {
        $this->orgrule = $rule;
    }

    /**
     * Forget every loaded set. The runner calls this before each feature and after
     * it completes: an earlier feature (org) writes rows that later ones (tenant
     * resolution) read, and an empty set cached by a preflight must not outlive it.
     *
     * @return void
     */
    public function refresh(): void {
        $this->idsets = [];
        $this->users = null;
        $this->userpaths = null;
        $this->orgs = null;
        $this->orgbypath = null;
    }

    /**
     * Does a row with this id exist in a core (or any) table?
     *
     * @param string $table Table name without prefix, for example course or course_modules.
     * @param int $id
     * @return bool
     */
    public function exists(string $table, int $id): bool {
        if ($table === 'user') {
            return isset($this->users()[$id]);
        }
        if (!isset($this->idsets[$table])) {
            $this->idsets[$table] = $this->load_ids($table);
        }
        return isset($this->idsets[$table][$id]);
    }

    /**
     * @param int $id
     * @return bool A user row exists (including a deleted one: history of deleted users is imported).
     */
    public function user_exists(int $id): bool {
        return isset($this->users()[$id]);
    }

    /**
     * @param int $id
     * @return bool The user exists and is not deleted.
     */
    public function user_active(int $id): bool {
        $users = $this->users();
        return isset($users[$id]) && $users[$id] === 0;
    }

    /**
     * @param int $id
     * @return bool
     */
    public function course_exists(int $id): bool {
        return $this->exists('course', $id);
    }

    /**
     * The user's current open_path, as stored. Null when the user has none or
     * the site has no open_path column (a vanilla Moodle).
     *
     * @param int $userid
     * @return string|null
     */
    public function user_path(int $userid): ?string {
        if ($this->userpaths === null) {
            $this->userpaths = [];
            global $DB;
            if (array_key_exists('open_path', $DB->get_columns('user'))) {
                $this->scan('user', ', open_path', function (\stdClass $row): void {
                    $path = trim((string) $row->open_path);
                    if ($path !== '') {
                        $this->userpaths[(int) $row->id] = $path;
                    }
                });
            }
        }
        return $this->userpaths[$userid] ?? null;
    }

    /**
     * Every organisation, keyed by id. Empty when the org table is absent.
     *
     * @return array<int, \stdClass>
     */
    public function orgs(): array {
        if ($this->orgrule !== null) {
            ($this->orgrule)();
        }
        if ($this->orgs === null) {
            $this->orgs = [];
            $this->orgbypath = [];
            global $DB;
            if ($DB->get_manager()->table_exists(self::ORG_TABLE)) {
                $this->scan(self::ORG_TABLE, ', parentid, path, depth, shortname', function (\stdClass $row): void {
                    $org = (object) [
                        'id' => (int) $row->id,
                        'parentid' => (int) $row->parentid,
                        'path' => (string) $row->path,
                        'depth' => (int) $row->depth,
                        'shortname' => (string) $row->shortname,
                    ];
                    $this->orgs[$org->id] = $org;
                    if ($org->path !== '') {
                        $this->orgbypath[$org->path] = $org;
                    }
                });
            }
        }
        return $this->orgs;
    }

    /**
     * @return bool The org engine holds at least one organisation.
     */
    public function has_orgs(): bool {
        return !empty($this->orgs());
    }

    /**
     * @param int $id
     * @return \stdClass|null
     */
    public function org(int $id): ?\stdClass {
        return $this->orgs()[$id] ?? null;
    }

    /**
     * @param string $path A normalised path such as /1/5.
     * @return \stdClass|null
     */
    public function org_by_path(string $path): ?\stdClass {
        $this->orgs();
        return $this->orgbypath[$path] ?? null;
    }

    /**
     * User id => deleted flag, loaded once.
     *
     * @return array<int, int>
     */
    private function users(): array {
        if ($this->users === null) {
            $this->users = [];
            $this->scan('user', ', deleted', function (\stdClass $row): void {
                $this->users[(int) $row->id] = (int) $row->deleted;
            });
        }
        return $this->users;
    }

    /**
     * Load the id set of a table.
     *
     * @param string $table
     * @return array<int, bool>
     */
    private function load_ids(string $table): array {
        fingerprint::assert_identifier($table);
        $ids = [];
        $this->scan($table, '', function (\stdClass $row) use (&$ids): void {
            $ids[(int) $row->id] = true;
        });
        return $ids;
    }

    /**
     * Keyset-page a table by id, calling $each for every row.
     *
     * @param string $table
     * @param string $extracolumns Leading comma then columns, or empty.
     * @param callable $each
     * @return void
     */
    private function scan(string $table, string $extracolumns, callable $each): void {
        global $DB;
        fingerprint::assert_identifier($table);
        $after = 0;
        do {
            $rows = $DB->get_records_sql(
                "SELECT id{$extracolumns} FROM {" . $table . "} WHERE id > :after ORDER BY id",
                ['after' => $after], 0, self::PAGE);
            foreach ($rows as $row) {
                $each($row);
                $after = (int) $row->id;
            }
        } while (count($rows) === self::PAGE);
    }
}
