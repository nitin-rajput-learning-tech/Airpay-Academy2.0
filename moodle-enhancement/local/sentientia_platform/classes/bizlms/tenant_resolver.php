<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Decides which tenant path an imported row belongs to (ADR-032, tenant helper).
 *
 * BizLMS rows store no tenant of their own, so importers attribute one from
 * candidates such as the row's own path, its parent's path or the user's current
 * open_path. The resolver normalises each candidate, validates its root through
 * tenant::assert_valid() (never VALID_TENANTS directly), checks the node against
 * the org engine, and reports how it got the answer so the report can count it.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_resolver {

    /** @var lookups */
    private lookups $lookups;

    /**
     * @param lookups $lookups
     */
    public function __construct(lookups $lookups) {
        $this->lookups = $lookups;
    }

    /**
     * Normalise a raw path.
     *
     * ' 1/5/ ', '//1//5' and '/1/5' all become '/1/5'. The empty string, '0',
     * a zero segment and any non-digit segment give null.
     *
     * @param string|null $raw
     * @return string|null
     */
    public static function normalise(?string $raw): ?string {
        if ($raw === null) {
            return null;
        }
        $segments = [];
        foreach (explode('/', trim($raw)) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if (!ctype_digit($segment)) {
                return null;
            }
            $id = (int) $segment;
            if ($id <= 0) {
                return null;
            }
            $segments[] = (string) $id;
        }
        return $segments ? '/' . implode('/', $segments) : null;
    }

    /**
     * Pick the first usable candidate.
     *
     * @param array<string, string|int|null> $candidates Ordered name => path or root id.
     * @return array{0: ?string, 1: ?int, 2: string} [path, root, method] where method is
     *         exact, normalised, walked_up, fallback:<name> or unresolved.
     */
    public function resolve(array $candidates): array {
        $first = true;
        foreach ($candidates as $name => $value) {
            $isfirst = $first;
            $first = false;
            if ($value === null || $value === '') {
                continue;
            }
            $raw = (string) $value;
            $path = self::normalise($raw);
            if ($path === null) {
                continue;
            }
            $root = (int) explode('/', ltrim($path, '/'))[0];
            if (!$this->root_is_valid($root)) {
                continue;
            }

            $method = (is_int($value) || $raw === $path) ? 'exact' : 'normalised';

            if ($this->lookups->has_orgs() && $this->org_for_path($path, false) === null) {
                $ancestor = $this->org_for_path($path, true);
                if ($ancestor === null) {
                    continue;
                }
                $path = $ancestor->path;
                $method = 'walked_up';
            }

            if (!$isfirst) {
                $method = 'fallback:' . $name;
            }
            return [$path, $root, $method];
        }
        return [null, null, 'unresolved'];
    }

    /**
     * The organisation at a path, optionally the nearest ancestor that exists.
     *
     * Compares whole paths only (equality), never a prefix, so /1/2 can never
     * be mistaken for /1/20.
     *
     * @param string $path
     * @param bool $walkup Fall back to the parent, grandparent and so on.
     * @return \stdClass|null
     */
    public function org_for_path(string $path, bool $walkup = true): ?\stdClass {
        $path = self::normalise($path);
        if ($path === null) {
            return null;
        }
        $segments = explode('/', ltrim($path, '/'));
        while ($segments) {
            $org = $this->lookups->org_by_path('/' . implode('/', $segments));
            if ($org !== null) {
                return $org;
            }
            if (!$walkup) {
                return null;
            }
            array_pop($segments);
        }
        return null;
    }

    /**
     * The tenant root of a user's current open_path; 0 when there is none.
     *
     * @param int $userid
     * @return int
     */
    public function root_of_user(int $userid): int {
        $path = self::normalise($this->lookups->user_path($userid));
        if ($path === null) {
            return 0;
        }
        return (int) explode('/', ltrim($path, '/'))[0];
    }

    /**
     * Is this a registered tenant root?
     *
     * @param int $root
     * @return bool
     */
    private function root_is_valid(int $root): bool {
        try {
            \local_sentientia_platform\tenant::assert_valid($root);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
