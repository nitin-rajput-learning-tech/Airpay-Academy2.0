<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\user_type;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\user_type_provider;
use local_sentientia_users\external_trainer\manager;

/**
 * The fifth ADR-017 user type, `external_trainer` (ADR-036 D9, amends ADR-017; session 5).
 *
 * A person from outside the organisation who delivers classroom training. No catalogue, learning,
 * leaderboard, compliance or analytics surfaces: a dashboard and sidebar with "Trainings I deliver" and
 * the profile, and a DPDP notice for non-employees at first login (consent_capture).
 *
 * This class lives in local_sentientia_users, next to the profile table and the invite flow it reads.
 * It is NOT yet listed by local_sentientia_platform\user_type_factory (instantiate() and all_types()):
 * that is the platform part of session 5, specified separately, because the factory throws on an
 * unknown type and this session does not change the platform plugin. Until then an external trainer
 * has no local_sentientia_user_type row and resolves to the factory's default; the restrictions that
 * matter for security (role, own classrooms, names-only rosters, expiry) do not depend on the type row.
 *
 * Widget and sidebar keys: `trainings_i_deliver` is new. The dashboard layout ignores keys it does not
 * know (theme layout/dashboard.php keeps a fixed list), so returning it is safe before the theme has a
 * widget for it.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_trainer_provider implements user_type_provider {

    public static function type_id(): string {
        return manager::TYPE;
    }

    public static function label(): string {
        return get_string('usertype_external_trainer_label', 'local_sentientia_users');
    }

    public function profile_context(\stdClass $user): array {
        $profile = manager::get_profile((int) $user->id) ?: new \stdClass();
        $status = (string) ($profile->status ?? '');
        return [
            'user_id'         => $user->id,
            'full_name'       => fullname($user),
            'email'           => $user->email,
            'user_type_label' => self::label(),
            'joined_date_str' => userdate($user->timecreated, get_string('strftimedate', 'langconfig')),
            'extras' => [
                'organisation'    => (string) ($profile->organisation ?? ''),
                'access_ends_str' => !empty($profile->expires_at)
                    ? userdate((int) $profile->expires_at, get_string('strftimedate', 'langconfig')) : '',
                'status_label'    => $status !== ''
                    ? get_string('exttrainer_status_' . $status, 'local_sentientia_users') : '',
            ],
        ];
    }

    public function dashboard_widgets(\stdClass $user): array {
        return ['trainings_i_deliver'];
    }

    public function sidebar_items(\stdClass $user): array {
        return ['home', 'trainings_i_deliver', 'profile'];
    }

    public function onboarding_steps(\stdClass $user): array {
        // consent_capture carries the DPDP notice for non-employees (ADR-036 D9).
        return ['welcome', 'consent_capture', 'finish'];
    }

    public function required_consents(): array {
        // Not an employee: no workplace legitimate-interest basis for optional uses. They see no
        // leaderboard and have no manager here.
        return [
            'marketing'          => true,
            'leaderboard'        => false,
            'manager_visibility' => false,
            'analytics_export'   => true,
        ];
    }

    public function feature_supported(string $featurekey): bool {
        return in_array($featurekey, ['trainings_i_deliver', 'profile'], true);
    }
}
