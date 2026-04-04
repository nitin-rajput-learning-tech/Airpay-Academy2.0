<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Integrations Hub';

// Settings page
$string['settings_heading'] = 'Airpay Integrations Configuration';
$string['settings_desc'] = 'Configure external integrations for Airpay Academy. Each feature is disabled by default — enable and configure individually.';

// AI features
$string['ai_heading'] = 'AI Features (Phase 9)';
$string['ai_enable'] = 'Enable AI Features';
$string['ai_enable_desc'] = 'Master toggle for all AI features. Requires AI provider configured in Site Admin → AI.';
$string['ai_recommendations_enable'] = 'Enable AI Course Recommendations';
$string['ai_recommendations_desc'] = 'Show personalised course recommendations on learner dashboard based on completion history and skill gaps.';
$string['ai_quiz_enable'] = 'Enable AI Quiz Generation';
$string['ai_quiz_desc'] = 'Allow L&D admins to generate quiz questions from course content using AI.';

// SENTIENTIA
$string['sentientia_heading'] = 'SENTIENTIA Content Pipeline (Phase 9)';
$string['sentientia_enable'] = 'Enable SENTIENTIA Pipeline';
$string['sentientia_desc'] = 'SOP → SCORM automation pipeline. Requires ElevenLabs API key for voice generation.';
$string['elevenlabs_apikey'] = 'ElevenLabs API Key';
$string['elevenlabs_apikey_desc'] = 'API key from elevenlabs.io for voice generation (Agent 4).';
$string['elevenlabs_voiceid'] = 'ElevenLabs Voice ID';
$string['elevenlabs_voiceid_desc'] = 'Voice ID to use for narration generation.';

// Microsoft 365
$string['m365_heading'] = 'Microsoft 365 Integration (Phase 10)';
$string['m365_enable'] = 'Enable Microsoft 365 SSO';
$string['m365_desc'] = 'Azure AD Single Sign-On. Requires OIDC plugin configured and Azure app registration.';
$string['m365_tenant_id'] = 'Azure Tenant ID';
$string['m365_tenant_id_desc'] = 'From Azure Portal → App registrations → Directory (tenant) ID.';
$string['m365_client_id'] = 'Azure Client ID';
$string['m365_client_id_desc'] = 'Application (client) ID from Azure app registration.';
$string['m365_client_secret'] = 'Azure Client Secret';
$string['m365_client_secret_desc'] = 'Client secret value (rotates every 24 months).';

// Teams
$string['teams_heading'] = 'Microsoft Teams Notifications (Phase 10)';
$string['teams_enable'] = 'Enable Teams Notifications';
$string['teams_desc'] = 'Send learning events (enrolment, deadline, completion) to Teams channels via webhook.';
$string['teams_webhook_url'] = 'Teams Webhook URL';
$string['teams_webhook_url_desc'] = 'Incoming webhook URL for the target Teams channel.';
$string['teams_events'] = 'Events to Notify';
$string['teams_events_desc'] = 'Which events trigger Teams notifications.';

// HRMS
$string['hrms_heading'] = 'HRMS Sync (Phase 10)';
$string['hrms_enable'] = 'Enable HRMS Sync';
$string['hrms_desc'] = 'Real-time employee sync from Keka or other HRMS via REST API.';
$string['hrms_api_url'] = 'HRMS API Endpoint';
$string['hrms_api_url_desc'] = 'Base URL of the HRMS API (e.g., https://api.keka.com/v1/).';
$string['hrms_api_key'] = 'HRMS API Key';
$string['hrms_api_key_desc'] = 'Authentication key for the HRMS API.';
$string['hrms_sync_interval'] = 'Sync Interval (hours)';
$string['hrms_sync_interval_desc'] = 'How often to pull employee updates. Default: 4 hours.';
