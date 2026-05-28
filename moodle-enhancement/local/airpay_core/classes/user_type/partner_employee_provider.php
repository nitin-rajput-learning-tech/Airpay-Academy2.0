<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Partner-employee user-type provider — B2B partner-organisation staff
 * (future Sentientia LMS customers' employees, e.g. BankCo HR staff
 * when BankCo onboards as a customer).
 *
 * ADR-017 Phase 2 (C1.2). Per Q2 ruling 2026-05-28 — explicitly modelled
 * as a third type rather than reusing `employee`, because their consent
 * regime + profile shape differ (separate customer_id, separate HRMS
 * sync source, separate department taxonomy).
 *
 * @package    local_airpay_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_core\user_type;

use local_airpay_core\user_type_provider;

defined('MOODLE_INTERNAL') || die();

class partner_employee_provider implements user_type_provider {

    public static function type_id(): string {
        return 'partner_employee';
    }

    public static function label(): string {
        return get_string('usertype_partner_employee_label', 'local_airpay_core');
    }

    public function profile_context(\stdClass $user): array {
        global $DB;

        $profile = $DB->get_record('local_airpay_partner_employee_profile',
            ['userid' => $user->id]) ?: new \stdClass();

        // Resolve customer display name via the airpay_core customer registry.
        $customer_name = '';
        if (!empty($profile->customer_id)
            && class_exists('\\local_airpay_core\\customer')) {
            try {
                $brand = \local_airpay_core\customer::branding(
                    (int) $profile->customer_id);
                $customer_name = $brand['name'] ?? '';
            } catch (\Throwable $e) {
                // Customer not registered — leave blank, don't crash profile.
            }
        }

        $manager_name = '';
        if (!empty($profile->partner_manager_userid)) {
            $mgr = $DB->get_record('user',
                ['id' => (int) $profile->partner_manager_userid],
                'firstname, lastname', IGNORE_MISSING);
            if ($mgr) {
                $manager_name = fullname($mgr);
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
                'customer_name'        => $customer_name,
                'customer_id'          => (int) ($profile->customer_id ?? 0),
                'partner_department'   => $profile->partner_department ?? '',
                'partner_job_title'    => $profile->partner_job_title ?? '',
                'partner_employee_id'  => $profile->partner_employee_id ?? '',
                'partner_manager_name' => $manager_name,
                'partner_hire_date_str' => !empty($profile->partner_hire_date)
                    ? userdate((int) $profile->partner_hire_date,
                        get_string('strftimedate', 'langconfig'))
                    : '',
            ],
        ];
    }

    public function dashboard_widgets(\stdClass $user): array {
        global $DB;
        $widgets = ['continue_learning', 'mandatory_compliance'];

        // Same manager-capability check as employee_provider — partner
        // managers see their team's certification stats too.
        if ($DB->record_exists_select('user',
                'open_supervisorid = :uid AND deleted = 0',
                ['uid' => $user->id])) {
            $widgets[] = 'partner_team_compliance';
        }

        return $widgets;
    }

    public function sidebar_items(\stdClass $user): array {
        // Like employee, but with partner-scoped surfaces. NO
        // manage_users (partner staff manage only within their own
        // customer subtree, gated by capabilities at the call-site).
        return ['home', 'catalog', 'my_courses', 'compliance', 'profile'];
    }

    public function onboarding_steps(\stdClass $user): array {
        // Partner onboarding skips Airpay-specific compliance walkthrough.
        // The partner-org's own compliance materials surface via the
        // mandatory_compliance widget.
        return ['welcome', 'manager_intro', 'finish'];
    }

    public function required_consents(): array {
        // Same as employee — workplace context, legitimate-interest basis.
        // The partner-org is the data controller; we're a data processor.
        return [
            'marketing'          => false,
            'leaderboard'        => false,
            'manager_visibility' => false,
            'analytics_export'   => false,
        ];
    }

    public function feature_supported(string $featurekey): bool {
        // Same surface as employee, EXCEPT manage_users (per ADR-017 —
        // partner staff don't see the global manage surface).
        $supported = ['leaderboard', 'compliance_report', 'team_view',
                      'mandatory_training'];
        return in_array($featurekey, $supported, true);
    }
}
