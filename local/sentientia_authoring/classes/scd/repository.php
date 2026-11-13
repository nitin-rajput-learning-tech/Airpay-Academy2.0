<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_authoring\scd;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\customer;
use local_sentientia_platform\feature_flags;
use local_sentientia_platform\tenant;

/**
 * Storage for Sentientia Canonical Course Documents (ADR-034 D1, Stage 1 slice 1).
 *
 * One row of {local_sentientia_scd} is one immutable version of one course
 * document in one tenant: (customerid, costcenterid, slug, version) is
 * unique, slug and version come from the document (course.slug,
 * course.version), state mirrors provenance.review.state. A changed course is
 * stored as a new version; slice 1 has no update or delete.
 *
 * Every write is validated first (validator, store context) and the row keeps
 * the document's canonical JSON with its sha256 (document::canonical(), the
 * same bytes and hash the reference harness computes), so
 * sha256(scd_json) == sha256 is an invariant that decode() re-checks.
 *
 * Tenant scope (ADR-031): a row belongs to the tenant it was filed under. A
 * caller reads and lists only rows of their own tenant (and customer), unless
 * tenant::is_cross_tenant(). A caller with no resolvable tenant reads nothing
 * and cannot store anything (fail closed). Tenants are a flat costcenterid
 * here; nothing in this class matches on an org path.
 *
 * Feature flag: sentientia.content.scd.enabled (default OFF). create() reads
 * it for the tenant it files under and that tenant's customer
 * (customer::of_tenant()); get() and list_for_actor() read it for each row's
 * own stored customerid and costcenterid. While it is OFF there, every call
 * that would store or return those documents throws err_scd_disabled (a
 * cross-tenant listing of every tenant leaves them out instead).
 * Nothing user-visible uses this class in slice 1 (no page, no menu); the
 * compiler (slice 2) and the studio are its first callers.
 *
 * No capability check here: this class enforces tenant scope and the flag
 * only. A caller must require_capability() first (for example
 * local/sentientia_authoring:generate to store, :review to read for review)
 * and pass $USER as $actor. The actor's id and open_path are trusted as
 * given, so never pass a user record built from request input.
 *
 * @package local_sentientia_authoring
 */
class repository {

    /** Documents table. */
    public const TABLE = 'local_sentientia_scd';

    /** Asset registry table (rows are written by later slices). */
    public const ASSET_TABLE = 'local_sentientia_scd_asset';

    /** File area that holds asset files (ADR-034 D1). */
    public const FILEAREA = 'scd_asset';

    /** Master switch for the canonical course document (ADR-034 D7). */
    public const FLAG = 'sentientia.content.scd.enabled';

    /** Column widths (install.xml); longer values are refused, never cut. */
    public const MAX_SLUG = 128;
    public const MAX_VERSION = 32;

    /** Columns returned by list_for_actor() (the document text is left out). */
    private const LIST_FIELDS = 'id, customerid, costcenterid, slug, version, schemaversion, state, sha256, '
        . 'usercreated, usermodified, timecreated, timemodified';

    /**
     * Is the canonical course document switched on for a tenant?
     *
     * @param int $costcenterid Tenant root
     * @return bool
     */
    public static function is_enabled(int $costcenterid): bool {
        return feature_flags::is_enabled_for(self::FLAG, customer::of_tenant($costcenterid), $costcenterid);
    }

