<?php
/**
 * Library functions for Airpay Integrations.
 * Provides hooks and helper functions for the integration features.
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get AI-powered recommendations for dashboard display.
 * Returns empty array if AI features are disabled.
 *
 * @param int $userid
 * @param int $limit
 * @return array
 */
function local_sentientia_integrations_get_recommendations(int $userid, int $limit = 3): array {
    if (!class_exists('\local_sentientia_integrations\ai_recommender')) {
        return [];
    }
    return \local_sentientia_integrations\ai_recommender::get_recommendations($userid, $limit);
}

/**
 * Check if a specific integration feature is enabled.
 *
 * @param string $feature Feature key (ai, sentientia, m365, teams, hrms)
 * @return bool
 */
function local_sentientia_integrations_is_enabled(string $feature): bool {
    return !empty(get_config('local_sentientia_integrations', $feature . '_enable'));
}

/**
 * Get integration status for admin dashboard display.
 * Returns status of all configured integrations.
 */
function local_sentientia_integrations_get_status(): array {
    $features = [
        'ai' => ['label' => 'AI Features', 'icon' => 'magic'],
        'sentientia' => ['label' => 'SENTIENTIA Pipeline', 'icon' => 'microphone'],
        'm365' => ['label' => 'Microsoft 365 SSO', 'icon' => 'windows'],
        'teams' => ['label' => 'Teams Notifications', 'icon' => 'comments'],
        'hrms' => ['label' => 'HRMS Sync', 'icon' => 'refresh'],
    ];

    $status = [];
    foreach ($features as $key => $info) {
        $enabled = local_sentientia_integrations_is_enabled($key);
        $status[] = [
            'key' => $key,
            'label' => $info['label'],
            'icon' => $info['icon'],
            'enabled' => $enabled,
            'status' => $enabled ? 'Active' : 'Not configured',
            'statusclass' => $enabled ? 'success' : 'secondary',
        ];
    }

    return $status;
}
