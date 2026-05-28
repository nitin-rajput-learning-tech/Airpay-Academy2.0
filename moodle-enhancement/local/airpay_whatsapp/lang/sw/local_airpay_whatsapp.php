<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
// Kiswahili translations — local_airpay_whatsapp
// Machine quality. Native-speaker review recommended.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay WhatsApp na SMS';

// Page chrome
$string['preferences_pagetitle']   = 'Mapendeleo ya mawasiliano';
$string['preferences_nav']         = 'Mapendeleo ya mawasiliano';
$string['preferences_heading']     = 'Airpay Academy ikufikie vipi?';
$string['preferences_intro']       = 'Chagua njia ambazo Airpay Academy inaweza kutuma vikumbusho vya kozi, ukumbusho wa muda na arifa za vyeti. Barua pepe daima imewashwa.';

// Channels
$string['channel_email']           = 'Barua pepe';
$string['channel_whatsapp']        = 'WhatsApp';
$string['channel_sms']             = 'SMS';
$string['channel_email_desc']      = 'Imewashwa daima. Barua pepe yako ya kazini ni njia ya msingi.';
$string['channel_whatsapp_desc']   = 'Kiwango cha haraka cha kufungua. Vifaa vya kuchapisha vimeidhinishwa awali chini ya DLT nchini India.';
$string['channel_sms_desc']        = '95% ya kufungua ndani ya saa moja. Inafanya kazi bila intaneti, bora kwa wafanyakazi wa shamba.';

// Mobile
$string['mobile_label']            = 'Nambari ya simu';
$string['mobile_hint']             = 'Inahitajika kwa WhatsApp na SMS. Jumuisha msimbo wa nchi (mfano +91 kwa India).';
$string['mobile_invalid']          = 'Tafadhali ingiza nambari halali ya simu ikiwa na msimbo wa nchi (mfano +919876543210).';

// Primary
$string['prefer_label']            = 'Njia kuu';
$string['prefer_hint']             = 'Wakati njia zaidi ya moja zinapatikana, hii inajaribiwa kwanza.';

// Consent
$string['dlt_consent_heading']     = 'Idhini (inahitajika kwa WhatsApp/SMS nchini India)';
$string['dlt_consent_body']        = 'Kwa kuchagua, ninakubali kupokea ujumbe wa biashara na huduma kutoka Airpay Academy kwenye njia zilizochaguliwa hapo juu, kulingana na TCCCPR 2018 na Sheria ya DPDP 2023.';
$string['dlt_consent_required']    = 'Lazima ukubali kauli ya idhini ili kuwezesha utoaji wa WhatsApp au SMS.';
$string['dlt_consent_logged_at']   = 'Idhini imerekodiwa: {$a}';

// Disabled
$string['channel_disabled_tenant'] = 'Njia hii kwa sasa imezimwa kwa shirika lako. Wasiliana na msimamizi wako ikiwa ungependa iwashwe.';

// Actions
$string['save_preferences']        = 'Hifadhi mapendeleo';
$string['preferences_saved']       = 'Mapendeleo ya mawasiliano yamesasishwa.';
$string['preferences_unchanged']   = 'Hakuna mabadiliko ya kuhifadhi.';

// Settings
$string['settings_pagetitle']         = 'Airpay WhatsApp na SMS — mipangilio';
$string['settings_heading_live_mode'] = 'Vitambulisho vya mtoaji';
$string['settings_heading_live_mode_desc'] = 'Funguo hizi zinawezesha kutuma WhatsApp/SMS moja kwa moja. Hadi funguo zote ziwekwe, programu jalizi inaendesha katika hali ya majaribio.';
$string['settings_karix_api_key']     = 'Ufunguo wa Karix WhatsApp API';
$string['settings_karix_api_key_desc'] = 'Ishara ya Karix Business API. Inahitajika kwa hali ya WhatsApp live.';
$string['settings_msg91_api_key']     = 'Ufunguo wa MSG91 SMS API';
$string['settings_msg91_api_key_desc'] = 'MSG91 authkey. Inahitajika kwa hali ya SMS live.';
$string['settings_dlt_pe_id']         = 'Kitambulisho cha DLT Principal Entity';
$string['settings_dlt_pe_id_desc']    = 'Kitambulisho cha Principal Entity kilichosajiliwa cha DLT cha shirika lako. Inahitajika kwa SMS.';

