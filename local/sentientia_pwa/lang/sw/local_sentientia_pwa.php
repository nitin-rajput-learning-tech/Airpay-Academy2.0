<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Swahili strings for local_sentientia_pwa.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_pwa
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — PWA';
$string['privacy:metadata'] = 'Plugin ya Sentientia LMS PWA huhifadhi endpoints za usajili wa web-push kwa kila mtumiaji. Arifa za push zisipowashwa, hakuna data binafsi inayohifadhiwa.';
$string['nav_label'] = 'Arifa za kivinjari';
$string['preferences_page_title'] = 'Arifa za kivinjari';
$string['preferences_page_heading'] = 'Arifa za kivinjari';
$string['preferences_page_intro'] = 'Washa arifa za push kwenye kivinjari hiki ili upate vikumbusho, taarifa mpya za kozi na arifa za mazoezi bila kuacha Sentientia LMS ikiwa wazi kwenye kichupo.';
$string['preferences_install_heading'] = 'Sakinisha Sentientia LMS kama app';
$string['preferences_install_intro'] = 'Kwa matumizi bora kwenye simu, sakinisha Sentientia LMS kwenye skrini yako ya mwanzo kupitia menyu ya kivinjari chako. Ikishasakinishwa, arifa za push huonekana kama za app nyingine yoyote — hata kivinjari kikiwa kimefungwa.';
$string['subscribe_section_title'] = 'Arifa za push';
$string['subscribe_intro'] = 'Pata vikumbusho vya tarehe za mwisho, taarifa mpya za kozi na arifa za mazoezi kwenye kivinjari hiki. Unaweza kuzizima wakati wowote.';
$string['subscribe_enable'] = 'Washa arifa za kivinjari';
$string['subscribe_disable'] = 'Zima arifa za kivinjari';
$string['subscribe_unsupported'] = 'Kivinjari chako hakiwezi kupokea arifa za push';
$string['subscribe_denied'] = 'Arifa zimezuiwa na kivinjari chako';
$string['subscribe_privacy_note'] = 'Ujumbe wa push hupitishwa kupitia kampuni ya kivinjari chako (Google, Mozilla au Apple). Hatushiriki kamwe maudhui ya ujumbe na kampuni hiyo — ni blob iliyosimbwa tu, ambayo kivinjari chako husimbua kwenye kifaa chako.';
$string['vapid_not_setup_title'] = 'Arifa za push bado hazijasanidiwa';
$string['vapid_not_setup_body'] = 'Jozi ya funguo za VAPID bado haijatengenezwa kwenye seva hii. Mwombe msimamizi wako aendeshe "php local/sentientia_pwa/cli/generate_vapid_keys.php" mara moja.';
$string['push_flag_off_notice'] = 'Utumaji wa push umezimwa kwa sasa na msimamizi wako. Unaweza kuuwasha tena kutoka Switchboard.';
$string['pwa_disabled_redirect'] = 'Kipengele cha PWA kimezimwa kwa sasa. Wasiliana na msimamizi wako.';
$string['vapid_already_exists'] = 'Jozi ya funguo za VAPID tayari ipo. Ongeza --regenerate ili kuibadilisha (hii itabatilisha usajili uliopo).';
$string['vapid_openssl_required'] = 'Extension ya PHP openssl inahitajika ili kutengeneza funguo za VAPID.';
$string['vapid_generation_failed'] = 'Utengenezaji wa funguo za VAPID umeshindikana: {$a}';
$string['missingrequiredfields'] = 'Sehemu ya lazima haijajazwa.';
$string['settings_vapid_heading'] = 'Hali ya jozi ya funguo za VAPID';
$string['settings_vapid_ready'] = 'Jozi ya funguo za VAPID inatumika. Utumaji wa push unaweza kuwashwa kwenye Switchboard.';
$string['settings_vapid_not_setup'] = 'Hakuna jozi ya funguo za VAPID iliyotengenezwa. Arifa za push hazitafanya kazi hadi uendeshe keygen CLI.';
$string['settings_vapid_cli_instruction'] = 'Endesha keygen CLI mara moja kwenye seva (kwa kawaida kwenye web host, kama mtumiaji wa apache):';
$string['settings_vapid_public_label'] = 'Ufunguo wa umma (base64url)';
$string['settings_vapid_generated_label'] = 'Ilitengenezwa';
$string['settings_active_subs_label'] = 'Usajili unaotumika';
$string['settings_vapid_public_safe_note'] = 'Hii ni sehemu ya UMMA ya jozi ya funguo — hutumwa kwa kila kivinjari kinachojisajili na ni salama kuishiriki. Sehemu ya SIRI imesimbwa kwa njia ya envelope inapohifadhiwa na haionyeshwi kamwe kwenye UI ya msimamizi.';
$string['settings_store_body_label'] = 'Hifadhi kichwa + maudhui ya push kwenye kumbukumbu za utumaji';
$string['settings_store_body_desc'] = 'Kwa chaguo-msingi IMEZIMWA — vichwa vya push na herufi 200 za kwanza za maudhui hufanyiwa hash ya SHA-256 kabla ya kuingizwa, hivyo kumbukumbu zinaweza kuhifadhiwa kwa usalama kwa siku 90 za chaguo-msingi bila hatari ya GDPR. IWASHE kwa mifumo ya forensics inayohitaji kulinganisha maudhui halisi ya push na matukio yanayofuata. Rekodi zilizopo HAZIANDIKWI upya.';
$string['settings_vapid_subject_label'] = 'Subject ya VAPID';
$string['settings_vapid_subject_desc'] = 'Kitambulisho cha mawasiliano kinachotumwa ndani ya JWT kwa watoa huduma za push (Google FCM, Mozilla autopush). Lazima kiwe URL ya mailto: au https:. Kampuni za push hukitumia kuwasiliana nawe kuhusu malalamiko ya matumizi mabaya.';
$string['settings_push_defaults_heading'] = 'Chaguo-msingi za arifa za push';
$string['settings_push_defaults_desc'] = 'Chaguo-msingi za upande wa seva zinazotumika wakati wa kutuma kila push. Ujumbe mmoja mmoja unaweza kuzibadilisha.';
$string['settings_default_ttl_label'] = 'TTL ya chaguo-msingi (sekunde)';
$string['settings_default_ttl_desc'] = 'Muda ambao mtoa huduma wa push anapaswa kuendelea kujaribu kutuma ikiwa kifaa hakiko mtandaoni. Chaguo-msingi ni 86400 (saa 24). Weka 0 ili ujumbe utupwe usipofika kwenye jaribio la kwanza.';
$string['settings_max_payload_label'] = 'Ukubwa wa juu wa payload (bytes)';
$string['settings_max_payload_desc'] = 'Payload kubwa zaidi inayoruhusiwa kwa kila push. Spec ya Web Push inataka ≤ 4096 bytes baada ya usimbaji. Chaguo-msingi 3500 huacha nafasi kwa ziada ya usimbaji. Payload kubwa zaidi zitakatwa bila taarifa.';
$string['settings_log_retention_label'] = 'Muda wa kuhifadhi kumbukumbu (siku)';
$string['settings_log_retention_desc'] = 'Muda wa kuhifadhi rekodi kwenye <code>mdl_local_sentientia_push_log</code>. Cron ya kila siku saa 02:00 hufuta rekodi za zamani zaidi. Weka 0 kuhifadhi bila kikomo (haipendekezwi kwa mifumo yenye push nyingi — jedwali hukua haraka).';
$string['settings_push_log_link'] = 'Tazama kumbukumbu za utumaji wa push';
$string['settings_push_log_link_desc'] = 'Kumbukumbu za uendeshaji za kila jaribio la push. Unaweza kuchuja kwa matokeo, mtumiaji na muda.';
$string['task_push_log_retention'] = 'Uhifadhi wa kumbukumbu za push za PWA (ufutaji wa kila siku)';
$string['push_log_page_title'] = 'Kumbukumbu za utumaji wa push za PWA';
$string['push_log_page_heading'] = 'Kumbukumbu za utumaji wa push za PWA';
$string['push_log_stats_24h'] = 'Saa 24 zilizopita';
$string['push_log_stats_line'] = 'Majaribio <strong>{$a->total_24h}</strong> — <span class="text-success"><strong>{$a->sent_24h}</strong> yametumwa</span>, <span class="text-warning">{$a->gone_24h} yameondolewa</span>, <span class="text-danger">{$a->failed_24h} yameshindikana</span>. Watumiaji <strong>{$a->unique_users_24h}</strong> tofauti.';
$string['push_log_filter_apply'] = 'Tumia';
$string['push_log_filter_result'] = 'Matokeo';
$string['push_log_filter_since'] = 'Tangu';
$string['push_log_filter_userid'] = 'ID ya mtumiaji';
$string['push_log_filter_any'] = 'Yoyote';
$string['push_log_filter_sent'] = 'Imetumwa';
$string['push_log_filter_failed'] = 'Imeshindikana';
$string['push_log_filter_gone'] = 'Imeondolewa (usajili umefutwa)';
$string['push_log_filter_truncated'] = 'Imekatwa';
$string['push_log_since_1h'] = 'Saa 1 iliyopita';
$string['push_log_since_24h'] = 'Saa 24 zilizopita';
$string['push_log_since_7d'] = 'Siku 7 zilizopita';
$string['push_log_since_30d'] = 'Siku 30 zilizopita';
$string['push_log_since_all'] = 'Muda wote';
$string['push_log_no_results'] = 'Hakuna utumaji wa push unaolingana na kichujio.';
$string['push_log_total_count'] = 'Matokeo {$a} yanayolingana';
$string['push_log_col_when'] = 'Lini';
$string['push_log_col_user'] = 'Mtumiaji';
$string['push_log_col_host'] = 'Host ya push';
$string['push_log_col_title'] = 'Kichwa';
$string['push_log_col_result'] = 'Matokeo';
$string['push_log_col_http'] = 'HTTP';
$string['push_log_col_error'] = 'Maelezo ya hitilafu';
$string['ios_hint_title'] = 'Sakinisha Sentientia LMS ili uwashe arifa za push';
$string['ios_hint_body'] = 'Kwenye iOS Safari, arifa za push hufanya kazi tu tovuti hii ikiongezwa kwenye skrini yako ya mwanzo:';
$string['ios_hint_step1'] = 'Gusa kitufe cha Share chini ya skrini.';
$string['ios_hint_step2'] = 'Sogeza chini kisha uchague "Add to Home Screen".';
$string['ios_hint_step3'] = 'Fungua Sentientia LMS kutoka kwenye skrini yako ya mwanzo kisha ujaribu tena kuwasha arifa.';
$string['ios_hint_dismiss'] = 'Funga';
$string['invalid_subscription_endpoint'] = 'URL ya endpoint ya usajili haitoki kwenye huduma ya push inayotambulika.';
$string['invalid_subscription_key_p256dh'] = 'Ufunguo wa usajili (p256dh) una muundo usio sahihi.';
$string['invalid_subscription_key_auth'] = 'Siri ya auth ya usajili ina muundo usio sahihi.';
$string['vapid_master_key_missing'] = 'Ufunguo binafsi wa VAPID umesimbwa kwenye disk lakini master key haijawekwa. Weka env var SENTIENTIA_VAPID_MASTER_KEY au $CFG->sentientia_vapid_master_key.';
$string['vapid_pem_decrypt_failed'] = 'Imeshindikana kusimbua ufunguo binafsi wa VAPID uliohifadhiwa. Huenda master key imebadilishwa au blob iliyosimbwa imechezewa.';
$string['install_cta_title'] = 'Sakinisha Sentientia LMS';
$string['install_cta_body'] = 'Ongeza app kwenye skrini yako ya mwanzo ili uifungue kwa mguso mmoja, upate arifa za push na uitumie bila mtandao.';
$string['install_cta_install_btn'] = 'Sakinisha';
$string['install_cta_dismiss'] = 'Si sasa';
$string['install_cta_dismiss_aria'] = 'Funga ujumbe wa kusakinisha';
$string['install_cta_aria_label'] = 'Sakinisha app ya Sentientia LMS';
$string['install_cta_gotit'] = 'Nimeelewa';
$string['privacy:metadata:push_subs'] = 'Usajili wa web-push uliofanywa na vivinjari vya mtumiaji';
$string['privacy:metadata:push_subs:userid'] = 'Mtumiaji anayemiliki usajili huu';
$string['privacy:metadata:push_subs:endpoint'] = 'URL ya endpoint ya huduma ya push kwa kivinjari cha mtumiaji';
$string['privacy:metadata:push_subs:p256dh'] = 'Ufunguo wa umma wa usimbaji uliotengenezwa na kivinjari kwa usajili huu';
$string['privacy:metadata:push_subs:auth_secret'] = 'Siri ya uthibitishaji iliyotengenezwa na kivinjari kwa usajili huu';
$string['privacy:metadata:push_subs:user_agent'] = 'User agent ya kivinjari kilichofanya usajili huu';
$string['privacy:metadata:push_subs:last_seen'] = 'Wakati usajili ulipowasiliana mara ya mwisho';
$string['privacy:metadata:push_subs:timecreated'] = 'Wakati usajili ulipofanywa';
$string['privacy:metadata:push_log'] = 'Kumbukumbu za arifa za push zilizotumwa kwa mtumiaji';
$string['privacy:metadata:push_log:userid'] = 'Mtumiaji aliyetumiwa arifa';
$string['privacy:metadata:push_log:endpoint_host'] = 'Host ya huduma ya push ambayo arifa ilipitia';
$string['privacy:metadata:push_log:title'] = 'Kichwa cha arifa';
$string['privacy:metadata:push_log:body_truncated'] = 'Sehemu fupi ya maudhui ya arifa';
$string['privacy:metadata:push_log:url'] = 'Kiungo ambacho arifa ilielekeza';
$string['privacy:metadata:push_log:result'] = 'Kama utumaji ulifanikiwa au ulishindikana';
$string['privacy:metadata:push_log:sent_at'] = 'Wakati arifa ilipotumwa';
