<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * What a step decided about one source row. A step has no write API: it returns
 * outcomes and the runner and writer act on them (ADR-032, "Side-effect safety").
 *
 * Every source row of a step must get exactly one primary outcome (subkey empty):
 * insert, adopt, merge, fold, archive or skip. insert may also be returned with
 * a non-empty subkey any number of times to fan one source row out into further
 * target rows. update is only valid in a recompute_step.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class outcome {

    /** Create a target row. */
    public const INSERT = 'insert';
    /** Overwrite a target row that is an identical header copy of the source. */
    public const ADOPT = 'adopt';
    /** A duplicate folded into the row that won; the duplicate stays in the legacy table. */
    public const MERGE = 'merge';
    /** The row became part of another, existing target row. */
    public const FOLD = 'fold';
    /** The row stays only in the legacy table, deliberately. */
    public const ARCHIVE = 'archive';
    /** The row could not be imported; the reason is recorded. */
    public const SKIP = 'skip';
    /** Change a target row this importer created or adopted (recompute steps). */
    public const UPDATE = 'update';

    /** Outcome word stored in legacymap.outcome for each primary kind. */
    private const WORDS = [
        self::INSERT => 'imported',
        self::ADOPT => 'adopted',
        self::MERGE => 'merged',
        self::FOLD => 'folded',
        self::ARCHIVE => 'archived',
        self::SKIP => 'skipped',
    ];

    /** What legacymap.detail may hold: up to four lower-case words joined by colons. */
    private const DETAIL_PATTERN = '/^[a-z][a-z0-9_]{0,63}(:[a-z][a-z0-9_]{0,63}){0,3}$/';

    /** What legacymap.subkey may hold: a code, optionally followed by a colon and an id or code (course:17, part:a). */
    private const SUBKEY_PATTERN = '/^[a-z][a-z0-9_]{0,30}(:[a-z0-9_]{1,30})?$/';

    /**
     * Subkey prefixes that would carry a person's id. legacymap holds no personal data, and an id such as user:55 is
     * one (the same reason outcome::skip() takes no id in its detail). A row exploded from a list of users takes its
     * position in the list (pos:2), never the user.
     */
    private const PERSON_PREFIXES = [
        'user', 'userid', 'uid', 'usr', 'person', 'people', 'employee', 'learner', 'student', 'teacher', 'trainer',
        'manager', 'member', 'owner', 'author', 'trainee', 'participant', 'username', 'email', 'name', 'account',
    ];

    /** @var string[] Warning codes, for example truncated:name, derived_timestamp, url_sanitised. */
    public array $warnings = [];

    /** @var string|null exact, normalised, walked_up, fallback:name or unresolved. */
    public ?string $tenantmethod = null;

    /**
     * Use the static factories.
     */
    private function __construct(
        public readonly string $kind,
        public readonly int $sourceid,
        public readonly string $table,
        public readonly ?int $targetid,
        public readonly ?\stdClass $row,
        public readonly string $subkey,
        public readonly string $reason,
        public readonly string $detail,
        public readonly int $winnersourceid,
    ) {
    }

    /**
     * Create a target row for a source row.
     *
     * For a PRESERVE step's primary target the row must not carry an id: the
     * runner sets it to the legacy id.
     *
     * @param int $sourceid Legacy id (or the derived group's key).
     * @param string $table Target table, declared by the importer.
     * @param \stdClass $row Every column to write, including all time* columns.
     * @param string $subkey Empty for the primary row; a fan-out sub-key otherwise: a lower-case code, optionally
     *        with a non-person id after a colon (course:17, part:a). It is stored in legacymap.subkey, a framework
     *        table that holds no personal data, so it never names a user.
     * @return self
     */
    public static function insert(int $sourceid, string $table, \stdClass $row, string $subkey = ''): self {
        if ($table === '') {
            throw new \coding_exception('outcome::insert() needs a target table');
        }
        if ($subkey !== '') {
            $prefix = explode(':', $subkey)[0];
            if (!preg_match(self::SUBKEY_PATTERN, $subkey) || in_array($prefix, self::PERSON_PREFIXES, true)) {
                throw new \coding_exception('outcome::insert() subkey must be a code with an optional non-person id '
                    . '(course:17, part:a), never a user or free text');
            }
        }
        return new self(self::INSERT, $sourceid, $table, null, $row, $subkey, '', '', 0);
    }

    /**
     * Overwrite an existing target row with the full mapping.
     *
     * @param int $sourceid Legacy id.
     * @param string $table Target table.
     * @param int $targetid The occupant, which must be an adoptable header copy.
     * @param \stdClass $fields The full mapping.
     * @return self
     */
    public static function adopt(int $sourceid, string $table, int $targetid, \stdClass $fields): self {
        return new self(self::ADOPT, $sourceid, $table, $targetid, $fields, '', '', '', 0);
    }

    /**
     * Record a duplicate as folded into the row that won. The winner is a source
     * row of the same step; its target becomes this row's target.
     *
     * @param int $sourceid The duplicate.
     * @param int $winnersourceid The surviving source row.
     * @param string $reason Code from the importer's vocabulary.
     * @return self
     */
    public static function merge(int $sourceid, int $winnersourceid, string $reason): self {
        return new self(self::MERGE, $sourceid, '', null, null, '', $reason, '', $winnersourceid);
    }

    /**
     * Record that the row became part of an existing target row.
     *
     * @param int $sourceid
     * @param string $table
     * @param int $targetid
     * @param string $reason
     * @return self
     */
    public static function fold(int $sourceid, string $table, int $targetid, string $reason): self {
        return new self(self::FOLD, $sourceid, $table, $targetid, null, '', $reason, '', 0);
    }

    /**
     * The row stays only in the legacy table.
     *
     * @param int $sourceid
     * @param string $reason
     * @return self
     */
    public static function archive(int $sourceid, string $reason): self {
        return new self(self::ARCHIVE, $sourceid, '', null, null, '', $reason, '', 0);
    }

    /**
     * The row could not be imported.
     *
     * @param int $sourceid
     * @param string $reason Code from the importer's vocabulary.
     * @param string $detail Codes only: lower-case words joined by colons (user_not_found, truncated:title).
     *        Never a number, a name, an e-mail address or free text. It is stored in legacymap.detail, a
     *        framework table that holds no personal data, and an id such as orphan_user:123 is one.
     * @return self
     */
    public static function skip(int $sourceid, string $reason, string $detail = ''): self {
        if ($detail !== '' && !preg_match(self::DETAIL_PATTERN, $detail)) {
            throw new \coding_exception('outcome::skip() detail must be codes only (words joined by colons), no ids or text');
        }
        return new self(self::SKIP, $sourceid, '', null, null, '', $reason, $detail, 0);
    }

    /**
     * Change a target row this importer created or adopted.
     *
     * @param string $table
     * @param int $targetid
     * @param \stdClass $fields Only the columns to change.
     * @return self
     */
    public static function update(string $table, int $targetid, \stdClass $fields): self {
        return new self(self::UPDATE, 0, $table, $targetid, $fields, '', '', '', 0);
    }

    /**
     * Attach a warning code (shown in the report, never blocks).
     *
     * @param string $code For example truncated:name, derived_timestamp, url_sanitised.
     * @return self
     */
    public function warn(string $code): self {
        $this->warnings[] = $code;
        return $this;
    }

    /**
     * Record how the tenant path of this row was decided.
     *
     * @param string $method exact, normalised, walked_up, fallback:name or unresolved.
     * @return self
     */
    public function tenant_method(string $method): self {
        if (!preg_match('/^(exact|normalised|walked_up|unresolved|fallback:[A-Za-z0-9_.\-]+)$/', $method)) {
            throw new \coding_exception('outcome::tenant_method() got an unknown method');
        }
        $this->tenantmethod = $method;
        return $this;
    }

    /**
     * Is this the row that settles a source row (as opposed to a fan-out
     * sub-row or an update)?
     *
     * @return bool
     */
    public function is_primary(): bool {
        return $this->kind !== self::UPDATE && $this->subkey === '';
    }

    /**
     * The word stored in legacymap.outcome for this outcome.
     *
     * @return string
     */
    public function word(): string {
        if (!isset(self::WORDS[$this->kind])) {
            throw new \coding_exception('outcome of kind ' . $this->kind . ' has no map word');
        }
        return self::WORDS[$this->kind];
    }
}