// Templates
$string['templates_pagetitle']        = 'Meneja wa kiolezo cha DLT';
$string['templates_heading']          = 'Violezo vya ujumbe vilivyoidhinishwa na DLT';
$string['templates_intro']            = 'Violezo lazima visajiliwe DLT na mtoa huduma. Violezo `approved` tu vinatumika.';
$string['template_status_updated']    = 'Hali ya kiolezo imesasishwa.';
$string['show_body']                  = 'Onyesha mwili';
$string['th_template']                = 'Ufunguo wa kiolezo';
$string['th_channel']                 = 'Njia';
$string['th_status']                  = 'Hali';
$string['th_dlt_id']                  = 'Kitambulisho cha DLT';
$string['th_body']                    = 'Mwili';
$string['th_actions']                 = 'Vitendo';
$string['btn_submit']                 = 'Wasilisha kwa DLT';
$string['btn_approve']                = 'Idhinisha';
$string['btn_reject']                 = 'Kataa';
$string['btn_redraft']                = 'Andika tena';
$string['approved_ready']             = 'Tayari kutumwa';

// Analytics
$string['analytics_pagetitle']        = 'Uchanganuzi wa njia';
$string['analytics_heading']          = 'Mchanganyiko wa njia za WhatsApp / SMS / Barua pepe';

// Privacy
$string['privacy:metadata:local_airpay_user_channel_prefs']
    = 'Mapendeleo ya kuchagua kwa kila mtumiaji ya njia za WhatsApp / SMS / Barua pepe.';
$string['privacy:metadata:local_airpay_user_channel_prefs:userid']
    = 'Mtumiaji ambaye pendeleo hili linahusiana naye.';
$string['privacy:metadata:local_airpay_user_channel_prefs:mobile_number']
    = 'Nambari ya simu ikiwa na msimbo wa nchi.';
$string['privacy:metadata:local_airpay_user_channel_prefs:whatsapp_optin']
    = 'Iwapo mtumiaji amechagua kupokea ujumbe wa WhatsApp.';
$string['privacy:metadata:local_airpay_user_channel_prefs:sms_optin']
    = 'Iwapo mtumiaji amechagua kupokea ujumbe wa SMS.';
$string['privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_at']
    = 'Muda ambapo mtumiaji alitoa idhini ya DLT.';
$string['privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_text']
    = 'Picha ya lugha ya idhini wakati wa kuchagua.';

// C14/F-082 stabilization (2026-05-28) - unified admin landing
$string['admin_index_title'] = 'Paneli ya udhibiti wa WhatsApp';
$string['admin_index_intro'] = 'Mahali pamoja pa kusimamia kituo cha WhatsApp.';
$string['stats_sent_week'] = 'Imetumwa (siku 7)';
$string['stats_active_templates'] = 'Violezo amilifu';
$string['stats_failures_24h'] = 'Kushindwa (24h)';
$string['stats_flag_on'] = 'Imewashwa';
$string['stats_flag_off'] = 'Imezimwa';
$string['stats_flag_label'] = 'Bendera';
$string['admin_index_quicknav'] = 'Urambazaji wa haraka';
$string['admin_index_link_templates'] = 'Kidhibiti cha violezo';
$string['admin_index_link_templates_desc'] = 'Simamia violezo.';
$string['admin_index_link_analytics'] = 'Uchanganuzi';
$string['admin_index_link_analytics_desc'] = 'Mwelekeo na kushindwa.';
$string['admin_index_link_settings'] = 'Mipangilio';
$string['admin_index_link_settings_desc'] = 'Funguo za API.';