    /**
     * Validate and store one version of a course document.
     *
     * @param string|\stdClass $scd          The document: JSON text, or decoded (objects as \stdClass).
     * @param \stdClass        $actor        The user storing it (id, open_path).
     * @param int|null         $costcenterid Tenant to file it under. Null = the actor's own tenant;
     *                                       another tenant only for a cross-tenant actor.
     * @return \stdClass The stored row, with its id.
     * @throws \moodle_exception err_scd_notenant, error_outoftenant, err_scd_disabled,
     *                           err_scd_invalidjson, err_scd_invalid, err_scd_noversion,
     *                           err_scd_toolong, err_scd_duplicate
     */
    public static function create($scd, \stdClass $actor, ?int $costcenterid = null): \stdClass {
        global $DB;

        $actorid = (int) ($actor->id ?? 0);
        $ownroot = tenant::root_for_user($actor);
        $costcenterid = $costcenterid ?? $ownroot;
        if ($costcenterid <= 0) {
            throw new \moodle_exception('err_scd_notenant', 'local_sentientia_authoring');
        }
        if ($costcenterid !== $ownroot && !tenant::is_cross_tenant($actorid)) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        tenant::assert_valid($costcenterid);
        if (!self::is_enabled($costcenterid)) {
            throw new \moodle_exception('err_scd_disabled', 'local_sentientia_authoring');
        }

        $doc = is_string($scd) ? document::decode($scd) : $scd;
        $result = validator::validate($doc, ['context' => validator::CONTEXT_STORE] + self::target_standards($doc));
        if (!$result->ok()) {
            throw new \moodle_exception('err_scd_invalid', 'local_sentientia_authoring', '', (object) [
                'count'   => count($result->errors()),
                'summary' => s($result->summary()),
            ]);
        }

        // The schema guarantees course.slug and provenance.review.state once
        // validation passed; course.version is optional in the schema but
        // every stored version needs one, exactly as written: it is stored in
        // the version column and inside the canonical JSON, and the two must
        // not differ, so surrounding spaces are refused, never trimmed.
        $slug = (string) $doc->course->slug;
        $version = isset($doc->course->version) && is_string($doc->course->version) ? $doc->course->version : '';
        if ($version === '' || trim($version) !== $version) {
            throw new \moodle_exception('err_scd_noversion', 'local_sentientia_authoring');
        }
        foreach (['course.slug' => [$slug, self::MAX_SLUG], 'course.version' => [$version, self::MAX_VERSION]]
                as $field => [$value, $max]) {
            if (\core_text::strlen($value) > $max) {
                throw new \moodle_exception('err_scd_toolong', 'local_sentientia_authoring', '',
                    (object) ['field' => $field, 'max' => $max]);
            }
        }

        $customerid = customer::of_tenant($costcenterid);
        if ($DB->record_exists(self::TABLE, ['customerid' => $customerid, 'costcenterid' => $costcenterid,
                'slug' => $slug, 'version' => $version])) {
            throw new \moodle_exception('err_scd_duplicate', 'local_sentientia_authoring', '',
                (object) ['slug' => s($slug), 'version' => s($version)]);
        }

        $canonical = document::canonical($doc);
        $now = time();
        $row = new \stdClass();
        $row->customerid    = $customerid;
        $row->costcenterid  = $costcenterid;
        $row->slug          = $slug;
        $row->version       = $version;
        $row->schemaversion = (string) $doc->scd_version;
        $row->state         = (string) $doc->provenance->review->state;
        $row->scd_json      = $canonical;
        $row->sha256        = hash('sha256', $canonical);
        $row->usercreated   = $actorid;
        $row->usermodified  = $actorid;
        $row->timecreated   = $now;
        $row->timemodified  = $now;
        try {
            $row->id = (int) $DB->insert_record(self::TABLE, $row);
        } catch (\dml_write_exception $e) {
            // Another request stored the same version between the check above
            // and this insert, and the unique index refused it. Every other
            // column limit was checked before: slug and version lengths here,
            // scd_version (const) and the review state (enum) by the schema.
            throw new \moodle_exception('err_scd_duplicate', 'local_sentientia_authoring', '',
                (object) ['slug' => s($slug), 'version' => s($version)], $e->debuginfo ?? null);
        }
        return $row;
    }

    /**
     * The validator's R05 targets for a document: its own delivery.standards,
     * with the schema's scorm2004_4 named as the validator names it
     * (scorm2004). A document that does not target SCORM 1.2 is then not
     * refused for the SCORM 1.2 node cap (only warned). Without a usable list
     * the validator's default targets apply, SCORM 1.2 included.
     *
     * @param mixed $doc The decoded document.
     * @return array ['standards' => string[]], or [] for the default.
     */
    private static function target_standards($doc): array {
        $delivery = $doc instanceof \stdClass && isset($doc->delivery) ? $doc->delivery : null;
        $standards = $delivery instanceof \stdClass && isset($delivery->standards) && is_array($delivery->standards)
            ? $delivery->standards : [];
        $names = [];
        foreach ($standards as $standard) {
            if (is_string($standard)) {
                $names[] = $standard === 'scorm2004_4' ? 'scorm2004' : $standard;
            }
        }
        return $names ? ['standards' => array_values(array_unique($names))] : [];
    }

