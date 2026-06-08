<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Employee user-type provider — Airpay-customer staff (internal staff
 * of the customer-zero tenant, plus ZEEA subtree).
 *
 * ADR-017 Phase 2 (C1.2).
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform\user_type;

use local_sentientia_platform\user_type_provider;

defined('MOODLE_INTERNAL') || die();

class employee_provider implements user_type_provider {

    public static function type_id(): string {
        return 'employee';
    }

    public static function label(): string {
        return get_string('usertype_employee_label', 'local_sentientia_platform');
    }

    public function profile_context(\stdClass $user): array {
        global $DB;

        $profile = $DB->get_record('local_airpay_employee_profile',
            ['userid' => $user->id]) ?: new \stdClass();

        $manager_name = '';
        if (!empty($profile->manager_userid)) {
            $mgr = $DB->get_record('user', ['id' => (int) $profile->manager_userid],
                'firstname, lastname', IGNORE_MISSING);
            if ($mgr) {
                $manager_name = fullname($mgr);
            }
        }

        return [
            // Canonical keys (all providers must return these)
            'user_id'         => $user->id,
            'full_name'       => fullname($user),
            'email'           => $user->email,
            'user_type_label' => self::label(),
            'joined_date_str' => userdate($user->timecreated,
                get_string('strftimedate', 'langconfig')),
            // Type-specific extras
            'extras' => [
                'department'    => $profile->department    ?? '',
                'job_title'     => $profile->job_title     ?? '',
                'employee_id'   => $profile->employee_id   ?? '',
                'manager_name'  => $manager_name,
                'hire_date_str' => !empty($profile->hire_date)
                    ? userdate((int) $profile->hire_date,
                        get_string('strftimedate', 'langconfig'))
                    : '',
            ],
        ];
    }

    public function dashboard_widgets(\stdClass $user): array {
        global $DB;
        $widgets = ['continue_learning', 'mandatory_compliance'];

        // Manager-capability check (Q7 ruling: manager is an EMPLOYEE
        // who has direct reports, NOT a separate user_type). If this
        // user is anyone's open_supervisorid, surface the team widget.
        if ($DB->record_exists_select('user',
                'open_supervisorid = :uid AND deleted = 0',
                ['uid' => $user->id])) {
            $widgets[] = 'team_certifications';
        }

        return $widgets;
    }

    public function sidebar_items(\stdClass $user): array {
        return ['home', 'catalog', 'my_courses', 'compliance', 'profile'];
    }

    public function onboarding_steps(\stdClass $user): array {
        return ['welcome', 'manager_intro', 'compliance_walkthrough', 'finish'];
    }

    public function required_consents(): array {
        // Employee context: workplace learning under GDPR Art. 6(1)(f)
        // legitimate interest. No explicit opt-in needed for these.
        return [
            'marketing'          => false,
            'leaderboard'        => false,
            'manager_visibility' => false,
            'analytics_export'   => false,
        ];
    }

    public function feature_supported(string $featurekey): bool {
        $supported = ['leaderboard', 'compliance_report', 'team_view',
                      'mandatory_training', 'manage_courses', 'manage_users'];
        return in_array($featurekey, $supported, true);
    }
}
