<?php
/**
 * English language strings for local_sentientia_content_market.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ─── Plugin identity ─────────────────────────────────────────────
$string['pluginname']      = 'Content Marketplace';
$string['privacy:metadata'] = 'The Content Marketplace plugin does not store personal data. It indexes third-party course catalog metadata only.';

// ─── Capabilities ────────────────────────────────────────────────
$string['sentientia_content_market:view']            = 'Browse the Content Marketplace catalog';
$string['sentientia_content_market:syncproviders']   = 'Trigger a provider catalog sync';
$string['sentientia_content_market:manageproviders'] = 'Manage provider configuration and credentials';
$string['sentientia_content_market:mapskills']       = 'Manually map catalog items to skills taxonomy';

// ─── Browse page UI ──────────────────────────────────────────────
$string['browse_heading']         = 'Content Marketplace';
$string['browse_desc']            = 'Discover curated courses from Go1, Udemy Business, Coursera, and Skillsoft — searchable and mapped to your skills taxonomy.';
$string['search_placeholder']     = 'Search courses, topics, skills...';
$string['search_button']          = 'Search';
$string['filter_provider']        = 'Provider';
$string['filter_all_providers']   = 'All providers';
$string['filter_content_type']    = 'Type';
$string['filter_all_types']       = 'All types';
$string['filter_level']           = 'Level';
$string['filter_all_levels']      = 'All levels';
$string['filter_skill']           = 'Skill';
$string['results_count']          = '{$a} results';
$string['no_results']             = 'No courses found matching your filters.';
$string['no_results_hint']        = 'Try broadening your search or removing a filter.';
$string['view_on_provider']       = 'Open on {$a}';
$string['duration_mins']          = '{$a} min';
$string['duration_hours']         = '{$a} hr';
$string['level_beginner']         = 'Beginner';
$string['level_intermediate']     = 'Intermediate';
$string['level_advanced']         = 'Advanced';
$string['content_type_video']     = 'Video';
$string['content_type_course']    = 'Course';
$string['content_type_microlearning'] = 'Micro-learning';
$string['content_type_podcast']   = 'Podcast';
$string['content_type_article']   = 'Article';
$string['free_label']             = 'Included';
$string['price_label']            = 'USD {$a}';
$string['page_prev']              = 'Previous';
$string['page_next']              = 'Next';
$string['page_indicator']         = 'Page {$a->current} of {$a->total}';
$string['sync_now']               = 'Sync now';
$string['manage_providers']       = 'Manage providers';

// ─── Errors ──────────────────────────────────────────────────────
$string['featureunavailable']     = 'The Content Marketplace is not yet enabled for your organisation. Contact your L&D administrator.';
$string['error_invalidtenant']    = 'Invalid tenant. You do not have access to this resource.';

// ─── Settings page ───────────────────────────────────────────────
$string['settings_general_heading'] = 'Content Marketplace';
$string['settings_general_desc']    = 'Configure third-party content provider credentials. Enable each provider individually via the Feature Flags switchboard (Sentientia > Switchboard). All credentials are stored encrypted and never exposed in logs.';

$string['settings_go1_heading']     = 'Go1';
$string['settings_go1_desc']        = 'Go1 content library — 80,000+ courses. Obtain an API key from your Go1 Partner Dashboard.';
$string['settings_go1_api_key']     = 'Go1 API Key';
$string['settings_go1_api_key_desc'] = 'Bearer token for Go1 REST API v3. Obtained from your Go1 account → Integrations → API Keys.';

$string['settings_udemy_heading']      = 'Udemy Business';
$string['settings_udemy_desc']         = 'Udemy Business subscription catalog. Requires an active Udemy Business account.';
$string['settings_udemy_account_id']   = 'Udemy Business Account ID';
$string['settings_udemy_account_id_desc'] = 'Your Udemy Business organisation account identifier.';
$string['settings_udemy_api_key']      = 'Udemy API Key';
$string['settings_udemy_api_key_desc'] = 'API key from Udemy Business → Settings → API Credentials.';

$string['settings_coursera_heading']       = 'Coursera for Business';
$string['settings_coursera_desc']          = 'Coursera for Business subscription catalog — university-grade courses and certificates.';
$string['settings_coursera_client_id']     = 'Coursera OAuth Client ID';
$string['settings_coursera_client_id_desc'] = 'Client ID from Coursera for Business → Partner Tools → OAuth Applications.';
$string['settings_coursera_client_secret']  = 'Coursera OAuth Client Secret';
$string['settings_coursera_client_secret_desc'] = 'Client secret paired with the Client ID above.';

$string['settings_skillsoft_heading']        = 'Skillsoft Percipio';
$string['settings_skillsoft_desc']           = 'Skillsoft Percipio content library — compliance, leadership, and technology courses.';
$string['settings_skillsoft_subdomain']      = 'Percipio Subdomain';
$string['settings_skillsoft_subdomain_desc'] = 'Your organisation subdomain (e.g. "airpay" for airpay.percipio.com).';
$string['settings_skillsoft_org_id']         = 'Percipio Organisation ID';
$string['settings_skillsoft_org_id_desc']    = 'Organisation GUID from Percipio Admin → Settings → API.';
$string['settings_skillsoft_api_key']        = 'Percipio API Token';
$string['settings_skillsoft_api_key_desc']   = 'Bearer token from Percipio Admin → Settings → API.';

// ─── Scheduled task ──────────────────────────────────────────────
$string['task_sync_providers']    = 'Sync third-party content provider catalogs';
