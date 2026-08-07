<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Integrations Hub';

// Settings page
$string['settings_heading'] = 'Airpay Integrations Configuration';
$string['settings_desc'] = 'Configure external integrations for Airpay Academy. Each feature is disabled by default — enable and configure individually.';

// AI features
$string['ai_heading'] = 'AI Features';
$string['ai_enable'] = 'Enable AI Features';
$string['ai_enable_desc'] = 'Master toggle for all AI features. Requires AI provider configured in Site Admin → AI.';
$string['ai_recommendations_enable'] = 'Enable AI Course Recommendations';
$string['ai_recommendations_desc'] = 'Show personalised course recommendations on learner dashboard based on completion history and skill gaps.';
$string['ai_quiz_enable'] = 'Enable AI Quiz Generation';
$string['ai_quiz_desc'] = 'Allow L&D admins to generate quiz questions from course content using AI.';

// SENTIENTIA
$string['sentientia_heading'] = 'SENTIENTIA Content Pipeline';
$string['sentientia_enable'] = 'Enable SENTIENTIA Pipeline';
$string['sentientia_desc'] = 'SOP → SCORM automation pipeline. Requires ElevenLabs API key for voice generation.';
$string['elevenlabs_apikey'] = 'ElevenLabs API Key';
$string['elevenlabs_apikey_desc'] = 'API key from elevenlabs.io for voice generation (Agent 4).';
$string['elevenlabs_voiceid'] = 'ElevenLabs Voice ID';
$string['elevenlabs_voiceid_desc'] = 'Voice ID to use for narration generation.';

// Microsoft 365
$string['m365_heading'] = 'Microsoft 365 Integration';
$string['m365_enable'] = 'Enable Microsoft 365 SSO';
$string['m365_desc'] = 'Azure AD Single Sign-On. Requires OIDC plugin configured and Azure app registration.';
$string['m365_tenant_id'] = 'Azure Tenant ID';
$string['m365_tenant_id_desc'] = 'From Azure Portal → App registrations → Directory (tenant) ID.';
$string['m365_client_id'] = 'Azure Client ID';
$string['m365_client_id_desc'] = 'Application (client) ID from Azure app registration.';
$string['m365_client_secret'] = 'Azure Client Secret';
$string['m365_client_secret_desc'] = 'Client secret value (rotates every 24 months).';

// Teams
$string['teams_heading'] = 'Microsoft Teams Notifications';
$string['teams_enable'] = 'Enable Teams Notifications';
$string['teams_desc'] = 'Send learning events (enrolment, deadline, completion) to Teams channels via webhook.';
$string['teams_webhook_url'] = 'Teams Webhook URL';
$string['teams_webhook_url_desc'] = 'Incoming webhook URL for the target Teams channel.';
$string['teams_events'] = 'Events to Notify';
$string['teams_events_desc'] = 'Which events trigger Teams notifications.';

// HRMS
$string['hrms_heading'] = 'HRMS Sync';
$string['hrms_enable'] = 'Enable HRMS Sync';
$string['hrms_desc'] = 'Real-time employee sync from Keka or other HRMS via REST API.';
$string['hrms_api_url'] = 'HRMS API Endpoint';
$string['hrms_api_url_desc'] = 'Base URL of the HRMS API (e.g., https://api.keka.com/v1/).';
$string['hrms_api_key'] = 'HRMS API Key';
$string['hrms_api_key_desc'] = 'Authentication key for the HRMS API.';
$string['hrms_sync_interval'] = 'Sync Interval (hours)';
$string['hrms_sync_interval_desc'] = 'How often to pull employee updates. Default: 4 hours.';

// KeKa HRMS (2026-08-07 JML hardening)
$string['keka_heading'] = 'KeKa HRMS Integration';
$string['keka_heading_desc'] = 'Joiner-Mover-Leaver automation via KeKa REST API + webhooks. The webhook endpoint and the reconciliation task are additionally gated by the sentientia.hrms.* feature flags (default off) and the "Enable HRMS Sync" toggle above.';
$string['keka_base_url'] = 'KeKa API Base URL';
$string['keka_base_url_desc'] = 'e.g., https://airpay.keka.com';
$string['keka_api_key'] = 'KeKa API Key';
$string['keka_api_key_desc'] = 'Generate from KeKa Admin > Integrations.';
$string['keka_client_id'] = 'KeKa OAuth Client ID';
$string['keka_client_id_desc'] = 'Alternative to API key.';
$string['keka_client_secret'] = 'KeKa OAuth Client Secret';
$string['keka_client_secret_desc'] = 'Used with the OAuth client ID when no API key is set.';
$string['webhook_secret'] = 'Webhook Secret';
$string['webhook_secret_desc'] = 'Set the same value in the KeKa webhook configuration. KeKa must send it in the X-Webhook-Secret HTTP header — a ?secret= query parameter is not accepted (it would leak into access logs). Endpoint: /local/sentientia_integrations/webhook.php';
$string['keka_default_orgpath'] = 'Default org path for new users';
$string['keka_default_orgpath_desc'] = 'Org path (e.g. /1) that webhook-created users are placed under when their KeKa department cannot be mapped to an organisation. The path is validated against the org tree; invalid values fall back to /1.';
$string['task_keka_reconcile'] = 'KeKa HRMS reconciliation pull';

// Gamification
$string['gamification_heading'] = 'Gamification';
$string['gamification_enable'] = 'Enable Gamification';
$string['gamification_desc'] = 'XP points, leaderboards, and learning streaks. Requires block_xp plugin installed.';
$string['gamification_xp_per_completion'] = 'XP per Course Completion';
$string['gamification_xp_per_completion_desc'] = 'XP awarded when a learner completes a course.';
$string['gamification_leaderboard_enable'] = 'Enable Department Leaderboards';
$string['gamification_leaderboard_desc'] = 'Show leaderboards filtered by costcenter/department.';

// Privacy.
$string['privacy:metadata'] = 'The Airpay sentientia_integrations plugin does not store personal data in plugin-owned tables; user state lives on core Sentientia LMS tables exported by their respective providers.';
