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
 * Swahili strings for local_sentientia_api.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_api
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'API ya Umma ya Sentientia';
$string['sentientia_api:read'] = 'Soma API ya umma ya Sentientia (kozi, usajili, ukamilishaji, ujuzi)';
$string['sentientia_api:write'] = 'Fanya shughuli za kuandika kupitia API ya umma ya Sentientia';
$string['sentientia_api:manage'] = 'Simamia usajili wa API ya Sentientia na LTI';
$string['sentientia_api:lti'] = 'Fanya kazi kama endpoint ya uzinduzi wa LTI 1.3';
$string['api_disabled'] = 'API ya umma ya Sentientia imezimwa kwa sasa.';
$string['api_write_disabled'] = 'Shughuli za kuandika kwenye API ya umma ya Sentientia zimezimwa kwa sasa.';
$string['api_enabled_notice'] = 'API ya umma ya Sentientia (v1) imewashwa.';
$string['ratelimited'] = 'Umezidi kikomo cha maombi. Kikomo ni maombi {$a} kwa kila dirisha la muda. Jaribu tena baada ya muda mfupi.';
$string['error_notenant'] = 'Akaunti yako haijaunganishwa na tenant halali; ufikiaji wa API umekataliwa.';
$string['error_notauthenticated'] = 'Uthibitishaji unahitajika ili kuita API ya umma ya Sentientia.';
$string['error_no_manual_enrol'] = 'Usajili wa mkono haupatikani kwenye kozi hii.';
$string['error_role_not_assignable'] = 'Huwezi kutoa jukumu hili kwenye kozi hii.';
$string['rest_base'] = 'URL ya msingi ya REST';
$string['v1_endpoints'] = 'Endpoint za v1';
$string['lti_status'] = 'Hali ya LTI 1.3';
$string['lti_enabled'] = 'LTI 1.3 imewashwa.';
$string['lti_disabled'] = 'LTI 1.3 imezimwa.';
$string['lti_launch_title'] = 'Uzinduzi wa LTI 1.3';
$string['lti_launch_verified'] = 'Uzinduzi wa LTI umethibitishwa.';
$string['lti_message_type'] = 'Aina ya ujumbe';
$string['lti_invalid_token'] = 'Tokeni ya LTI haiko katika muundo sahihi.';
$string['lti_bad_alg'] = 'Algorithm ya kusaini tokeni ya LTI haitumiki (RS256 inahitajika).';
$string['lti_no_key'] = 'Hakuna ufunguo wa uthibitishaji unaopatikana kwa usajili huu wa LTI.';
$string['lti_bad_signature'] = 'Sahihi ya tokeni ya LTI haikuweza kuthibitishwa.';
$string['lti_bad_iss'] = 'Mtoaji wa tokeni ya LTI halingani na usajili.';
$string['lti_bad_aud'] = 'Hadhira (aud) ya tokeni ya LTI hailingani na zana hii.';
$string['lti_expired'] = 'Tokeni ya LTI imeisha muda wake.';
$string['lti_bad_iat'] = 'Muda wa kutolewa kwa tokeni ya LTI si sahihi.';
$string['lti_bad_nonce'] = 'Nonce ya tokeni ya LTI si sahihi, haipo, au imeshatumika.';
$string['lti_no_registration'] = 'Hakuna usajili wa LTI unaolingana uliopatikana kwa uzinduzi huu.';
$string['lti_no_authurl'] = 'Usajili wa LTI hauna URL ya kuingia ya uthibitishaji iliyowekwa.';
$string['setting_ratelimit_heading'] = 'Kikomo cha maombi';
$string['setting_ratelimit_desc'] = 'Kikomo cha maombi kwa kila mtumiaji kwa dirisha la muda maalum kwa API ya umma.';
$string['setting_rate_limit'] = 'Maombi kwa kila dirisha';
$string['setting_rate_limit_desc'] = 'Idadi ya juu ya maombi ya API ambayo mtumiaji mmoja anaweza kufanya ndani ya dirisha moja.';
$string['setting_rate_window'] = 'Urefu wa dirisha (sekunde)';
$string['setting_rate_window_desc'] = 'Urefu wa dirisha la kikomo cha maombi kwa sekunde.';
$string['setting_log_retention'] = 'Muda wa kuhifadhi kumbukumbu za maombi (siku)';
$string['setting_log_retention_desc'] = 'Idadi ya siku za kuhifadhi safu za kumbukumbu za maombi ya API kabla kazi ya usafishaji haijazifuta.';
$string['task_cleanup'] = 'Usafishaji wa API ya Sentientia (vihesabu vya maombi, nonce za LTI, kumbukumbu za maombi)';
$string['privacy:metadata:log'] = 'Kumbukumbu ya maombi ya API ya umma inayoongezewa tu.';
$string['privacy:metadata:log:userid'] = 'Mtumiaji aliyetuma ombi.';
$string['privacy:metadata:log:endpoint'] = 'Endpoint ya API iliyoitwa.';
$string['privacy:metadata:log:status'] = 'Hali ya kimantiki ya ombi.';
$string['privacy:metadata:log:timecreated'] = 'Wakati ombi lilipotumwa.';
$string['privacy:metadata:rate'] = 'Vihesabu vya kikomo cha maombi kwa kila mtumiaji.';
$string['privacy:metadata:rate:userid'] = 'Mtumiaji anayemiliki kihesabu.';
$string['privacy:metadata:rate:hits'] = 'Idadi ya maombi katika dirisha la sasa.';
$string['privacy:metadata:rate:windowstart'] = 'Wakati dirisha la sasa la kikomo cha maombi lilipoanza.';
$string['sentientia_api:webhooks_manage'] = 'Simamia usajili wa webhook zinazotoka na kumbukumbu za uwasilishaji';
$string['webhooks_title'] = 'Webhook zinazotoka';
$string['webhooks_intro'] = 'Sajili endpoint za https zinazopokea JSON POST zilizosainiwa matukio ya kujifunza yanapotokea. Kila usajili una siri yake ya HMAC-SHA256 (header X-Sentientia-Signature: t=&lt;unix&gt;,v1=&lt;hmac of "t.body"&gt;; kataa ikiwa ni ya zamani zaidi ya dakika 5). Uwasilishaji hujaribiwa tena kwa exponential backoff na huwekwa kwenye dead-letter baada ya majaribio 5.';
$string['webhooks_subscriptions'] = 'Usajili';
$string['webhooks_deliveries'] = 'Uwasilishaji wa hivi karibuni';
$string['webhooks_none'] = 'Bado hakuna usajili.';
$string['webhooks_nodeliveries'] = 'Bado hakuna uwasilishaji.';
$string['webhook_name'] = 'Jina';
$string['webhook_url'] = 'URL ya endpoint (https)';
$string['webhook_events'] = 'Matukio';
$string['webhook_event'] = 'Tukio';
$string['webhook_tenant'] = 'ID ya tenant kuu';
$string['webhook_tenant_help'] = 'Wekea usajili huu tenant kuu moja tu (kwa ID yake), au 0 ili kupokea matukio kutoka tenant zote za mteja.';
$string['webhook_enabled'] = 'Imewashwa';
$string['webhook_lastsuccess'] = 'Mafanikio ya mwisho';
$string['webhook_lastfailure'] = 'Kushindikana kwa mwisho';
$string['webhook_status'] = 'Hali';
$string['webhook_attempts'] = 'Majaribio';
$string['webhook_nextattempt'] = 'Jaribio linalofuata';
$string['webhook_httpstatus'] = 'HTTP';
$string['webhook_lasterror'] = 'Hitilafu ya mwisho';
$string['webhook_all_tenants'] = 'Tenant zote';
$string['webhook_never'] = 'Haijawahi';
$string['webhook_add'] = 'Ongeza usajili';
$string['webhook_created'] = 'Usajili umeundwa.';
$string['webhook_deleted'] = 'Usajili na historia yake ya uwasilishaji vimefutwa.';
$string['webhook_toggled'] = 'Usajili umesasishwa.';
$string['webhook_retried'] = 'Uwasilishaji umerudishwa kwenye foleni ili ujaribiwe tena mara moja.';
$string['webhook_secret_shown'] = 'Siri ya kusaini ya "{$a}" (inaonyeshwa mara moja tu - ihifadhi kwenye mfumo unaopokea sasa hivi):';
$string['webhook_action_enable'] = 'Washa';
$string['webhook_action_disable'] = 'Zima';
$string['webhook_action_delete'] = 'Futa';
$string['webhook_action_rotate'] = 'Badilisha siri';
$string['webhook_action_retry'] = 'Jaribu tena';
$string['webhook_confirm_delete'] = 'Futa usajili huu na historia yake yote ya uwasilishaji?';
$string['webhook_counts'] = 'Uwasilishaji - kwenye foleni: {$a->queued}, umetumwa: {$a->sent}, umeshindikana (unajaribiwa tena): {$a->failed}, umeshindikana kabisa: {$a->dead}';
$string['webhook_flag_off_notice'] = 'Feature flag ya webhook zinazotoka imezimwa kwenye wigo wa jumla. Usajili unahifadhiwa, lakini hakuna kinachowekwa kwenye foleni wala kutumwa hadi sentientia.api.enabled na sentientia.api.webhooks.enabled ziwashwe kwa mteja/tenant husika.';
$string['webhook_name_required'] = 'Jina la usajili linahitajika.';
$string['webhook_events_required'] = 'Chagua angalau tukio moja.';
$string['webhook_url_invalid'] = 'Endpoint lazima iwe URL kamili ya https://.';
$string['webhook_url_blocked'] = 'Host ya endpoint hiyo imezuiwa na sera ya usalama ya tovuti kwa maombi yanayotoka (anwani ya faragha au ya ndani).';
$string['event_course_completed'] = 'Kozi imekamilika';
$string['event_enrolment_created'] = 'Usajili umeundwa';
$string['event_certificate_issued'] = 'Cheti kimetolewa';
$string['task_webhook_drain'] = 'Uwasilishaji wa webhook zinazotoka za API ya Sentientia';
$string['privacy:metadata:whdel'] = 'Matukio ya webhook zinazotoka yaliyo kwenye foleni na yaliyowasilishwa.';
$string['privacy:metadata:whdel:userid'] = 'Mtumiaji anayehusika na tukio.';
$string['privacy:metadata:whdel:eventkey'] = 'Aina ya tukio la kujifunza.';
$string['privacy:metadata:whdel:status'] = 'Hali ya uwasilishaji wa tukio.';
$string['privacy:metadata:whdel:timecreated'] = 'Wakati tukio lilipowekwa kwenye foleni.';
$string['privacy:metadata:webhook_endpoint'] = 'Metadata ya tukio (ID ya mtumiaji, ID ya kozi, muda - bila majina wala barua pepe) hutumwa kwa POST kwenye endpoint za https zilizosajiliwa na mteja.';
$string['sentientia_api:scim_manage'] = 'Simamia client za provisioning za SCIM 2.0';
$string['scim_title'] = 'Client za provisioning za SCIM 2.0';
$string['scim_intro'] = 'Sajili client moja kwa kila mtoa utambulisho (Entra ID, Okta). Kila client hupewa tokeni ya bearer (inaonyeshwa mara moja tu) na hufungwa kwa tenant moja: watumiaji inaowaunda huingia kwenye tenant hiyo, na inaweza tu kuona, kusasisha au kuzima watumiaji walio ndani yake. Kuzima (SCIM DELETE au active=false) husimamisha akaunti na kumaliza vipindi vyake vya kuingia; historia ya kujifunza huhifadhiwa.';
$string['scim_endpoint_url'] = 'URL ya tenant kwa mtoa utambulisho';
$string['scim_clients'] = 'Client';
$string['scim_none'] = 'Bado hakuna client za SCIM.';
$string['scim_client_name'] = 'Jina';
$string['scim_client_tenant'] = 'ID ya tenant kuu';
$string['scim_client_tenant_help'] = 'Watumiaji wanaowekwa na client hii huwekwa kwenye tenant kuu hii na client inabaki ndani yake tu. 0 = client ya kiwango cha tovuti (tenant zote) - itumie kwa mwendeshaji wa jukwaa pekee.';
$string['scim_client_auth'] = 'Njia ya uthibitishaji kwa watumiaji walioundwa';
$string['scim_client_auth_help'] = 'Plugin ya uthibitishaji ya Moodle inayopewa akaunti ambazo client hii huunda - kwa kawaida ni njia ya single sign-on inayotolewa na mtoa utambulisho huyo huyo (oauth2 / oidc / saml2). Kisha watumiaji huingia kupitia IdP; hakuna nenosiri linalowekwa.';
$string['scim_client_ratelimit'] = 'Maombi kwa kila dirisha';
$string['scim_client_ratelimit_help'] = 'Kikomo cha maombi kwa kila client kwa dirisha moja la kikomo (urefu wa dirisha ni mpangilio wa plugin nzima). 0 hutumia kikomo cha plugin nzima.';
$string['scim_client_enabled'] = 'Imewashwa';
$string['scim_client_lastseen'] = 'Ilionekana mwisho';
$string['scim_mappings'] = 'Watumiaji waliounganishwa';
$string['scim_client_add'] = 'Ongeza client';
$string['scim_client_created'] = 'Client imeundwa.';
$string['scim_client_deleted'] = 'Client na uunganisho wake wa externalId vimefutwa (akaunti za watumiaji hazijaguswa).';
$string['scim_client_toggled'] = 'Client imesasishwa.';
$string['scim_client_token_shown'] = 'Tokeni ya bearer ya "{$a}" (inaonyeshwa mara moja tu - ibandike kwenye mtoa utambulisho sasa hivi):';
$string['scim_action_enable'] = 'Washa';
$string['scim_action_disable'] = 'Zima';
$string['scim_action_delete'] = 'Futa';
$string['scim_action_rotate'] = 'Badilisha tokeni';
$string['scim_confirm_delete'] = 'Futa client hii? Tokeni yake itaacha kufanya kazi mara moja na uunganisho wake wa externalId utaondolewa. Akaunti za watumiaji haziathiriwi.';
$string['scim_client_name_required'] = 'Jina la client linahitajika.';
$string['scim_client_auth_invalid'] = 'Njia hiyo ya uthibitishaji hairuhusiwi kwa watumiaji walioundwa na SCIM.';
$string['scim_flag_off_notice'] = 'Feature flag ya SCIM imezimwa kwenye wigo wa jumla. Endpoint hujibu 503 hadi sentientia.api.enabled na sentientia.api.scim.enabled ziwashwe kwa mteja/tenant husika.';
$string['scim_unauthorized'] = 'Tokeni halali ya bearer inahitajika.';
$string['scim_disabled'] = 'Provisioning ya SCIM haijawashwa kwa client hii.';
$string['scim_notfound'] = 'Rasilimali haikupatikana.';
$string['scim_conflict_username'] = 'Mtumiaji mwenye userName hii tayari yupo.';
$string['scim_conflict_email'] = 'Mtumiaji mwenye anwani hii ya barua pepe tayari yupo.';
$string['scim_bad_json'] = 'Maudhui ya ombi lazima yawe object ya JSON.';
$string['scim_internal'] = 'Ombi la provisioning halikuweza kukamilika.';
$string['privacy:metadata:scimmap'] = 'Viungo kati ya watumiaji wa Sentientia na kitambulisho kilichotolewa na mtoa utambulisho wa nje.';
$string['privacy:metadata:scimmap:userid'] = 'Mtumiaji wa Sentientia.';
$string['privacy:metadata:scimmap:externalid'] = 'Kitambulisho cha mtumiaji kutoka kwa mtoa utambulisho.';
$string['privacy:metadata:scimmap:timecreated'] = 'Wakati kiungo kilipoundwa.';
$string['scim_events'] = 'Matukio ya provisioning (uthibitisho)';
$string['scim_events_intro'] = 'Kila akaunti ambayo mtoa utambulisho aliiunda, aliiwezesha upya, aliizima, aliisasisha au aliihamisha kupitia SCIM, pamoja na muda na client - ushahidi wa deprovisioning ambao wakaguzi huuomba.';
$string['scim_events_none'] = 'Bado hakuna matukio ya provisioning.';
$string['scim_event_time'] = 'Lini (UTC)';
$string['scim_event_action'] = 'Kitendo';
$string['scim_event_client'] = 'Client';
$string['scim_event_user'] = 'Mtumiaji';
$string['scim_event_external'] = 'externalId';
$string['scim_event_detail'] = 'Maelezo';
$string['scim_export_csv'] = 'Pakua CSV ya uthibitisho';
$string['scim_action_created'] = 'Imeundwa';
$string['scim_action_reactivated'] = 'Imewezeshwa upya';
$string['scim_action_deactivated'] = 'Imezimwa';
$string['scim_action_updated'] = 'Imesasishwa';
$string['scim_action_moved'] = 'Imehamishwa kikundi';
$string['scim_groups_readonly'] = 'Vikundi vinaakisi mti wa shirika na haviwezi kuundwa, kubadilishwa jina au kufutwa kupitia SCIM; simamia muundo kwenye Usimamizi wa Shirika. Mabadiliko ya uanachama yanakubaliwa.';
$string['scim_groups_unavailable'] = 'Uanachama wa kikundi unahitaji safu za watumiaji za mti wa shirika, ambazo tovuti hii haina.';
$string['scim_member_outside_scope'] = 'Mwanachama mmoja au zaidi hawako kwenye tenant ya client hii.';
$string['privacy:metadata:scimevt'] = 'Kumbukumbu ya uthibitisho ya mabadiliko ya akaunti yaliyofanywa na watoa utambulisho kupitia SCIM.';
$string['privacy:metadata:scimevt:userid'] = 'Mtumiaji aliyeathiriwa.';
$string['privacy:metadata:scimevt:action'] = 'Kile mtoa utambulisho alifanya (kuunda, kuwezesha upya, kuzima, kusasisha, kuhamisha).';
$string['privacy:metadata:scimevt:externalid'] = 'Kitambulisho cha mtoa utambulisho wakati huo.';
$string['privacy:metadata:scimevt:timecreated'] = 'Wakati lilipotokea.';
