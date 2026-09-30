<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * Tenant path of a catalogue row: a category, a skill or a course level (mapping doc, section 14, "Tenant").
 *
 * The legacy row's own open_path wins when it is a usable path under a registered tenant root. The upgrade
 * default '0' (and the empty string) are not a path, so the row falls back to its legacy costcenterid, and with
 * neither it has no tenant: the shared platform catalogue, which is what a null path has always meant in
 * Sentientia (and which BizLMS hid, see the mapping doc). The decision tenant.unresolved.skills says whether such
 * a row is imported pathless (the owner's choice) or skipped. Every row reports how its path was decided.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue_tenant {

    /** Decision key for rows whose tenant cannot be decided. */
    public const DECISION = 'tenant.unresolved.skills';

    /**
     * Decide the path of one catalogue row.
     *
     * @param context $ctx
     * @param mixed $legacypath The row's open_path ('0', '' or null when it has none).
     * @param mixed $costcenterid The row's costcenterid, when the production column exists.
     * @return array{0: ?string, 1: string, 2: bool} [path, method, skip]: the normalised path or null; how it was
     *         decided (exact, normalised, walked_up, fallback:costcenter or unresolved); true when the row must be
     *         skipped because it has no tenant and the owner chose to skip such rows.
     */
    public static function resolve(context $ctx, mixed $legacypath, mixed $costcenterid): array {
        $candidates = [
            'row' => $legacypath === null ? null : (string) $legacypath,
            'costcenter' => (is_numeric($costcenterid) && (int) $costcenterid > 0) ? (int) $costcenterid : null,
        ];
        [$path, , $method] = $ctx->tenant->resolve($candidates);
        if ($path === null) {
            return [null, 'unresolved', (string) $ctx->decision(self::DECISION) === 'skip'];
        }
        return [$path, $method, false];
    }
}
