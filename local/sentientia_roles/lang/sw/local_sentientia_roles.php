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
 * Swahili strings for local_sentientia_roles.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Usimamizi wa Majukumu wa Sentientia';
$string['sentientia_roles:view'] = 'Tazama UI ya usimamizi wa majukumu';
$string['sentientia_roles:manage'] = 'Hariri ruhusa za uwezo kwenye majukumu';
$string['sentientia_roles:assign'] = 'Pangia au ondoa majukumu ya watumiaji';
$string['sentientia_roles:audit'] = 'Tazama kumbukumbu za ukaguzi za usimamizi wa majukumu';
$string['sentientia_roles:export'] = 'Hamisha data ya majukumu + uwezo kama CSV';
$string['heading_index'] = 'Usimamizi wa majukumu';
$string['heading_view'] = 'Jukumu: {$a}';
$string['heading_audit'] = 'Kumbukumbu za ukaguzi';
$string['filter_archetype'] = 'Aina ya msingi';
$string['filter_archetype_all'] = 'Aina zote za msingi';
$string['filter_search'] = 'Tafuta';
$string['filter_search_placeholder'] = 'Jina la jukumu au jina fupi';
$string['btn_audit_log'] = 'Kumbukumbu za ukaguzi';
$string['btn_export_csv'] = 'Hamisha CSV';
$string['btn_view_role'] = 'Tazama';
$string['btn_edit_caps'] = 'Hariri uwezo';
$string['col_name'] = 'Jina';
$string['col_shortname'] = 'Jina fupi';
$string['col_archetype'] = 'Aina ya msingi';
$string['col_caps'] = 'Uwezo';
$string['col_assignments'] = 'Upangaji';
$string['col_sortorder'] = 'Mpangilio';
$string['col_actions'] = 'Vitendo';
$string['tab_overview'] = 'Muhtasari';
$string['tab_capabilities'] = 'Uwezo';
$string['tab_assignments'] = 'Upangaji';
$string['tab_audit'] = 'Ukaguzi';
$string['ov_id'] = 'ID ya jukumu';
$string['ov_shortname'] = 'Jina fupi';
$string['ov_archetype'] = 'Aina ya msingi';
$string['ov_archetype_custom'] = '(maalum — hakuna aina ya msingi)';
$string['ov_description'] = 'Maelezo';
$string['ov_caps_total'] = 'Jumla ya uwezo';
$string['ov_caps_allow'] = 'Ruhusu';
$string['ov_caps_prevent'] = 'Zuia';
$string['ov_caps_prohibit'] = 'Kataza';
$string['ov_assignments'] = 'Upangaji wa watumiaji';
$string['ov_audit_entries'] = 'Rekodi za ukaguzi (jukumu hili)';
$string['cap_filter_search'] = 'Chuja uwezo';
$string['cap_filter_perm'] = 'Ruhusa';
$string['cap_filter_perm_all'] = 'Zote';
$string['cap_perm_inherit'] = 'Rithi (haijawekwa)';
$string['cap_perm_allow'] = 'Ruhusu';
$string['cap_perm_prevent'] = 'Zuia';
$string['cap_perm_prohibit'] = 'Kataza';
$string['cap_col_name'] = 'Uwezo';
$string['cap_col_component'] = 'Kijenzi';
$string['cap_col_risks'] = 'Hatari';
$string['cap_col_perm'] = 'Ruhusa';
$string['cap_col_actions'] = 'Vitendo';
$string['cap_no_results'] = 'Hakuna uwezo unaolingana na kichujio cha sasa.';
$string['form_edit_cap'] = 'Hariri uwezo';
$string['form_capability'] = 'Uwezo';
$string['form_permission'] = 'Ruhusa';
$string['form_reason'] = 'Sababu ya mabadiliko';
$string['form_reason_help'] = 'Si lazima. Huhifadhiwa kwenye kumbukumbu za ukaguzi kwa ajili ya ukaguzi wa uzingatiaji.';
$string['form_save'] = 'Hifadhi mabadiliko';
$string['form_cancel'] = 'Ghairi';
$string['audit_col_when'] = 'Lini';
$string['audit_col_who'] = 'Nani';
$string['audit_col_role'] = 'Jukumu';
$string['audit_col_action'] = 'Kitendo';
$string['audit_col_cap'] = 'Uwezo';
$string['audit_col_change'] = 'Mabadiliko';
$string['audit_col_reason'] = 'Sababu';
$string['audit_no_entries'] = 'Bado hakuna rekodi za ukaguzi.';
$string['audit_filter_role'] = 'Chuja kwa jukumu';
$string['audit_filter_role_all'] = 'Majukumu yote';
$string['audit_filter_action_all'] = 'Vitendo vyote';
$string['audit_filter_action'] = 'Chuja kwa kitendo';
$string['audit_action_capability_set'] = 'Uwezo umebadilishwa';
$string['audit_action_capability_unset'] = 'Uwezo umewekwa upya';
$string['audit_action_role_assigned'] = 'Jukumu limepangiwa mtumiaji';
$string['audit_action_role_unassigned'] = 'Jukumu limeondolewa';
$string['audit_action_role_created'] = 'Jukumu limeundwa';
$string['audit_action_role_deleted'] = 'Jukumu limefutwa';
$string['err_role_not_found'] = 'Jukumu halikupatikana.';
$string['err_user_not_found'] = 'Mtumiaji hakupatikana.';
$string['err_capability_not_found'] = 'Uwezo "{$a}" haujasajiliwa kwenye usakinishaji huu wa Sentientia LMS.';
$string['err_invalid_permission'] = 'Ruhusa lazima iwe mojawapo ya: inherit, allow, prevent, prohibit.';
$string['err_cannot_modify_admin'] = 'Huwezi kubadilisha uwezo kwenye jukumu la msimamizi wa tovuti.';
$string['err_filterstoolong'] = 'Data ya vichujio imezidi kikomo.';
$string['err_definitions_crosstenant'] = 'Ufafanuzi wa majukumu unashirikiwa na kila tenant, kwa hiyo ni msimamizi wa jukwaa (wa tenant zote) pekee anayeweza kuubadilisha.';
$string['err_role_not_assignable'] = 'Unaweza tu kumpangia mtu mwingine ndani ya shirika lako jukumu ambalo wewe mwenyewe unalo na unaruhusiwa kulipangia wengine.';
$string['err_assignment_not_found'] = 'Mtumiaji huyo hana jukumu hili kwenye ngazi ya tovuti, kwa hiyo hakuna cha kuondoa.';
$string['assignment_scope_system'] = 'Jukwaa zima';
$string['privacy:metadata:auditlog'] = 'Kumbukumbu za ukaguzi zinazoongezwa tu za mabadiliko ya majukumu na uwezo yaliyofanywa kupitia UI ya usimamizi wa majukumu, pamoja na upangaji wa majukumu ulioingizwa kutoka BizLMS.';
$string['privacy:metadata:auditlog:roleid'] = 'Jukumu linalobadilishwa.';
$string['privacy:metadata:auditlog:capability'] = 'Uwezo ambao ruhusa yake ilibadilishwa.';
$string['privacy:metadata:auditlog:oldpermission'] = 'Ruhusa ya uwezo kabla ya mabadiliko.';
$string['privacy:metadata:auditlog:newpermission'] = 'Ruhusa ya uwezo baada ya mabadiliko.';
$string['privacy:metadata:auditlog:changedby'] = 'ID ya mtumiaji ya msimamizi aliyefanya mabadiliko.';
$string['privacy:metadata:auditlog:targetuserid'] = 'ID ya mtumiaji anayepangiwa au kuondolewa jukumu (kwa matukio ya upangaji wa majukumu).';
$string['privacy:metadata:auditlog:reason'] = 'Maelezo ya hiari ya msimamizi kuhusu sababu ya mabadiliko.';
$string['privacy:metadata:auditlog:open_path'] = 'Njia ya tenant ya Airpay ya msimamizi wakati wa mabadiliko.';
$string['privacy:metadata:auditlog:timecreated'] = 'Wakati mabadiliko yalipotokea.';
$string['cap_updated_success'] = 'Uwezo "{$a}" umesasishwa.';
