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
 * Swahili strings for local_sentientia_core.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'Plugin ya Sentientia Core huhifadhi safu za uanachama wa org-unit (mtumiaji, kitengo, jukumu, meneja wa moja kwa moja) katika local_sentientia_org_member, pamoja na admin log ya BizLMS iliyoingizwa katika local_sentientia_admin_log (nani aliunda, alisasisha au alifuta kozi na lini, pamoja na maelezo yanayomtaja mtu huyo kwa jina lake la kwanza). Rejista ya tenant yenyewe (usanidi wa mteja + tenant: majina, root id, hali) haina data binafsi yoyote.';
$string['settings_tenant_identity'] = 'Utambulisho wa tenant';
$string['setting_legacy_openpath'] = 'Tambua tenant kutoka open_path ya BizLMS (ya zamani)';
$string['setting_legacy_openpath_desc'] = 'Ikiwashwa (chaguo-msingi), Sentientia hutambua tenant ya mtumiaji kutoka sehemu ya profile ya BizLMS ya zamani <code>open_path</code> — sawa kabisa na tabia ya sasa ya production. Kuizima kumehifadhiwa kwa ajili ya ADR-018 Wave 3+ pindi rejista ya tenant ya Sentientia itakapokuwepo; hadi wakati huo huduma hurudi kwa usalama kwenye <code>open_path</code> hata hivyo. Iache ikiwa imewashwa kwenye production.';
$string['settings_org'] = 'Muundo wa org';
$string['setting_org_legacy'] = 'Tambua meneja / org kutoka BizLMS (ya zamani)';
$string['setting_org_legacy_desc'] = 'Ikiwashwa (chaguo-msingi), Sentientia hutambua meneja wa mtumiaji kutoka sehemu ya BizLMS ya zamani <code>open_supervisorid</code> — sawa kabisa na tabia ya sasa ya production. Kuizima kumehifadhiwa kwa ajili ya ADR-020 Wave 3.2+ pindi modeli ya org ya Sentientia itakapokuwepo; hadi wakati huo huduma hurudi kwa usalama kwenye <code>open_supervisorid</code> hata hivyo. Iache ikiwa imewashwa kwenye production.';
$string['setting_org_dualwrite'] = 'Nakili grafu ya org ya zamani kwenda kwenye modeli ya org ya Sentientia (dual-write)';
$string['setting_org_dualwrite_desc'] = 'Ikiwashwa, task iliyopangwa hunakili mara kwa mara grafu ya org ya BizLMS ya zamani (mti wa cost-center wa <code>open_path</code> + link za meneja za <code>open_supervisorid</code>) kwenda kwenye majedwali ya modeli ya org ya Sentientia. Grafu ya zamani inabaki kuwa chanzo kikuu cha ukweli — hii inayaweka tu majedwali mapya tayari kabla ya cutover itakayokuja. Chaguo-msingi ni kuzimwa (task haifanyi chochote): iwashe tu ili kujaza modeli kwa ajili ya parity check au mazoezi, kisha endesha <code>cli/parity_check_org.php</code> kabla ya kufikiria kubadili. HAIBADILISHI jinsi meneja anavyotambuliwa — hilo bado linaongozwa na flag ya “Tambua meneja / org kutoka BizLMS (ya zamani)” hapo juu.';
$string['task_reconcile_org'] = 'Sawazisha modeli ya org ya Sentientia kutoka kwenye grafu ya zamani';
$string['error_outoftenant'] = 'Huna ruhusa ya kufikia rasilimali hii — ni ya tenant nyingine.';
$string['sentientia_core:managetenants'] = 'Simamia rejista ya tenant ya Sentientia';
$string['settings_tenant_registry'] = 'Rejista ya tenant';
$string['setting_legacy_registry'] = 'Thibitisha tenant kwa allow-list iliyo hardcoded (ya zamani)';
$string['setting_legacy_registry_desc'] = 'Ikiwashwa (chaguo-msingi), Sentientia huthibitisha root za tenant dhidi ya allow-list ya zamani iliyo hardcoded (<code>[1, 77, 177]</code>) — sawa kabisa na tabia ya sasa ya production. Ukiizima, itasoma rejista ya tenant ya Sentientia (ukurasa wa <em>Simamia rejista ya tenant</em> hapa chini) badala yake. Izime tu baada ya kujaza rejista na kuthibitisha usawa wa 100% kwa <code>cli/parity_check_tenants.php</code> (fanya mazoezi kwenye clone DB kwanza). Iache ikiwa imewashwa kwenye production hadi cutover.';
$string['error_invalidtenant'] = 'Tenant isiyojulikana — id hii haipo kwenye rejista ya tenant.';
$string['managetenants'] = 'Simamia rejista ya tenant';
$string['registry_flag_legacy_on'] = 'Rejista ya tenant HAITUMIKI kwa sasa — uthibitishaji wa tenant kwa sasa unatumia allow-list ya zamani iliyo hardcoded. Safu zinazosimamiwa hapa zitaanza kufanya kazi tu baada ya mpangilio wa "Thibitisha tenant kwa allow-list iliyo hardcoded (ya zamani)" kuzimwa.';
$string['registry_flag_legacy_off'] = 'Rejista ya tenant IKO LIVE — uthibitishaji wa tenant sasa unasoma safu hizi. Kuondoa au kusimamisha tenant hapa kunaathiri mara moja nani anaweza kufikia rasilimali za tenant husika.';
$string['customers'] = 'Wateja';
$string['tenants'] = 'Tenant';
$string['tenantcount'] = 'Tenant';
$string['customer_missing'] = '(mteja asiyejulikana)';
$string['nocustomers'] = 'Bado hakuna wateja. Ongeza mmoja hapa chini, au endesha cli/seed_tenants.php.';
$string['notenants'] = 'Bado hakuna tenant iliyosajiliwa. Ongeza moja hapa chini, au endesha cli/seed_tenants.php.';
$string['actions'] = 'Vitendo';
$string['suspend'] = 'Simamisha';
$string['activate'] = 'Washa';
$string['tenant_statuschanged'] = 'Hali ya tenant imesasishwa.';
$string['customer_saved'] = 'Mteja amehifadhiwa.';
$string['tenant_saved'] = 'Tenant imehifadhiwa.';
$string['addcustomer'] = 'Ongeza mteja';
$string['addtenant'] = 'Ongeza tenant';
$string['addcustomer_first'] = 'Ongeza mteja kabla ya kusajili tenant — kila tenant lazima iwe ya mteja mmoja.';
$string['status_active'] = 'Hai';
$string['status_suspended'] = 'Imesimamishwa';
$string['status_archived'] = 'Imewekwa kumbukumbu';
$string['field_customername'] = 'Jina la mteja';
$string['field_shortname'] = 'Jina fupi';
$string['field_shortname_help'] = 'Kitambulisho kifupi, cha kipekee na rahisi kwa mashine kwa ajili ya mteja (herufi, namba, _ na -). Mfano: <code>airpay</code>. Kinatumika ndani ya mfumo tu; hakionyeshwi kwa wanafunzi.';
$string['field_status'] = 'Hali';
$string['field_rootid'] = 'Root id ya tenant';
$string['field_rootid_help'] = 'Root id ya tenant. Kwa sasa hii ni root ya cost-center ya BizLMS (1 = Airpay, 77 = Public, 177 = ZEEA). Lazima iwe namba kamili chanya na ya kipekee katika rejista nzima.';
$string['field_customer'] = 'Mteja mmiliki';
$string['field_tenantname'] = 'Jina la tenant';
$string['field_idnumber'] = 'Id ya nje (si lazima)';
$string['field_idnumber_help'] = 'Ufunguo wa nje usio wa lazima (kwa mfano kitambulisho cha HRMS) unaotumika kusawazisha tenant hii kwenda na kurudi na mfumo wa upstream. Acha wazi kama haihusiki.';
$string['err_shortname_taken'] = 'Jina hilo fupi tayari linatumiwa na mteja mwingine.';
$string['err_rootid_positive'] = 'Root id ya tenant lazima iwe namba kamili chanya.';
$string['err_rootid_taken'] = 'Root id hiyo ya tenant tayari imesajiliwa.';
$string['privacy:metadata:org_member'] = 'Uanachama wa mtumiaji katika org-unit: kitengo, jukumu na meneja wa moja kwa moja';
$string['privacy:metadata:org_member:userid'] = 'Mwanachama';
$string['privacy:metadata:org_member:unitid'] = 'Kitengo cha org ambacho mtumiaji yumo';
$string['privacy:metadata:org_member:role'] = 'Jukumu la mtumiaji ndani ya kitengo';
$string['privacy:metadata:org_member:managerid'] = 'Meneja wa moja kwa moja wa mtumiaji';
$string['privacy:metadata:org_member:timecreated'] = 'Wakati uanachama uliporekodiwa';
$string['sentientia_core:viewadminlog'] = 'Tazama admin log iliyoingizwa';
$string['adminlog'] = 'Admin log iliyoingizwa';
$string['adminlog_intro'] = 'Admin log ya BizLMS (kozi iliyoundwa, kusasishwa au kufutwa) na makosa ya upload ya kozi kwa wingi, vilivyoingizwa wakati wa cutover. Hii ni historia ya kusoma tu: hakuna kitu hapa kinachoweza kuhaririwa au kufutwa. Maelezo yanamtaja mtu aliyefanya kitendo kwa jina lake la kwanza, kwa hiyo chukulia ukurasa huu kama data binafsi.';
$string['adminlog_flagoff'] = 'Ripoti hii imezimwa. Huwashwa na feature flag sentientia.legacy_logs.report.enabled, ambayo imezimwa hadi ukurasa utakapokaguliwa.';
$string['adminlog_noentries'] = 'Hakuna rekodi zinazolingana.';
$string['adminlog_notenant'] = 'Akaunti yako haijaunganishwa na tenant yoyote, kwa hiyo hakuna cha kuonyesha.';
$string['adminlog_total'] = 'Rekodi {$a}';
$string['adminlog_col_when'] = 'Lini';
$string['adminlog_col_who'] = 'Nani';
$string['adminlog_col_source'] = 'Chanzo';
$string['adminlog_col_event'] = 'Tukio';
$string['adminlog_col_module'] = 'Moduli';
$string['adminlog_col_item'] = 'Kipengele';
$string['adminlog_col_description'] = 'Maelezo';
$string['adminlog_source_local_logs'] = 'Log ya usimamizi wa kozi';
$string['adminlog_source_local_courseerrors'] = 'Kosa la upload ya kozi kwa wingi';
$string['adminlog_filter_any'] = 'Yoyote';
$string['adminlog_filter_apply'] = 'Chuja';
$string['adminlog_actor_deleted'] = '{$a} (mtumiaji aliyefutwa)';
$string['adminlog_actor_unknown'] = 'Hajulikani au amefutwa';
$string['privacy:metadata:admin_log'] = 'Admin log ya BizLMS iliyoingizwa na makosa ya upload ya kozi kwa wingi: nani alifanya kitendo, kwenye nini, na lini. Huhifadhiwa kama historia mtu anapofutwa; mtu huyo huondolewa kwenye safu.';
$string['privacy:metadata:admin_log:source'] = 'Log ya BizLMS ambayo safu ilitoka';
$string['privacy:metadata:admin_log:event'] = 'Kilichotokea (kuingiza, kusasisha, kufuta, au kosa la upload)';
$string['privacy:metadata:admin_log:module'] = 'Aina ya kipengele ambacho rekodi inahusu';
$string['privacy:metadata:admin_log:description'] = 'Maandishi huru yanayomtaja mtu aliyefanya kitendo kwa jina la kwanza; husafishwa mtu huyo anapofutwa';
$string['privacy:metadata:admin_log:itemref'] = 'Id ya kipengele ambacho rekodi inahusu, kwa kawaida kozi';
$string['privacy:metadata:admin_log:userid'] = 'Mtu aliyefanya kitendo, au aliyeendesha upload';
$string['privacy:metadata:admin_log:usermodified'] = 'Mtu aliyebadilisha rekodi mara ya mwisho katika BizLMS';
$string['privacy:metadata:admin_log:actor_path'] = 'Njia ya shirika ya mtu aliyefanya kitendo wakati rekodi ilipoingizwa';
$string['privacy:metadata:admin_log:timecreated'] = 'Wakati rekodi ilipofanywa katika BizLMS';
$string['privacy:metadata:admin_log:timemodified'] = 'Wakati rekodi ilipobadilishwa mara ya mwisho katika BizLMS';
$string['privacy:metadata:course_creator'] = 'Safuwima ya muundaji wa kozi ya BizLMS kwenye kozi. Kozi huhifadhiwa muundaji wake anapofutwa; muundaji huwekwa kuwa 0.';
$string['privacy:metadata:course_creator:open_coursecreator'] = 'Mtumiaji aliyeunda kozi, kama ilivyorekodiwa na BizLMS.';
