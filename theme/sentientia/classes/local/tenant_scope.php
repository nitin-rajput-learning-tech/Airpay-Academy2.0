<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant scoping for the admin dashboard's widget queries.
 *
 * WHY THIS IS A CLASS AND NOT A CLOSURE
 * -------------------------------------
 * This logic used to be a closure declared inline in
 * theme/sentientia/layout/dashboard.php, a 1,185-line layout file. Roughly
 * thirty widget queries depend on it: the total-users tile, the active-users
 * tile, the course counts, the completion rates, the activity feed. If it is
 * wrong, every number an L&D admin sees is wrong, and nothing errors.
 *
 * A closure in a layout file cannot be unit tested. The logic it held was in
 * fact correct -- it was already '/'-terminated with an exact-root companion,
 * unlike the fourteen sites the 2026-09-22 path-boundary sweep had to fix --
 * but "correct and untested" is a state that decays. Extracting it costs
 * nothing and makes the boundary assertable.
 *
 * ONE REAL DEFECT CAME OUT OF THE EXTRACTION
 * ------------------------------------------
 * The closure decided its scope like this:
 *
 *     if ($isldadmin && !$issiteadmin && !empty($USER->open_path)) {
 *         $parts  = explode('/', $USER->open_path);
 *         $toporg = '/' . ($parts[1] ?? '');
 *         $scopedtenant = ($toporg !== '/');
 *     }
 *     // $scopedtenant false  =>  fragment is ''  =>  NO FILTER AT ALL
 *
 * So an L&D admin whose open_path was null, empty, or '/' fell through to the
 * unscoped branch and saw the entire site: every tenant's user counts, course
 * counts and completion rates, presented as their own. Failing open is the
 * wrong direction for a tenant boundary. This class fails CLOSED: an
 * unresolvable path yields a filter that matches nothing, and is_unresolved()
 * lets the caller say so rather than quietly rendering zeros.
 *
 * (On the current production import no user is exposed by this: the five
 * null-path accounts are guest, the site admin, and three role-less test
 * accounts. It was one role assignment away from mattering.)
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tenant_scope {

    /** @var bool True when this viewer sees every tenant (site admin). */
    private bool $unrestricted;

    /** @var string Tenant root such as '/1', or '' when none could be resolved. */
    private string $root;

    /**
     * @param bool $unrestricted True for a viewer who may see every tenant.
     * @param string $root Tenant root path, or '' when unresolvable.
     */
    private function __construct(bool $unrestricted, string $root) {
        $this->unrestricted = $unrestricted;
        $this->root = $root;
    }

    /**
     * Scope for a given user.
     *
     * @param \stdClass|null $user User record, or null for no user.
     * @param bool $unrestricted True when this viewer may see every tenant
     *                           (site admin). Passed in rather than derived so
     *                           the caller keeps one is_siteadmin() call and
     *                           the class stays testable without a session.
     * @return self
     */
    public static function for_user(?\stdClass $user, bool $unrestricted): self {
        if ($unrestricted) {
            return new self(true, '');
        }

        $openpath = (string) ($user->open_path ?? '');
        $parts = explode('/', trim($openpath, '/'));
        $first = $parts[0] ?? '';

        // Only a numeric tenant root counts. '', '/', '/abc' and null all
        // arrive here and all mean "no scope could be established".
        if ($first === '' || !ctype_digit($first)) {
            return new self(false, '');
        }

        return new self(false, '/' . (int) $first);
    }

    /**
     * Whether this viewer sees every tenant.
     *
     * @return bool
     */
    public function is_unrestricted(): bool {
        return $this->unrestricted;
    }

    /**
     * Whether no tenant could be established for a restricted viewer.
     *
     * When true, every fragment() matches nothing, so the dashboard renders
     * zeros rather than another tenant's numbers. A caller that can show a
     * message should use this to explain the zeros.
     *
     * @return bool
     */
    public function is_unresolved(): bool {
        return !$this->unrestricted && $this->root === '';
    }

    /**
     * The tenant root, e.g. '/1'. Empty for an unrestricted or unresolved viewer.
     *
     * @return string
     */
    public function root(): string {
        return $this->root;
    }

    /**
     * An SQL fragment to append to a WHERE clause, with its parameters.
     *
     * Returns [' AND (...)', params], already prefixed with ' AND ' so the
     * call sites read as they did with the closure. Parameter names are
     * derived from $tag so several fragments can share one query without
     * colliding -- the dashboard uses tu / ju / tc / jc.
     *
     * @param string $alias Table alias, or '' for an unaliased column.
     * @param string $tag Short unique prefix for this fragment's parameters.
     * @param string $column Path column name.
     * @return array{0: string, 1: array} [sql, params]
     */
    public function fragment(string $alias, string $tag, string $column = 'open_path'): array {
        if ($this->unrestricted) {
            return ['', []];
        }

        $col = ($alias === '') ? $column : $alias . '.' . $column;

        if ($this->root === '') {
            // Fail closed. Matching nothing is the safe reading of "we do not
            // know which tenant this person belongs to"; the alternative the
            // closure chose was matching everything.
            return [" AND 1 = 0", []];
        }

        // Delegated so there is one implementation of the path boundary in the
        // product. '/1' must not match '/177', and must still match '/1'.
        [$sql, $params] = \local_sentientia_platform\tenant::path_descendant_filter(
            $this->root, $alias, $column, $tag);

        return [' AND ' . $sql, $params];
    }
}
