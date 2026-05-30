<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant identity seam — ADR-019, Sentientia independence Wave 2.
 *
 * The SINGLE abstraction the rest of Sentientia calls to resolve a user's
 * tenant + tenant-path access, instead of reading `$USER->open_path` directly
 * (the hard BizLMS coupling catalogued in docs/DEPRECATION-SCHEDULE.md row 7).
 *
 * Surface (Wave 2 — extended so the ~20 open_path call sites can migrate):
 *  - root_for_user / root_for_current_user — the tenant root (top segment).
 *  - segments_for_user / department_for_user / subdepartment_for_user /
 *    path_for_user — the rest of the open_path decomposition, so callers stop
 *    hand-rolling `explode('/', trim($USER->open_path, '/'))`.
 *  - path_root(string) — the tenant root of an arbitrary entity open_path
 *    string (e.g. mdl_course.open_path), as opposed to a user record.
 *  - can_access_path / require_path_access — tenant-path access enforcement.
 *  - sql_filter / path_filter — tenant-scoping WHERE fragments for queries.
 *
 * Behind the default-ON `tenant_identity_legacy` flag the resolver + the
 * access/filter helpers delegate to the legacy BizLMS implementation
 * (`local_airpay_core\tenant`), so behaviour is byte-identical to current
 * production. When a future wave builds the Sentientia tenant registry,
 * flipping the flag OFF switches the source; until that registry exists
 * (ADR-018 Wave 3+), the OFF path falls back to legacy so nothing can break.
 *
 * The seam carries NO hard dependency on local_airpay_core: every delegation
 * is class_exists()-guarded with an inline fallback that mirrors the legacy
 * algorithm, so local_sentientia_core can ship standalone for Enterprise N.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_identity {

    /** Sentinel returned when no tenant can be resolved. */
    public const NO_TENANT = 0;

    /**
     * Is the legacy open_path resolver active?
     *
     * Default ON: an unset config (fresh install / pre-settings) is treated
     * as enabled so production behaviour never changes implicitly.
     */
    public static function use_legacy_open_path(): bool {
        $v = get_config('local_sentientia_core', 'tenant_identity_legacy');
        return $v === false ? true : (bool) $v;
    }

    /**
     * Resolve a user's tenant root id.
     *
     * @param \stdClass $user A user record carrying (at least) open_path.
     * @return int Tenant root id, or self::NO_TENANT (0) if unresolved.
     */
    public static function root_for_user(\stdClass $user): int {
        if (self::use_legacy_open_path()) {
            return self::legacy_root($user);
        }
        // Wave 3+: the Sentientia tenant registry. Not built yet — fall back
        // to legacy so the OFF state can be exercised without breaking auth.
        debugging('local_sentientia_core: Sentientia tenant registry not yet '
            . 'available; falling back to legacy open_path resolution.',
            DEBUG_DEVELOPER);
        return self::legacy_root($user);
    }

    /**
     * Resolve the tenant root for the current $USER.
     *
     * @return int Tenant root id, or self::NO_TENANT (0) if logged out.
     */
    public static function root_for_current_user(): int {
        global $USER;
        if (empty($USER->id)) {
            return self::NO_TENANT;
        }
        return self::root_for_user($USER);
    }

    /**
     * The user's open_path as an array of integer segments.
     *
     * "/1/2/3" → [1, 2, 3];  "/77" → [77];  "" / null / non-numeric-root → [].
     * Replaces the duplicated `explode('/', trim($USER->open_path, '/'))`
     * idiom across the Wave-2 call sites. Anchored on a numeric tenant root so
     * results stay consistent with root_for_user().
     *
     * @param \stdClass $user
     * @return int[] Zero-indexed: [0]=tenant root, [1]=department, [2]=sub-dept…
     */
    public static function segments_for_user(\stdClass $user): array {
        $raw = trim((string) ($user->open_path ?? ''), '/');
        if ($raw === '') {
            return [];
        }
        $parts = explode('/', $raw);
        // No usable decomposition unless the first segment is a tenant id —
        // mirrors the ctype_digit guard in root_for_user()/legacy_root().
        if (!ctype_digit($parts[0])) {
            return [];
        }
        return array_map('intval', $parts);
    }

    /**
     * The user's department id — the SECOND open_path segment ("/root/DEPT/…").
     *
     * @param \stdClass $user
     * @return int Department id, or 0 if the path has no department segment.
     */
    public static function department_for_user(\stdClass $user): int {
        return self::segments_for_user($user)[1] ?? 0;
    }

    /**
     * The user's sub-department id — the THIRD open_path segment.
     *
     * @param \stdClass $user
     * @return int Sub-department id, or 0 if the path has no third segment.
     */
    public static function subdepartment_for_user(\stdClass $user): int {
        return self::segments_for_user($user)[2] ?? 0;
    }

    /**
     * The user's raw open_path string, for callers that pass it straight to a
     * filter/comparison helper rather than parse it.
     *
     * @param \stdClass $user
     * @return string The stored open_path (e.g. "/1/2/3"), or '' if unset.
     */
    public static function path_for_user(\stdClass $user): string {
        return (string) ($user->open_path ?? '');
    }

    /**
     * Tenant root from an arbitrary open_path STRING (e.g. mdl_course.open_path),
     * as opposed to a user record. "/77/5" → 77; "/1" → 1; "" → 0.
     *
     * @param string $openpath
     * @return int Tenant root, or self::NO_TENANT (0) if unparseable.
     */
    public static function path_root(string $openpath): int {
        $parts = explode('/', trim($openpath, '/'));
        if (!isset($parts[0]) || !ctype_digit($parts[0])) {
            return self::NO_TENANT;
        }
        return (int) $parts[0];
    }

    /**
     * Non-throwing tenant-path access check: may the viewer access a resource
     * carrying the given open_path? Mirrors require_path_access() as a boolean.
     *
     * @param string $entitypath The resource's open_path (e.g. course->open_path)
     * @param int|null $viewerid  Defaults to the current $USER
     * @return bool
     */
    public static function can_access_path(string $entitypath, ?int $viewerid = null): bool {
        try {
            self::require_path_access($entitypath, $viewerid);
            return true;
        } catch (\moodle_exception $e) {
            return false;
        }
    }

    /**
     * Enforce tenant access on a resource's open_path; throws on violation.
     *
     * Site admins pass; an empty resource path passes (legacy unscoped row);
     * otherwise the resource path must equal "/root" or descend from "/root/".
     * Delegates to local_airpay_core\tenant when present (single source of
     * truth for the rule), else applies the identical inline check.
     *
     * @param string $entitypath
     * @param int|null $viewerid Defaults to the current $USER
     */
    public static function require_path_access(string $entitypath, ?int $viewerid = null): void {
        if (class_exists('\local_airpay_core\tenant')) {
            \local_airpay_core\tenant::require_path_access($entitypath, $viewerid);
            return;
        }
        // Inline fallback — mirrors local_airpay_core\tenant::require_path_access().
        if ($entitypath === '') {
            return;  // legacy unscoped row — same tolerance as the inline pattern
        }
        global $DB, $USER;
        $isadmin = ($viewerid === null || $viewerid === ($USER->id ?? 0))
            ? is_siteadmin()
            : is_siteadmin($viewerid);
        if ($isadmin) {
            return;
        }
        if ($viewerid === null || $viewerid === ($USER->id ?? 0)) {
            $viewerroot = self::root_for_user($USER);
        } else {
            $viewer = $DB->get_record('user', ['id' => $viewerid], 'id, open_path');
            $viewerroot = $viewer ? self::root_for_user($viewer) : self::NO_TENANT;
        }
        if ($viewerroot <= self::NO_TENANT) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_core');
        }
        $exact  = '/' . $viewerroot;
        $prefix = '/' . $viewerroot . '/';
        if ($entitypath !== $exact && strpos($entitypath, $prefix) !== 0) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_core');
        }
    }

    /**
     * Tenant-scoping WHERE fragment for a costcenterid column.
     *
     * Pass-through to local_airpay_core\tenant::sql_filter so migrated callers
     * depend only on local_sentientia_core. Site admins get '1=1'.
     *
     * @param string $alias Table alias prefix (e.g. 'h' for h.costcenterid)
     * @return array{0: string, 1: array}
     */
    public static function sql_filter(string $alias = ''): array {
        if (class_exists('\local_airpay_core\tenant')) {
            return \local_airpay_core\tenant::sql_filter($alias);
        }
        // Inline fallback — mirrors local_airpay_core\tenant::sql_filter().
        $col = $alias === '' ? 'costcenterid' : "{$alias}.costcenterid";
        if (is_siteadmin()) {
            return ['1=1', []];
        }
        return ["$col = :aptenantroot", ['aptenantroot' => self::root_for_current_user()]];
    }

    /**
     * Tenant-scoping WHERE fragment for an open_path-style column.
     *
     * Pass-through to local_airpay_core\tenant::path_filter so migrated callers
     * depend only on local_sentientia_core. Matches "/N" exactly OR "/N/…" as a
     * prefix where N is the caller's tenant root. Site admins get '1=1'; an
     * unknown tenant gets '1=0' (matches nothing — defensive).
     *
     * @param string $alias  Table alias (defaults to no alias)
     * @param string $column Path column name (default: 'open_path')
     * @param bool   $allow_null Also match rows where the column is NULL
     * @return array{0: string, 1: array}
     */
    public static function path_filter(string $alias = '', string $column = 'open_path',
                                        bool $allow_null = false): array {
        if (class_exists('\local_airpay_core\tenant')) {
            return \local_airpay_core\tenant::path_filter($alias, $column, $allow_null);
        }
        // Inline fallback — mirrors local_airpay_core\tenant::path_filter().
        $col = $alias === '' ? $column : "{$alias}.{$column}";
        if (is_siteadmin()) {
            return ['1=1', []];
        }
        $root = self::root_for_current_user();
        if ($root <= self::NO_TENANT) {
            return ['1=0', []];
        }
        $nullclause = $allow_null ? " OR {$col} IS NULL" : '';
        return [
            "({$col} = :appathexact OR {$col} LIKE :appathprefix{$nullclause})",
            ['appathexact' => '/' . $root, 'appathprefix' => '/' . $root . '/%'],
        ];
    }

    /**
     * Legacy resolver: derive the tenant root from the BizLMS open_path.
     *
     * Delegates to `local_airpay_core\tenant` when present (single source of
     * truth); otherwise parses inline with the identical algorithm so the
     * seam carries no hard dependency on local_airpay_core.
     *
     * @param \stdClass $user
     * @return int
     */
    private static function legacy_root(\stdClass $user): int {
        if (class_exists('\local_airpay_core\tenant')) {
            return \local_airpay_core\tenant::root_for_user($user);
        }
        // Inline fallback — mirrors local_airpay_core\tenant::root_for_user().
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        if (!isset($parts[0]) || !ctype_digit($parts[0])) {
            return self::NO_TENANT;
        }
        return (int) $parts[0];
    }
}