    /**
     * One stored document row, if the actor may read it.
     *
     * @param int       $id
     * @param \stdClass $actor
     * @return \stdClass|null Null when missing or outside the actor's tenant.
     * @throws \moodle_exception err_scd_disabled when the row's tenant has the flag OFF
     */
    public static function get(int $id, \stdClass $actor): ?\stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['id' => $id]);
        if (!$row || !self::can_read($row, $actor)) {
            return null;
        }
        if (!self::row_enabled($row)) {
            throw new \moodle_exception('err_scd_disabled', 'local_sentientia_authoring');
        }
        return $row;
    }

    /**
     * Stored versions the actor may read, newest first, without the document
     * text (get() returns that).
     *
     * @param \stdClass $actor
     * @param int|null  $costcenterid Only this tenant. A scoped actor may name only their own.
     * @param string    $slug         Only this course slug ('' = every course).
     * @param int       $limit
     * @return \stdClass[]
     * @throws \moodle_exception error_outoftenant for another tenant; err_scd_disabled
     */
    public static function list_for_actor(\stdClass $actor, ?int $costcenterid = null, string $slug = '',
            int $limit = 50): array {
        global $DB;
        $where = [];
        $params = [];
        if (tenant::is_cross_tenant((int) ($actor->id ?? 0))) {
            if ($costcenterid !== null && !self::is_enabled($costcenterid)) {
                throw new \moodle_exception('err_scd_disabled', 'local_sentientia_authoring');
            }
            // Every (customer, tenant) pair that holds documents and has the
            // flag ON for that pair; within one tenant when the caller named it.
            $pairsql = 'SELECT DISTINCT customerid, costcenterid FROM {' . self::TABLE . '}';
            $pairparams = [];
            if ($costcenterid !== null) {
                $pairsql .= ' WHERE costcenterid = :cid';
                $pairparams['cid'] = $costcenterid;
            }
            $scopes = [];
            $pairs = $DB->get_recordset_sql($pairsql, $pairparams);
            foreach ($pairs as $pair) {
                if (self::row_enabled($pair)) {
                    $n = count($scopes);
                    $scopes[] = "(customerid = :cust{$n} AND costcenterid = :cc{$n})";
                    $params["cust{$n}"] = (int) $pair->customerid;
                    $params["cc{$n}"] = (int) $pair->costcenterid;
                }
            }
            $pairs->close();
            if (!$scopes) {
                return [];
            }
            $where[] = '(' . implode(' OR ', $scopes) . ')';
        } else {
            $root = tenant::root_for_user($actor);
            if ($root <= 0) {
                // ADR-031: no tenant of one's own means nothing, not every 0 row.
                return [];
            }
            if ($costcenterid !== null && $costcenterid !== $root) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
            if (!self::is_enabled($root)) {
                throw new \moodle_exception('err_scd_disabled', 'local_sentientia_authoring');
            }
            $where[] = 'costcenterid = :cid AND customerid = :custid';
            $params['cid'] = $root;
            $params['custid'] = customer::of_tenant($root);
        }
        if ($slug !== '') {
            $where[] = 'slug = :slug';
            $params['slug'] = $slug;
        }
        return array_values($DB->get_records_select(self::TABLE, implode(' AND ', $where), $params,
            'timecreated DESC, id DESC', self::LIST_FIELDS, 0, $limit));
    }

    /**
     * The decoded document of a stored row, after checking its hash.
     *
     * @param \stdClass $row A row from get() (it must carry scd_json and sha256).
     * @return \stdClass
     * @throws \moodle_exception err_scd_integrity when the text no longer matches its hash
     */
    public static function decode(\stdClass $row): \stdClass {
        if (!isset($row->scd_json, $row->sha256) || !hash_equals((string) $row->sha256, hash('sha256', (string) $row->scd_json))) {
            throw new \moodle_exception('err_scd_integrity', 'local_sentientia_authoring', '', (int) ($row->id ?? 0));
        }
        $doc = document::decode((string) $row->scd_json);
        if (!$doc instanceof \stdClass) {
            throw new \moodle_exception('err_scd_integrity', 'local_sentientia_authoring', '', (int) ($row->id ?? 0));
        }
        return $doc;
    }

    /**
     * Is the flag ON for a stored row's own customer and tenant?
     *
     * @param \stdClass $row Carries customerid and costcenterid.
     * @return bool
     */
    private static function row_enabled(\stdClass $row): bool {
        return feature_flags::is_enabled_for(self::FLAG, (int) $row->customerid, (int) $row->costcenterid);
    }

    /**
     * May the actor read this row? Cross-tenant callers read every tenant;
     * everyone else only rows of their own tenant and customer.
     *
     * @param \stdClass $row
     * @param \stdClass $actor
     * @return bool
     */
    private static function can_read(\stdClass $row, \stdClass $actor): bool {
        if (tenant::is_cross_tenant((int) ($actor->id ?? 0))) {
            return true;
        }
        $root = tenant::root_for_user($actor);
        return $root > 0
            && (int) $row->costcenterid === $root
            && (int) $row->customerid === customer::of_tenant($root);
    }
}
