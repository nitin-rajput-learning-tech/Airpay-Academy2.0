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
 * Swahili strings for local_sentientia_content_market.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Soko la Maudhui';
$string['privacy:metadata'] = 'Plugin ya Soko la Maudhui haihifadhi data binafsi. Inaorodhesha tu metadata ya katalogi za kozi za watoa huduma wa nje.';
$string['sentientia_content_market:view'] = 'Vinjari katalogi ya Soko la Maudhui';
$string['sentientia_content_market:syncproviders'] = 'Anzisha usawazishaji wa katalogi ya mtoa huduma';
$string['sentientia_content_market:manageproviders'] = 'Simamia mipangilio na vitambulisho vya watoa huduma';
$string['sentientia_content_market:mapskills'] = 'Unganisha vipengee vya katalogi na mfumo wa ujuzi kwa mkono';
$string['browse_heading'] = 'Soko la Maudhui';
$string['browse_desc'] = 'Gundua kozi zilizochaguliwa kutoka Go1, Udemy Business, Coursera na Skillsoft — zinatafutika na zimeunganishwa na mfumo wako wa ujuzi.';
$string['search_placeholder'] = 'Tafuta kozi, mada, ujuzi...';
$string['search_button'] = 'Tafuta';
$string['filter_provider'] = 'Mtoa huduma';
$string['filter_all_providers'] = 'Watoa huduma wote';
$string['filter_content_type'] = 'Aina';
$string['filter_all_types'] = 'Aina zote';
$string['filter_level'] = 'Kiwango';
$string['filter_all_levels'] = 'Viwango vyote';
$string['filter_skill'] = 'Ujuzi';
$string['results_count'] = 'Matokeo {$a}';
$string['no_results'] = 'Hakuna kozi zinazolingana na vichujio vyako.';
$string['no_results_hint'] = 'Jaribu kupanua utafutaji wako au ondoa kichujio.';
$string['view_on_provider'] = 'Fungua kwenye {$a}';
$string['duration_mins'] = 'dakika {$a}';
$string['duration_hours'] = 'saa {$a}';
$string['level_beginner'] = 'Mwanzo';
$string['level_intermediate'] = 'Kati';
$string['level_advanced'] = 'Juu';
$string['content_type_video'] = 'Video';
$string['content_type_course'] = 'Kozi';
$string['content_type_microlearning'] = 'Mafunzo mafupi';
$string['content_type_podcast'] = 'Podikasti';
$string['content_type_article'] = 'Makala';
$string['free_label'] = 'Imejumuishwa';
$string['price_label'] = 'USD {$a}';
$string['page_prev'] = 'Iliyotangulia';
$string['page_next'] = 'Inayofuata';
$string['page_indicator'] = 'Ukurasa {$a->current} kati ya {$a->total}';
$string['sync_now'] = 'Sawazisha sasa';
$string['manage_providers'] = 'Simamia watoa huduma';
$string['featureunavailable'] = 'Soko la Maudhui bado halijawezeshwa kwa shirika lako. Wasiliana na msimamizi wako wa Mafunzo na Maendeleo.';
$string['error_invalidtenant'] = 'Tenant si sahihi. Huna ruhusa ya kufikia rasilimali hii.';
$string['settings_general_heading'] = 'Soko la Maudhui';
$string['settings_general_desc'] = 'Sanidi vitambulisho vya watoa maudhui wa nje. Wezesha kila mtoa huduma peke yake kupitia switchboard ya Feature Flags (Sentientia > Switchboard). Vitambulisho vyote huhifadhiwa vikiwa vimesimbwa na havionyeshwi kamwe kwenye logi.';
$string['settings_go1_heading'] = 'Go1';
$string['settings_go1_desc'] = 'Maktaba ya maudhui ya Go1 — kozi 80,000+. Pata ufunguo wa API kutoka Go1 Partner Dashboard yako.';
$string['settings_go1_api_key'] = 'Ufunguo wa API wa Go1';
$string['settings_go1_api_key_desc'] = 'Tokeni ya Bearer kwa Go1 REST API v3. Inapatikana kwenye akaunti yako ya Go1 → Integrations → API Keys.';
$string['settings_udemy_heading'] = 'Udemy Business';
$string['settings_udemy_desc'] = 'Katalogi ya usajili wa Udemy Business. Inahitaji akaunti hai ya Udemy Business.';
$string['settings_udemy_account_id'] = 'ID ya Akaunti ya Udemy Business';
$string['settings_udemy_account_id_desc'] = 'Kitambulisho cha akaunti ya shirika lako kwenye Udemy Business.';
$string['settings_udemy_api_key'] = 'Ufunguo wa API wa Udemy';
$string['settings_udemy_api_key_desc'] = 'Ufunguo wa API kutoka Udemy Business → Settings → API Credentials.';
$string['settings_coursera_heading'] = 'Coursera for Business';
$string['settings_coursera_desc'] = 'Katalogi ya usajili wa Coursera for Business — kozi na vyeti vya kiwango cha chuo kikuu.';
$string['settings_coursera_client_id'] = 'Client ID ya Coursera OAuth';
$string['settings_coursera_client_id_desc'] = 'Client ID kutoka Coursera for Business → Partner Tools → OAuth Applications.';
$string['settings_coursera_client_secret'] = 'Client Secret ya Coursera OAuth';
$string['settings_coursera_client_secret_desc'] = 'Client secret inayoendana na Client ID iliyo hapo juu.';
$string['settings_skillsoft_heading'] = 'Skillsoft Percipio';
$string['settings_skillsoft_desc'] = 'Maktaba ya maudhui ya Skillsoft Percipio — kozi za uzingatiaji, uongozi na teknolojia.';
$string['settings_skillsoft_subdomain'] = 'Subdomain ya Percipio';
$string['settings_skillsoft_subdomain_desc'] = 'Subdomain ya shirika lako (mf. "airpay" kwa airpay.percipio.com).';
$string['settings_skillsoft_org_id'] = 'ID ya Shirika kwenye Percipio';
$string['settings_skillsoft_org_id_desc'] = 'GUID ya shirika kutoka Percipio Admin → Settings → API.';
$string['settings_skillsoft_api_key'] = 'Tokeni ya API ya Percipio';
$string['settings_skillsoft_api_key_desc'] = 'Tokeni ya Bearer kutoka Percipio Admin → Settings → API.';
$string['task_sync_providers'] = 'Sawazisha katalogi za watoa maudhui wa nje';
