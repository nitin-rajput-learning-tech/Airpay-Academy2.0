<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Polymorphic user-type provider interface.
 *
 * ADR-017 Phase 2 (C1.2). Each user_type — employee, consumer,
 * partner_employee, operator — has one provider class implementing
 * this interface. The factory (`user_type_factory::for_user`) reads
 * `local_sentientia_user_type.user_type` for a given userid and returns
 * the matching provider.
 *
 * Call-sites consume the provider's methods rather than branching on
 * type literals. New types add a new provider; no code changes
 * outside the provider's own file.
 *
 * Two-axis composition (Q5 ruling 2026-05-28): this interface knows
 * NOTHING about Moodle roles. The `role_detector` (existing class)
 * handles the role axis. Call-sites compose:
 *
 *   $type = user_type_factory::for_user($userid);
 *   $role = \theme_airpayux\role_detector::detect();
 *   if ($role['isldadmin'] && $type->feature_supported('manage_courses')) {
 *       // render admin shortcut
 *   }
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

interface user_type_provider {

    /**
     * Stable identifier for this user-type.
     * Matches a value of `local_sentientia_user_type.user_type`.
     *
     * @return string One of: employee | consumer | partner_employee | operator
     */
    public static function type_id(): string;

    /**
     * Human-readable label for this user-type. Translatable.
     * Used by the profile-page badge (per ADR-017 Q4 ruling).
     *
     * @return string Lang-aware label, e.g. 'Employee', 'Learner', 'Partner staff', 'Operator'.
     */
    public static function label(): string;

    /**
     * Build the Mustache context for `/user/profile.php`. Each provider
     * returns the *union* of profile fields they own, plus any keys
     * the canonical profile template expects (rendered as empty
     * strings if not applicable).
     *
     * Canonical keys ALL providers return (template depends on these):
     *   user_id, full_name, email, avatar_url, user_type_label,
     *   joined_date_str
     *
     * Type-specific keys are namespaced under `extras`:
     *   employee: department, job_title, manager_name, employee_id
     *   consumer: interests, weekly_goal, courses_completed
     *   partner_employee: customer_name, partner_department, partner_role
     *   operator: operator_role, oncall_for
     *
     * @param \stdClass $user The mdl_user record
     * @return array Mustache-ready data
     */
    public function profile_context(\stdClass $user): array;

    /**
     * Dashboard widget identifiers in render order. The dashboard
     * layout iterates this array and includes each widget partial.
     *
     * Recognised widget keys:
     *   - continue_learning            (all types, scoped per user)
     *   - mandatory_compliance         (employee only)
     *   - team_certifications          (employee with reports — see Q7)
     *   - interest_recommendations     (consumer)
     *   - paid_courses_history         (consumer)
     *   - partner_team_compliance      (partner_employee with reports)
     *   - admin_system_health          (operator)
     *
     * @param \stdClass $user
     * @return array<int, string> Widget keys in display order
     */
    public function dashboard_widgets(\stdClass $user): array;

    /**
     * Sidebar nav-item keys this user-type should see.
     *
     * Recognised keys (subset of `theme_airpayux/sidebar_navigation` items):
     *   home, catalog, my_courses, profile, leaderboard,
     *   compliance, team, manage_users, manage_courses, switchboard
     *
     * @param \stdClass $user
     * @return array<int, string>
     */
    public function sidebar_items(\stdClass $user): array;

    /**
     * Onboarding flow step keys this user-type sees on first login.
     * The onboarding controller drives a wizard through these steps
     * in order.
     *
     * Recognised step keys:
     *   welcome, interests, weekly_goal, manager_intro,
     *   compliance_walkthrough, consent_capture, finish
     *
     * @param \stdClass $user
     * @return array<int, string>
     */
    public function onboarding_steps(\stdClass $user): array;

    /**
     * Consent surfaces required for this user-type per GDPR Art. 6
     * and India DPDP Act 2023.
     *
     * Returns an associative array: consent_key => required_at_signup_bool
     * - `marketing`        — true if explicit opt-in required
     * - `leaderboard`      — true if explicit opt-in required (consumers)
     * - `manager_visibility` — true if explicit notice required (employees)
     * - `analytics_export` — true if explicit opt-in required
     *
     * Employees default to (false, false, false, false) — workplace
     * context legitimate-interest under GDPR Art. 6(1)(f) covers
     * all these. Consumers default to (true, true, false, true) —
     * no employment context, every personal-data use needs consent.
     *
     * @return array<string, bool>
     */
    public function required_consents(): array;

    /**
     * Feature gate — does this user-type have access to a given feature?
     * Composes with `feature_flags::is_enabled()` at call-sites
     * (this method only models the user-type's *eligibility*; the flag
     * controls deployment-state).
     *
     * @param string $featurekey e.g. 'leaderboard', 'manage_users', 'compliance_report'
     * @return bool
     */
    public function feature_supported(string $featurekey): bool;
}
