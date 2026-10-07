<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * Which organisation a BizLMS evaluation form or template belongs to (ADR-032, mapping doc section 18, "Tenant rule").
 *
 * Sentientia scopes an evaluation by an ORGANISATION (costcenterid is a local_sentientia_org id, open_path that
 * organisation's path), never by a bare tenant root, so the answer is always an organisation the org importer
 * has written, or none.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_scope {

    /**
     * Pick the first usable candidate and turn it into an organisation.
     *
     * The candidates keep their place even when empty, so the method the framework reports (exact, normalised,
     * walked_up, fallback:<name>) says which rule decided: pass them in order and leave an absent one as null.
     *
     * @param context $ctx
     * @param array<string, string|null> $candidates name => raw path or root path, most specific first.
     * @return array{0: string|null, 1: int, 2: string} [open_path, costcenterid, method]. No candidate works:
     *         [null, 0, 'unresolved']. A path with no organisation row (an organisation table that is empty,
     *         as in a dry run) keeps the path and gets costcenterid 0, which only cross-tenant callers can see.
     */
    public static function resolve(context $ctx, array $candidates): array {
        [$path, , $method] = $ctx->tenant->resolve($candidates);
        if ($path === null) {
            return [null, 0, 'unresolved'];
        }
        $org = $ctx->tenant->org_for_path($path, false);
        return [$path, $org === null ? 0 : (int) $org->id, $method];
    }

    /**
     * BizLMS kept the tenant ROOT id in a costcenterid column of type char. It is a candidate only when it is a
     * positive whole number.
     *
     * @param string|int|null $costcenterid
     * @return string|null '/<root>' or null
     */
    public static function root_path($costcenterid): ?string {
        $text = trim((string) $costcenterid);
        return (ctype_digit($text) && (int) $text > 0) ? '/' . (int) $text : null;
    }

    // There is deliberately no helper that reads a tenant from a PERSON (the user who last edited a form). The
    // classroom importer rejects that guess and so does this one (decision evaluation.tenant_editor_fallback =
    // not_used): a person's current tenant says nothing about the tenant a record belonged to when they touched it.
}
