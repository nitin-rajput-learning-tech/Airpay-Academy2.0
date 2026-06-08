<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Operator user-type provider — platform operators (Site Admins, Airpay
 * platform team, Sentientia-side support engineers, DPDP DPO).
 *
 * ADR-017 Phase 2 (C1.2). Per Q3 ruling 2026-05-28 — explicitly modelled
 * rather than defaulted to employee, so operator-specific fields
 * (on-call rotation, support phone, DPDP role) have a home.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform\user_type;

use local_sentientia_platform\user_type_provider;

defined('MOODLE_INTERNAL') || die();

class operator_provider implements user_type_provider {

    public static function type_id(): string {
        return 'operator';
    }

    public static function label(): string {
        return get_string('usertype_operator_label', 'local_sentientia_platform');
    }

    public function profile_context(\stdClass $user): array {
        global $DB;

        $profile = $DB->get_record('local_sentientia_operator_profile',
            ['userid' => $user->id]) ?: new \stdClass();

        $oncall_for_name = '';
        if (!empty($profile->oncall_for_customer_id)
            && class_exists('\\local_sentientia_platform\\customer')) {
            try {
                $brand = \local_sentientia_platform\customer::branding(
                    (int) $profile->oncall_for_customer_id);
                $oncall_for_name = $brand['name'] ?? '';
            } catch (\Throwable $e) {
                // Unknown customer — leave blank.
            }
        }

        return [
            'user_id'         => $user->id,
            'full_name'       => fullname($user),
            'email'           => $user->email,
            'user_type_label' => self::label(),
            'joined_date_str' => userdate($user->timecreated,
                get_string('strftimedate', 'langconfig')),
            'extras' => [
                'operator_role'   => $profile->operator_role ?? 'siteadmin',
                'contact_phone'   => $profile->contact_phone ?? '',
                'oncall_for'      => $oncall_for_name,
            ],
        ];
    }

    public function dashboard_widgets(\stdClass $user): array {
        // Operators see the system-health widget no other user-type gets.
        // Plus the standard learning widgets since operators are also
        // Airpay employees in practice (academy@airpay.co.in IS staff).
        return ['admin_system_health', 'continue_learning'];
    }

    public function sidebar_items(\stdClass $user): array {
        // Full admin surface visible. role_detector's is_siteadmin
        // gate still applies — this is the user_type axis only.
        return ['home', 'switchboard', 'manage_users', 'manage_courses',
                'compliance', 'team', 'profile'];
    }

    public function onboarding_steps(\stdClass $user): array {
        // Operators are bootstrapped manually — minimal flow.
        return ['welcome', 'finish'];
    }

    public function required_consents(): array {
        // Operators are platform staff under their employer's
        // legitimate-interest basis — same as employee.
        return [
            'marketing'          => false,
            'leaderboard'        => false,
            'manager_visibility' => false,
            'analytics_export'   => false,
        ];
    }

    public function feature_supported(string $featurekey): bool {
        // Operators support EVERYTHING (capability checks gate the
        // actual surfaces; this method is the user_type-eligibility
        // check, not the role check).
        return true;
    }
}
