<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Review and repair of the role grants that a BizLMS database carries on capabilities of plugins that are
 * no longer on disk (ADR-032, "Capabilities").
 *
 * WHY THIS EXISTS. The retired local/sentientia_org/cli/migrate_all.php copied every role_capabilities row of
 * ten BizLMS capabilities (local/costcenter:*, local/courses:manage and :enrol, local/classroom:manageclassroom,
 * local/users:edit and :bulkstatuschange) to the Sentientia capability that replaced it, keeping role, context
 * and permission. On a restored production database that must not be replayed:
 *
 * - The BizLMS grants sit on manager-archetype roles (core manager, and the BizLMS tenant-admin role 9). Six of
 *   the Sentientia targets also have a manager archetype, so the plugin install already grants them to those
 *   roles. A copy adds nothing.
 * - The other four (local/sentientia_org:manage_multiorganizations, :manage, :manage_ownorganization and
 *   :manage_owndepartments) have no archetype on purpose. ADR-031 reserves creating, editing and deleting
 *   organisations and the multi-organisation scope for site administrators and holders of
 *   local/sentientia_platform:crosstenant. Replaying the BizLMS grants would give the tenant-admin role
 *   cross-tenant organisation delete, edit and visibility.
 * - local/classroom:manageclassroom has no archetype in BizLMS, so on production it exists only as explicit
 *   overrides, and nobody knows which roles hold them. If a role such as trainer held it, those users lose
 *   classroom management when the BizLMS code goes, because local/sentientia_classroom:manage reaches only the
 *   manager archetype.
 *
 * WHAT IT DOES. inventory() lists every role_capabilities row on a capability whose plugin is missing from
 * disk, per role and context, next to the Sentientia equivalent and whether the role already holds it. plan()
 * checks an allow-list the owner signed. The allow-list holds two kinds of decision, and every grant in the
 * inventory must end up in one of them or the run does not finish clean:
 *
 * - grants: one line per grant to carry ({role, context, legacy, target, permission}). apply() gives exactly
 *   those (assign_capability, which never overwrites an existing row, fires capability_assigned and clears the
 *   role cache; it does not mark a context dirty in 5.1). Nothing is revoked.
 * - declined: what the owner reviewed and does NOT carry, each with a reason. Either one role grant
 *   ({role, context, legacy, reason}) or every grant on capabilities of one missing plugin that has no
 *   Sentientia equivalent ({legacy_component, reason}). A component decline never covers one of the ten
 *   capabilities in MAP: those are where a real override hides (local/classroom:manageclassroom is held by
 *   trainers on production only as an override), so each of them is granted, held already, withheld, or
 *   declined by name for that role and context.
 *
 * A line that is both granted and declined is refused. The three NEVER_GRANT capabilities cannot be granted
 * whatever the allow-list says.
 *
 * It is not an importer: role_capabilities is permission configuration, not BizLMS history.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capability_repair {

    /** BizLMS capability => the Sentientia capability that replaced it (the ten the retired script copied). */
    public const MAP = [
        'local/costcenter:manage_multiorganizations' => 'local/sentientia_org:manage_multiorganizations',
        'local/costcenter:view' => 'local/sentientia_org:view',
        'local/costcenter:manage' => 'local/sentientia_org:manage',
        'local/costcenter:manage_ownorganization' => 'local/sentientia_org:manage_ownorganization',
        'local/costcenter:manage_owndepartments' => 'local/sentientia_org:manage_owndepartments',
        'local/courses:manage' => 'local/sentientia_courses:manage',
        'local/courses:enrol' => 'local/sentientia_courses:enrol',
        'local/classroom:manageclassroom' => 'local/sentientia_classroom:manage',
        'local/users:edit' => 'local/sentientia_users:edit',
        'local/users:bulkstatuschange' => 'local/sentientia_users:bulkstatuschange',
    ];

    /**
     * Capabilities this repair never grants, whatever the allow-list says. Organisation create, edit and delete
     * and the multi-organisation scope are site-admin and cross-tenant matters (ADR-031). The cross-tenant
     * capability is granted deliberately, by name, to the platform role Nitin names, in the runbook.
     */
    public const NEVER_GRANT = [
        'local/sentientia_org:manage',
        'local/sentientia_org:manage_multiorganizations',
        'local/sentientia_platform:crosstenant',
    ];

    /** A frankenstyle component name (local_classroom, block_trending_modules). */
    private const COMPONENT_PATTERN = '~^[a-z][a-z0-9]*_[a-z0-9_]+$~';

    /** A capability name (local/classroom:manageclassroom, moodle/role:manage). */
    private const CAPABILITY_PATTERN = '~^[a-z][a-z0-9_]*/[a-z0-9_]+:[a-z0-9_]+$~';

    /** @var array<string, string> */
    private array $map;

    /** @var string[] */
    private array $never;

    /**
     * @param array<string, string>|null $map Legacy capability => Sentientia capability (tests inject one).
     * @param string[]|null $never Capabilities that are never granted.
     */
    public function __construct(?array $map = null, ?array $never = null) {
        $this->map = $map ?? self::MAP;
        $this->never = $never ?? self::NEVER_GRANT;
    }

    /**
     * Read and validate an allow-list file.
     *
     * The file is JSON: version, approved_by, approved_on, basis, grants and declined. grants is a list of
     * {role, context, legacy, target, permission}. role is a shortname, context is "system" or a context id,
     * permission is 1 (allow), -1 (prevent) or -1000 (prohibit). declined is optional and is a list of
     * {role, context, legacy, reason} (one role grant) or {legacy_component, reason} (every grant on a
     * capability of that missing plugin that has no Sentientia equivalent). Other keys (a status, notes on
     * decisions still open) are allowed and are part of the hash.
     *
     * @param string $path
     * @return array{grants: array<int, array>, declines: array<int, array>, hash: string, approved_by: string,
     *         approved_on: string}
     * @throws blocked When the file is missing, malformed or not signed.
     */
    public static function load_allowlist(string $path): array {
        if (!is_readable($path)) {
            throw new blocked('capability_allowlist_unreadable');
        }
        $normalised = str_replace("\r\n", "\n", (string) file_get_contents($path));
        $data = json_decode($normalised, true);
        if (!is_array($data) || array_is_list($data) || !isset($data['grants']) || !is_array($data['grants'])) {
            throw new blocked('capability_allowlist_has_no_grants_list');
        }
        if (trim((string) ($data['approved_by'] ?? '')) === '' || trim((string) ($data['approved_on'] ?? '')) === '') {
            throw new blocked('capability_allowlist_is_not_signed (approved_by and approved_on)');
        }
        $grants = [];
        foreach ($data['grants'] as $i => $grant) {
            foreach (['role', 'context', 'legacy', 'target', 'permission'] as $field) {
                if (!is_array($grant) || !isset($grant[$field]) || !is_scalar($grant[$field])) {
                    throw new blocked('capability_allowlist_grant_invalid:' . $i . ':' . $field);
                }
            }
            if (!in_array((int) $grant['permission'], [CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT], true)) {
                throw new blocked('capability_allowlist_grant_invalid:' . $i . ':permission');
            }
            $grants[] = [
                'role' => (string) $grant['role'], 'context' => (string) $grant['context'],
                'legacy' => (string) $grant['legacy'], 'target' => (string) $grant['target'],
                'permission' => (int) $grant['permission'],
            ];
        }
        return [
            'grants' => $grants, 'declines' => self::load_declines($data['declined'] ?? []),
            'hash' => hash('sha256', $normalised),
            'approved_by' => (string) $data['approved_by'], 'approved_on' => (string) $data['approved_on'],
        ];
    }

    /**
     * Validate the "declined" section of an allow-list.
     *
     * @param mixed $declined The decoded section (absent means none).
     * @return array<int, array> Each {kind: 'component', component, reason} or {kind: 'row', role, context, legacy,
     *         reason}.
     * @throws blocked When an entry is not one of the two shapes, has no reason, or names a malformed target.
     */
    private static function load_declines(mixed $declined): array {
        if (!is_array($declined) || ($declined !== [] && !array_is_list($declined))) {
            throw new blocked('capability_allowlist_declined_is_not_a_list');
        }
        $out = [];
        foreach ($declined as $i => $decline) {
            if (!is_array($decline)) {
                throw new blocked('capability_allowlist_decline_invalid:' . $i . ':entry');
            }
            if (!isset($decline['reason']) || !is_string($decline['reason']) || trim($decline['reason']) === '') {
                throw new blocked('capability_allowlist_decline_invalid:' . $i . ':reason');
            }
            $reason = trim($decline['reason']);
            $iscomponent = array_key_exists('legacy_component', $decline);
            $isrow = array_key_exists('role', $decline) || array_key_exists('context', $decline)
                || array_key_exists('legacy', $decline);
            if ($iscomponent === $isrow) {
                // Neither shape, or both mixed: a whole-plugin decline must not also name one grant.
                throw new blocked('capability_allowlist_decline_invalid:' . $i . ':shape');
            }
            if ($iscomponent) {
                if (!is_string($decline['legacy_component']) || !preg_match(self::COMPONENT_PATTERN, $decline['legacy_component'])) {
                    throw new blocked('capability_allowlist_decline_invalid:' . $i . ':legacy_component');
                }
                $out[] = ['kind' => 'component', 'component' => $decline['legacy_component'], 'reason' => $reason];
                continue;
            }
            foreach (['role', 'context', 'legacy'] as $field) {
                if (!isset($decline[$field]) || !is_scalar($decline[$field]) || trim((string) $decline[$field]) === '') {
                    throw new blocked('capability_allowlist_decline_invalid:' . $i . ':' . $field);
                }
            }
            if (!preg_match(self::CAPABILITY_PATTERN, (string) $decline['legacy'])) {
                throw new blocked('capability_allowlist_decline_invalid:' . $i . ':legacy');
            }
            $out[] = ['kind' => 'row', 'role' => (string) $decline['role'], 'context' => (string) $decline['context'],
                'legacy' => (string) $decline['legacy'], 'reason' => $reason];
        }
        return $out;
    }

    /**
     * Every role grant on a capability of a plugin that is missing from disk.
     *
     * @return array<int, array{roleid: int, role: string, contextid: int, contextlevel: int, legacy: string,
     *         component: string, permission: int, target: ?string, target_exists: bool, held: bool, withheld: bool}>
     *         component is the missing plugin the capability belongs to, target is the Sentientia equivalent (null
     *         when the map has none), held says the role already has a row for it in that context, withheld says
     *         the target is one this repair never grants.
     */
    public function inventory(): array {
        global $DB;
        $missing = [];
        foreach ($DB->get_fieldset_sql('SELECT DISTINCT component FROM {capabilities}') as $component) {
            try {
                $dir = \core_component::get_component_directory((string) $component);
            } catch (\Throwable $e) {
                $dir = null;
            }
            if ($dir === null) {
                $missing[] = (string) $component;
            }
        }
        if (!$missing) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($missing, SQL_PARAMS_NAMED, 'blmcomp');
        $rows = $DB->get_records_sql(
            "SELECT rc.id, rc.roleid, r.shortname, rc.contextid, ctx.contextlevel, rc.capability, rc.permission,
                    c.component
               FROM {role_capabilities} rc
               JOIN {capabilities} c ON c.name = rc.capability
               JOIN {role} r ON r.id = rc.roleid
               JOIN {context} ctx ON ctx.id = rc.contextid
              WHERE c.component {$insql}
           ORDER BY rc.roleid, rc.contextid, rc.capability", $params);

        $out = [];
        foreach ($rows as $row) {
            $target = $this->map[$row->capability] ?? null;
            $held = false;
            $exists = false;
            if ($target !== null) {
                $exists = (bool) \get_capability_info($target, false);
                $held = $DB->record_exists('role_capabilities',
                    ['roleid' => $row->roleid, 'contextid' => $row->contextid, 'capability' => $target]);
            }
            $out[] = [
                'roleid' => (int) $row->roleid, 'role' => (string) $row->shortname, 'contextid' => (int) $row->contextid,
                'contextlevel' => (int) $row->contextlevel, 'legacy' => (string) $row->capability,
                'component' => (string) $row->component,
                'permission' => (int) $row->permission, 'target' => $target, 'target_exists' => $exists, 'held' => $held,
                'withheld' => $target !== null && in_array($target, $this->never, true),
            ];
        }
        return $out;
    }

    /**
     * Check an allow-list against the inventory.
     *
     * @param array<int, array> $grants As returned by load_allowlist().
     * @param array<int, array>|null $inventory Defaults to inventory().
     * @param array<int, array> $declines The "declines" of load_allowlist(): what the owner reviewed and does not carry.
     * @return array{apply: array<int, array>, held: array<int, array>, refused: string[], uncovered: string[],
     *         withheld: string[], unmapped: string[], declined: string[],
     *         declined_by: array<string, array{rows: int, reason: string}>, unused_declines: string[]}
     *         apply are the grants to make; held are approved grants the role already has; refused are the
     *         allow-list lines that were rejected, with the reason; uncovered are inventory rows with a known
     *         target that nobody approved or declined; withheld are rows whose target this repair never grants;
     *         unmapped are legacy capabilities with grants, no known equivalent and no component decline;
     *         declined are the rows the owner declined (they count as decided), declined_by counts them per
     *         decline with its reason, and unused_declines are decline lines that matched no row.
     */
    public function plan(array $grants, ?array $inventory = null, array $declines = []): array {
        global $DB;
        $inventory ??= $this->inventory();
        $declines = array_values($declines);
        $plan = ['apply' => [], 'held' => [], 'refused' => [], 'uncovered' => [], 'withheld' => [], 'unmapped' => [],
            'declined' => [], 'declined_by' => [], 'unused_declines' => []];

        $index = [];
        foreach ($inventory as $row) {
            $index[$row['roleid'] . '|' . $row['contextid'] . '|' . $row['legacy']] = $row;
        }

        // Declines. A row decline names one role grant; a component decline covers the grants on capabilities of one
        // missing plugin that have no Sentientia equivalent, and never one of the ten in MAP (see the class comment).
        $rowdeclines = [];
        $componentdeclines = [];
        $labels = [];
        foreach ($declines as $n => $decline) {
            $labels[$n] = self::decline_label($n, $decline);
            if ($decline['kind'] === 'component') {
                $componentdeclines[$decline['component']] ??= $n;
                continue;
            }
            $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $decline['role']], IGNORE_MISSING);
            $contextid = $this->context_id($decline['context']);
            if ($roleid && $contextid !== null) {
                $rowdeclines[$roleid . '|' . $contextid . '|' . $decline['legacy']] ??= $n;
            }
        }
        $used = [];
        $conflicts = [];

        $approved = [];
        foreach ($grants as $n => $grant) {
            $label = 'grant ' . ($n + 1) . ' (' . $grant['role'] . ' ' . $grant['legacy'] . ')';
            if (in_array($grant['target'], $this->never, true)) {
                $plan['refused'][] = "{$label}: target_is_never_granted:{$grant['target']}";
                continue;
            }
            if (($this->map[$grant['legacy']] ?? null) !== $grant['target']) {
                $plan['refused'][] = "{$label}: target_is_not_the_equivalent_of_the_legacy_capability";
                continue;
            }
            $roleid = (int) $DB->get_field('role', 'id', ['shortname' => $grant['role']], IGNORE_MISSING);
            if (!$roleid) {
                $plan['refused'][] = "{$label}: role_not_found";
                continue;
            }
            $contextid = $this->context_id($grant['context']);
            if ($contextid === null) {
                $plan['refused'][] = "{$label}: context_not_found";
                continue;
            }
            $key = $roleid . '|' . $contextid . '|' . $grant['legacy'];
            if (isset($rowdeclines[$key])) {
                // Both a grant and a decline of the same role grant: neither is honoured and the run is refused.
                $declinenumber = $rowdeclines[$key];
                $plan['refused'][] = "{$label}: is_also_declined_by:{$labels[$declinenumber]}";
                $plan['refused'][] = "{$labels[$declinenumber]}: is_also_granted_by:grant " . ($n + 1);
                $conflicts[$key] = true;
                $used[$declinenumber] = true;
                continue;
            }
            $row = $index[$key] ?? null;
            if ($row === null || $row['permission'] !== $grant['permission']) {
                $plan['refused'][] = "{$label}: the role does not hold that legacy grant with that permission there";
                continue;
            }
            if (!\get_capability_info($grant['target'], false)) {
                $plan['refused'][] = "{$label}: target_capability_is_not_installed";
                continue;
            }
            $approved[$key] = true;
            $entry = $grant + ['roleid' => $roleid, 'contextid' => $contextid];
            if ($row['held']) {
                $plan['held'][] = $entry;
            } else {
                $plan['apply'][] = $entry;
            }
        }

        foreach ($inventory as $row) {
            $key = $row['roleid'] . '|' . $row['contextid'] . '|' . $row['legacy'];
            if (isset($approved[$key])) {
                continue;
            }
            $where = "{$row['role']} in context {$row['contextid']}: {$row['legacy']}";
            $declinenumber = isset($conflicts[$key]) ? null : ($rowdeclines[$key] ?? null);
            if ($declinenumber === null && $row['target'] === null) {
                $declinenumber = $componentdeclines[$row['component'] ?? ''] ?? null;
            }
            if ($declinenumber !== null) {
                $label = $labels[$declinenumber];
                $plan['declined'][] = $where;
                $plan['declined_by'][$label] ??= ['rows' => 0, 'reason' => $declines[$declinenumber]['reason']];
                $plan['declined_by'][$label]['rows']++;
                $used[$declinenumber] = true;
                continue;
            }
            if ($row['target'] === null) {
                $plan['unmapped'][] = $where;
            } else if ($row['withheld']) {
                $plan['withheld'][] = $where . " -> {$row['target']}";
            } else if (!$row['held']) {
                $plan['uncovered'][] = $where . " -> {$row['target']}";
            }
        }
        foreach ($labels as $n => $label) {
            if (!isset($used[$n])) {
                $plan['unused_declines'][] = "{$label}: matches no grant in the inventory";
            }
        }
        return $plan;
    }

    /**
     * Grants that still have no decision: neither approved, held already, withheld nor declined.
     *
     * @param array $plan As returned by plan().
     * @return int
     */
    public static function open_count(array $plan): int {
        return count($plan['uncovered']) + count($plan['unmapped']);
    }

    /**
     * The exit code of a review: 1 when an allow-list line was refused, 2 when grants remain undecided, else 0.
     * (The CLI's 3 is a guard refusal and comes from elsewhere.)
     *
     * @param array $plan As returned by plan().
     * @return int
     */
    public static function exit_code(array $plan): int {
        if ($plan['refused']) {
            return 1;
        }
        return self::open_count($plan) > 0 ? 2 : 0;
    }

    /**
     * @param int $n Zero-based position in the declined section.
     * @param array $decline
     * @return string
     */
    private static function decline_label(int $n, array $decline): string {
        $what = $decline['kind'] === 'component' ? $decline['component'] : $decline['role'] . ' ' . $decline['legacy'];
        return 'decline ' . ($n + 1) . ' (' . $what . ')';
    }

    /**
     * Give the approved grants. Never overwrites a row that exists and never revokes. assign_capability() fires
     * capability_assigned and clears the role cache; in 5.1 it does not mark the context dirty.
     *
     * @param array<int, array> $entries The plan's apply list.
     * @return int Grants made.
     * @throws blocked When an entry names a capability this repair never grants (a plan built by hand).
     */
    public function apply(array $entries): int {
        $made = 0;
        foreach ($entries as $entry) {
            if (in_array($entry['target'], $this->never, true)) {
                throw new blocked('capability_is_never_granted:' . $entry['target']);
            }
            \assign_capability($entry['target'], $entry['permission'], $entry['roleid'], $entry['contextid'], false);
            $made++;
        }
        return $made;
    }

    /**
     * @param string $context "system" or a context id.
     * @return int|null
     */
    private function context_id(string $context): ?int {
        global $DB;
        if ($context === 'system') {
            return (int) \context_system::instance()->id;
        }
        if (!ctype_digit($context)) {
            return null;
        }
        return $DB->record_exists('context', ['id' => (int) $context]) ? (int) $context : null;
    }
}
