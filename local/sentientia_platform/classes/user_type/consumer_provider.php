<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Consumer user-type provider — self-directed public learner with no
 * employment context. Public-tenant signup origin.
 *
 * ADR-017 Phase 2 (C1.2). Closes F-005 (N/A profile fields), F-006
 * (sidebar wrong shape), and the consent arm of F-002 (DPDP basis).
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_platform\user_type;

use local_sentientia_platform\user_type_provider;

defined('MOODLE_INTERNAL') || die();

class consumer_provider implements user_type_provider {

    public static function type_id(): string {
        return 'consumer';
    }

    public static function label(): string {
        return get_string('usertype_consumer_label', 'local_sentientia_platform');
    }

    public function profile_context(\stdClass $user): array {
        global $DB;

        $profile = $DB->get_record('local_sentientia_consumer_profile',
            ['userid' => $user->id]) ?: new \stdClass();

        // Count enrolments (proxy for "courses completed" pending a
        // dedicated completions query).
        $courses_enrolled = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT e.courseid)
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE ue.userid = :userid AND ue.status = 0",
            ['userid' => $user->id]);

        return [
            // Canonical keys (NO employee fields — consumers don't have them)
            'user_id'         => $user->id,
            'full_name'       => fullname($user),
            'email'           => $user->email,
            'user_type_label' => self::label(),
            'joined_date_str' => userdate($user->timecreated,
                get_string('strftimedate', 'langconfig')),
            // Type-specific extras
            'extras' => [
                'interests_csv'      => $profile->interests_json ?? '',
                'weekly_goal_hours'  => (int) ($profile->weekly_goal ?? 0),
                'referral_source'    => $profile->referral_source ?? '',
                'courses_enrolled'   => $courses_enrolled,
                'consent_marketing'  => (bool) ($profile->consent_marketing ?? 0),
                'consent_leaderboard' => (bool) ($profile->consent_leaderboard ?? 0),
            ],
        ];
    }

    public function dashboard_widgets(\stdClass $user): array {
        return ['continue_learning', 'interest_recommendations',
                'paid_courses_history'];
    }

    public function sidebar_items(\stdClass $user): array {
        // No team, no compliance, no manage-* — consumer surfaces only.
        return ['home', 'catalog', 'my_courses', 'profile'];
    }

    public function onboarding_steps(\stdClass $user): array {
        return ['welcome', 'interests', 'weekly_goal', 'consent_capture', 'finish'];
    }

    public function required_consents(): array {
        // Consumer = no employment context. GDPR Art. 6(1)(a) + DPDP §7(a)
        // require explicit consent for every personal-data use.
        return [
            'marketing'          => true,
            'leaderboard'        => true,
            'manager_visibility' => false,  // N/A — no manager
            'analytics_export'   => true,
        ];
    }

    public function feature_supported(string $featurekey): bool {
        // Consumers see learning surfaces only. NO manage_*, compliance,
        // team_view. Leaderboard supported but consent-gated (handled
        // by required_consents()).
        $supported = ['leaderboard', 'self_enrol', 'public_catalog',
                      'payment_history', 'certificates'];
        return in_array($featurekey, $supported, true);
    }
}
