<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Provisioner for the dedicated "Sentientia Author" system-context role.
 *
 * T-01 persona-caps gap (recurring bug class — fresh-install vs upgrade
 * parity, UAT persona walk 2026-09-07). The `sentientiaauthor` role and its
 * author/SME capabilities were historically seeded ONLY from db/upgrade.php
 * (steps 2026061701 here + 2026080400 in local_sentientia_aiquiz) and the UAT
 * provisioning CLI. Moodle runs a plugin's upgrade.php only when an EXISTING
 * install is upgraded — never on a fresh install — so a brand-new Sentientia
 * customer would come up with NO author role at all, and their Course Authors
 * would be indistinguishable from learners (exactly the symptom the UAT walk
 * hit, masked on UAT only because the CLI seeder had run).
 *
 * This class is the single idempotent seeder shared by BOTH the fresh-install
 * path (db/install.php) and the upgrade back-fill (a versioned db/upgrade.php
 * step), so every deployment — existing or new — provisions the same role with
 * the same caps.
 *
 * Scope discipline (author must NOT become an admin): the role is deliberately
 * archetype-less and assignable at CONTEXT_SYSTEM only. It grants EXACTLY the
 * AI-assisted authoring caps and nothing else — no user-management, no
 * tenant-admin, and no course-management (local/sentientia_courses:manage /
 * :view) caps. Course creation for authors is provided per-deployment by a
 * category-level coursecreator assignment (moodle/course:create), not by this
 * role.
 *
 * @package    local_sentientia_authoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_authoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates and reconciles the Sentientia Author role. Idempotent.
 */
class author_role {

    /** @var string The role shortname. */
    public const SHORTNAME = 'sentientiaauthor';

    /**
     * The exact capability allowlist for the Sentientia Author role.
     *
     * All granted at CONTEXT_SYSTEM. Caps whose owning plugin is not installed
     * (not registered in {capabilities}) are skipped by ensure(), so no orphan
     * role_capability rows are created for an unlicensed plugin — that plugin
     * re-runs ensure() from its own install once its caps are registered.
     *
     * @return string[]
     */
    public static function caps(): array {
        return [
            // GenAI Authoring Studio (this plugin).
            'local/sentientia_authoring:generate',
            'local/sentientia_authoring:review',
            'local/sentientia_authoring:managetemplates',
            // Skills AI / Skills Intelligence.
            'local/sentientia_skillsai:extract',
            'local/sentientia_skillsai:review',
            // AI Quiz generation.
            'local/sentientia_aiquiz:generate',
            'local/sentientia_aiquiz:review',
        ];
    }

    /**
     * Ensure the Sentientia Author role exists and holds its author caps.
     *
     * Idempotent and safe to call repeatedly and from multiple plugins:
     *  - creates the role only when the shortname is free (existing role kept,
     *    including any admin-edited display name);
     *  - always pins its assignable context to CONTEXT_SYSTEM only;
     *  - grants each cap that is currently registered, skipping the rest — so a
     *    plugin that installs AFTER this call (or isn't licensed) leaves no
     *    orphan rows and fills its own caps in when it runs ensure() itself.
     *
     * assign_capability(overwrite=true) keeps the author caps at ALLOW — this
     * role IS the author's definition, so we own its permissions outright.
     *
     * @return int The role id.
     */
    public static function ensure(): int {
        global $DB;

        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => self::SHORTNAME]);
        if (!$roleid) {
            $roleid = create_role(
                'Sentientia Author',
                self::SHORTNAME,
                'Content author / SME. Grants the GenAI Authoring Studio, AI Quiz '
                    . 'and Skills Intelligence capabilities at system context, '
                    . 'without broader teacher/manager permissions. Assign at the '
                    . 'System level to staff who create learning content.',
                '' // Archetype-less — inherits no broad teacher/manager caps.
            );
        }

        // Assignable at the System level only — the AI authoring tools gate at
        // CONTEXT_SYSTEM.
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        $syscontext = \context_system::instance();
        foreach (self::caps() as $cap) {
            if ($DB->record_exists('capabilities', ['name' => $cap])) {
                assign_capability($cap, CAP_ALLOW, $roleid, $syscontext->id, true);
            }
        }
        $syscontext->mark_dirty();

        return $roleid;
    }
}
