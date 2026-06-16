<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use local_sentientia_api\rate_limiter;
use local_sentientia_api\request_log;
use local_sentientia_platform\feature_flags;
use local_sentientia_platform\tenant;

/**
 * Shared gate for every v1 public-API external function.
 *
 * Concrete endpoints call $tenant = self::open_v1('local_..._endpoint')
 * at the top of execute() AFTER validate_parameters(). That single call
 * enforces, in order:
 *
 *   1. Feature flag  — sentientia.api.enabled must be ON, else 'api_disabled'.
 *      (Write endpoints additionally pass $write=true to require
 *       sentientia.api.write.enabled.)
 *   2. Context       — validate_context(system) so the WS token's allowed
 *      context is honoured (this is what enforces token auth at the WS layer;
 *      the capability declared in db/services.php is enforced by core before
 *      execute() even runs).
 *   3. Rate limit    — per-user fixed-window budget, else 'ratelimited' (429).
 *   4. Tenant scope  — resolves the caller's tenant root from open_path and
 *      returns it so the endpoint scopes every query. Site admins get 0
 *      (all tenants), matching the platform tenant helper contract.
 *
 * The capability check itself is declared per-function in db/services.php
 * (local/sentientia_api:read|write) and enforced by Moodle's WS dispatcher.
 * We re-assert it defensively here too, because these classes are also
 * directly unit-testable (bypassing the dispatcher).
 *
 * @package local_sentientia_api
 */
abstract class base extends external_api {

    /**
     * Run the full gate. Returns the caller's tenant root.
     *
     * @param string $endpoint   External function name (for the log row)
     * @param string $capability Capability to require (read|write|manage)
     * @param bool   $write      Require the write sub-flag too
     * @param string $method     Logical method for the log row
     * @return int Tenant root (0 = site admin / all tenants)
     * @throws \moodle_exception
     */
    protected static function open_v1(string $endpoint, string $capability,
                                       bool $write = false, string $method = 'GET'): int {
        global $USER;

        $context = \context_system::instance();
        self::validate_context($context);

        // 1. Feature flag gate.
        if (!class_exists('\local_sentientia_platform\feature_flags')
                || !feature_flags::is_enabled('sentientia.api.enabled')) {
            self::safe_log($endpoint, $method, 403);
            throw new \moodle_exception('api_disabled', 'local_sentientia_api');
        }
        if ($write && !feature_flags::is_enabled('sentientia.api.write.enabled')) {
            self::safe_log($endpoint, $method, 403);
            throw new \moodle_exception('api_write_disabled', 'local_sentientia_api');
        }

        // 2. Capability (defensive — also enforced by the WS dispatcher).
        require_capability($capability, $context);

        // 3. Rate limit.
        try {
            rate_limiter::check_and_increment((int) $USER->id);
        } catch (\moodle_exception $e) {
            self::safe_log($endpoint, $method, 429);
            throw $e;
        }

        // 4. Tenant scope.
        $tenantroot = tenant::root_for_current_user();
        if (!is_siteadmin() && $tenantroot <= 0) {
            self::safe_log($endpoint, $method, 403);
            throw new \moodle_exception('error_notenant', 'local_sentientia_api');
        }

        self::safe_log($endpoint, $method, 200, $tenantroot);
        return $tenantroot;
    }

    /**
     * Build the tenant WHERE-clause for a course query, scoping by the
     * course's open_path. Site admins (root 0) get an unrestricted filter.
     *
     * @param string $alias Course table alias
     * @return array{0:string,1:array}
     */
    protected static function course_tenant_filter(string $alias): array {
        return tenant::path_filter($alias, 'open_path', false);
    }

    /**
     * Best-effort log write that never blows up the request.
     */
    private static function safe_log(string $endpoint, string $method, int $status,
                                      int $tenantroot = 0): void {
        global $USER;
        try {
            request_log::record((int) ($USER->id ?? 0), $tenantroot, $endpoint, $method, $status);
        } catch (\Throwable $ignored) {
            // Logging must never break the API call.
            debugging('sentientia_api: request log write failed: ' . $ignored->getMessage(),
                DEBUG_DEVELOPER);
        }
    }
}
