<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Backup-filename template helper — P0 borrow #11 from Moodle 5.2.
 *
 * Moodle 5.2 exposes a Site Admin setting for the default backup-file
 * name template (e.g. `{site}-{type}-{shortname}-{date}.mbz`). Our
 * 5.1.3+ fork doesn't have it, so this helper provides the API surface
 * today — callers in our SENTIENTIA SCORM pipeline and any future
 * Sentientia LMS export job route through `resolve()` instead of
 * hand-rolling filenames.
 *
 * The 5.2 wholesale upgrade will replace this with the upstream
 * `\core_backup\filename_template` API — surface is intentionally
 * isomorphic so callers don't change.
 *
 * Tokens supported (case-sensitive, surrounded by curly braces):
 *
 *   {site}        Site shortname (from $SITE->shortname, sanitised)
 *   {customer}    Customer name from {@see customer::current()}
 *   {tenant}      Tenant root name (best-effort from $USER->open_path)
 *   {type}        Backup type: course | activity | section | export | scorm
 *   {id}          Numeric id of the subject (courseid, cmid, etc)
 *   {shortname}   Course shortname when available
 *   {date}        YYYYMMDD-HHMM, server timezone
 *   {iso}         ISO-8601 datetime (compact, no separators)
 *
 * Every token is sanitised through {@see clean_filename()} before
 * substitution. Empty tokens collapse to empty string — the resulting
 * filename always ends in `.mbz` (or the caller-provided extension).
 *
 * Maximum filename length: 200 chars (excluding extension). Truncated
 * with `…` mid-string when too long, never truncated to break the
 * extension.
 *
 * @package local_airpay_core
 */
class backup_filename {

    /** Admin setting name (config_plugins.plugin=local_airpay_core). */
    public const SETTING_TEMPLATE = 'backup_filename_template';

    /** Default template — backwards-compatible with Moodle's behaviour. */
    public const DEFAULT_TEMPLATE = 'backup-moodle2-{type}-{id}-{shortname}-{date}';

    /** Max filename length (excluding extension) to stay under FS limits. */
    public const MAX_LEN = 200;

    /**
     * Build a filename from the configured template (or a caller-provided one).
     *
     * @param array $context  Token values. Recognised keys:
     *                          - 'type'      (string)  course|activity|section|export|scorm
     *                          - 'id'        (int)     subject id
     *                          - 'shortname' (string)  course/section shortname
     *                          - 'extension' (string)  default 'mbz'
     *                          - 'template'  (string)  override the configured template
     * @return string Sanitised filename including extension. Never empty.
     *
     * @example
     *   backup_filename::resolve(['type' => 'course', 'id' => 42, 'shortname' => 'pci-dss'])
     *     → 'backup-moodle2-course-42-pci-dss-20260523-1430.mbz'
     */
    public static function resolve(array $context): string {
        global $SITE, $USER;

        $ext = isset($context['extension']) && is_string($context['extension'])
            ? trim($context['extension'], '.')
            : 'mbz';

        $template = $context['template']
            ?? get_config('local_airpay_core', self::SETTING_TEMPLATE)
            ?: self::DEFAULT_TEMPLATE;

        // Build the token map. Every value goes through clean_filename.
        $tokens = [
            '{site}'      => self::sanitise_token((string)($SITE->shortname ?? '')),
            '{customer}'  => self::sanitise_token(self::resolve_customer_name()),
            '{tenant}'    => self::sanitise_token(self::resolve_tenant_name($USER)),
            '{type}'      => self::sanitise_token((string)($context['type'] ?? 'export')),
            '{id}'        => self::sanitise_token((string)((int)($context['id'] ?? 0))),
            '{shortname}' => self::sanitise_token((string)($context['shortname'] ?? '')),
            '{date}'      => self::sanitise_token(userdate(time(), '%Y%m%d-%H%M', 99, false)),
            '{iso}'       => self::sanitise_token(date('Ymd\THis')),
        ];

        // Substitute. Tokens not in the template are simply absent — no error.
        $name = strtr($template, $tokens);

        // Belt-and-braces — strip any path traversal artefacts that survived
        // sanitise_token() if a future contributor adds a less-strict token.
        $name = str_replace(['/', '\\', '..'], '-', $name);

        // Collapse runs of dashes / underscores left over from empty tokens.
        $name = preg_replace('/[-_]{2,}/', '-', $name);
        $name = trim($name, '-_');

        // Never empty.
        if ($name === '') {
            $name = 'sentientia-export-' . time();
        }

        // Enforce max length without breaking the extension.
        if (strlen($name) > self::MAX_LEN) {
            $name = substr($name, 0, self::MAX_LEN - 1);
        }

        return $name . '.' . $ext;
    }

    /**
     * Resolve the customer name for the {customer} token.
     *
     * @return string
     */
    private static function resolve_customer_name(): string {
        // Phase 0/1: single customer. Hard-coded "airpay" — matches the
        // customer::AIRPAY constant. Phase 2+: read from
        // local_airpay_customers table once it exists.
        try {
            $cid = customer::current();
            if ($cid === customer::AIRPAY) {
                return 'airpay';
            }
            return 'customer-' . $cid;
        } catch (\Throwable $e) {
            return 'airpay'; // fail-safe to today's customer name
        }
    }

    /**
     * Resolve the tenant name for the {tenant} token, best-effort.
     *
     * @param \stdClass|null $user
     * @return string
     */
    private static function resolve_tenant_name(?\stdClass $user): string {
        if (!$user || empty($user->open_path)) {
            return '';
        }
        // open_path looks like '/1/2/3' — pick the immediate-tenant id.
        // Without a tenant-name lookup we just emit the id; it's stable
        // and audit-traceable. Future: join to local_costcenter for name.
        $parts = explode('/', trim($user->open_path, '/'));
        $tenantroot = $parts[0] ?? '';
        return $tenantroot !== '' ? 'tenant-' . $tenantroot : '';
    }

    /**
     * Sanitise a single token value — never allow path separators or shell
     * metacharacters into a filename token.
     *
     * @param string $value
     * @return string
     */
    private static function sanitise_token(string $value): string {
        if ($value === '') {
            return '';
        }
        // Moodle's clean_filename strips most dangerous chars; we then
        // lowercase + collapse spaces to '-' for URL-friendly output.
        $v = clean_filename($value);
        $v = str_replace([' ', '_'], '-', $v);
        $v = strtolower($v);
        $v = preg_replace('/[^a-z0-9\-]/', '', $v);
        $v = preg_replace('/-{2,}/', '-', $v);
        return trim($v, '-');
    }

    /**
     * Return the full list of supported tokens for admin-page help text.
     *
     * Used by settings.php to render the placeholder cheat-sheet next to
     * the template field. Stays in one place so a new token added here
     * automatically appears in the admin help.
     *
     * @return array<string,string> token → human description
     */
    public static function token_help(): array {
        return [
            '{site}'      => 'Site shortname',
            '{customer}'  => 'Customer name (airpay / customer-N)',
            '{tenant}'    => 'Tenant root (tenant-N)',
            '{type}'      => 'Backup type (course / activity / section / scorm / export)',
            '{id}'        => 'Subject id (course id, cmid, etc)',
            '{shortname}' => 'Course or section shortname',
            '{date}'      => 'YYYYMMDD-HHMM',
            '{iso}'       => 'ISO-8601 compact (YYYYMMDDTHHMMSS)',
        ];
    }
}
