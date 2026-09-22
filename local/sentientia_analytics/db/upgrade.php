<?php
/**
 * Upgrade steps for local_sentientia_analytics.
 *
 * 2026061600 (P1.2) — no schema changes required.
 *   New surfaces (predictive + ROI) are pure-PHP over existing Moodle
 *   tables ({user}, {user_enrolments}, {course_completions},
 *   {logstore_standard_log}, {course}). No new DB tables needed.
 *   The version bump is required to trigger the scheduled-task
 *   registration from db/tasks.php and the feature-flag registration
 *   from db/feature_flags.php.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_analytics_upgrade(int $oldversion): bool {
    // 2026061600 — P1.2 Predictive Analytics + Training ROI.
    // No DB schema changes. Version bump only (tasks + feature flags).
    if ($oldversion < 2026061600) {
        upgrade_plugin_savepoint(true, 2026061600, 'local', 'sentientia_analytics');
    }

    // 2026092201 - the dashboard had no capability layer at all.
    //
    // index.php, drilldown.php and export.php all gated on
    // local/courses:manage, renamed to local/sentientia_courses:manage by
    // ADR-025 and since undefined. has_capability() answers an unknown
    // capability with a debugging() notice and false, so that half of the
    // gate was dead code and effective access was site admins plus whoever
    // held hardcoded role id 9 at a course-category context. The manager role
    // - the dashboard's intended audience - got "nopermission".
    //
    // db/access.php now declares :view, :viewallorgs and :export. Their
    // archetype defaults reach every manager-archetype role when core syncs
    // capabilities; this step additionally covers customers whose admin-tier
    // role carries no archetype. Idempotent.
    if ($oldversion < 2026092201) {
        \local_sentientia_analytics\permission::grant_to_default_roles();
        upgrade_plugin_savepoint(true, 2026092201, 'local', 'sentientia_analytics');
    }

    return true;
}
