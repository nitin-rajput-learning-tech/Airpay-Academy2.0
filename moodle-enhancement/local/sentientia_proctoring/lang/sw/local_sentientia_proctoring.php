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
 * Swahili strings for local_sentientia_proctoring.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_proctoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Usimamizi wa Mitihani wa Sentientia';
$string['adminhome'] = 'Admin wa usimamizi wa mitihani';
$string['reviewqueue'] = 'Foleni ya ukaguzi';
$string['attempts'] = 'Majaribio yanayosimamiwa';
$string['mysessions'] = 'Vipindi vyangu vinavyosimamiwa';
$string['settings_h'] = 'Mipangilio ya usimamizi wa mitihani';
$string['sentientia_proctoring:attempt'] = 'Fanya quiz inayosimamiwa';
$string['sentientia_proctoring:viewattempts'] = 'Angalia maelezo ya majaribio yanayosimamiwa';
$string['sentientia_proctoring:review'] = 'Kagua vipindi vinavyosimamiwa vilivyowekewa flag';
$string['sentientia_proctoring:manage'] = 'Simamia mipangilio ya usimamizi wa mitihani + ugawaji wa wakaguzi';
$string['sentientia_proctoring:bypass'] = 'Ruka usimamizi wa mtihani (dharura, hurekodiwa kwenye audit log)';
$string['consent_title'] = 'Mtihani unaorekodiwa — ridhaa inahitajika';
$string['consent_intro'] = 'Mtihani huu unasimamiwa. Kabla ya kuanza, tafadhali soma na utoe ridhaa.';
$string['consent_l1'] = '<strong>Uthibitishaji wa utambulisho</strong> — utaombwa kupakia picha ya kitambulisho cha serikali na selfie. Alama ya ulinganifu hukokotolewa na ni alama hiyo tu inayohifadhiwa.';
$string['consent_l2'] = '<strong>Ufuatiliaji wa webcam na skrini</strong> — kamera na skrini yako vinarekodiwa muda wote wa mtihani. Sauti inanaswa kwa ajili ya kutambua kelele tu.';
$string['consent_l3'] = '<strong>Ukaguzi wa kiotomatiki</strong> — rekodi huchunguzwa na AI kutafuta tabia za kutiliwa shaka (nyuso nyingi, kutoka nje ya fremu, kelele za nyuma za muda mrefu). Vipindi vilivyowekewa flag hukaguliwa na msimamizi binadamu.';
$string['consent_retention'] = 'Rekodi huhifadhiwa kwa siku {$a} kisha hufutwa. Picha za utambulisho hufutwa mara tu baada ya kulinganishwa.';
$string['consent_accept'] = 'Nimesoma na ninakubali masharti haya ya kurekodi';
$string['consent_decline'] = 'Ghairi na utoke';
$string['consent_proceed'] = 'Endelea na mtihani';
$string['identity_title'] = 'Hatua ya 1 — Uthibitishaji wa utambulisho';
$string['identity_id_label'] = 'Kitambulisho cha serikali (picha iliyo wazi, pembe zote 4 zionekane)';
$string['identity_selfie_label'] = 'Selfie (uso katikati, mwanga wa kutosha)';
$string['identity_submit'] = 'Thibitisha utambulisho';
$string['identity_processing'] = 'Inathibitisha — hii inachukua sekunde 10-30...';
$string['identity_passed'] = 'Utambulisho umethibitishwa (alama ya ulinganifu: {$a})';
$string['identity_failed'] = 'Uthibitishaji wa utambulisho umeshindikana. Tafadhali jaribu tena au wasiliana na timu ya msaada.';
$string['identity_lowmatch'] = 'Alama ya ulinganifu ni ya chini mno ({$a}). Tafadhali piga selfie upya kwenye mwanga mzuri zaidi.';
$string['monitor_title'] = 'Hatua ya 2 — Ufuatiliaji wa moja kwa moja unaendelea';
$string['monitor_camera'] = 'Kamera imewashwa';
$string['monitor_mic'] = 'Maikrofoni imewashwa';
$string['monitor_screen'] = 'Skrini inarekodiwa';
$string['monitor_lockwarn'] = 'Usiondoke kwenye tab hii ya kivinjari. Kubadilisha tab kunarekodiwa.';
$string['event_face_lost'] = 'Uso umetoka nje ya fremu';
$string['event_multiple_faces'] = 'Nyuso nyingi zimegunduliwa';
$string['event_tab_switch'] = 'Tab imebadilishwa';
$string['event_window_blur'] = 'Dirisha limepoteza focus';
$string['event_mic_noise'] = 'Kelele za nyuma zimeongezeka ghafla';
$string['event_clipboard_paste'] = 'Paste imegunduliwa';
$string['event_fullscreen_exit'] = 'Umetoka kwenye skrini nzima';
$string['event_session_start'] = 'Kipindi kimeanza';
$string['event_session_end'] = 'Kipindi kimeisha';
$string['review_pending'] = 'Inasubiri ukaguzi';
$string['review_in_progress'] = 'Inakaguliwa';
$string['review_completed'] = 'Imekaguliwa';
$string['review_decision'] = 'Uamuzi';
$string['review_decision_clean'] = 'Safi — hakuna tatizo';
$string['review_decision_warn'] = 'Onyo — flag ndogo ndogo';
$string['review_decision_fail'] = 'Udanganyifu umegunduliwa — jaribio limefeli';
$string['review_note'] = 'Maelezo ya mkaguzi';
$string['review_assign'] = 'Teua mkaguzi';
$string['status_new'] = 'Haijaanza';
$string['status_consenting'] = 'Inasubiri ridhaa';
$string['status_verifying'] = 'Inathibitisha utambulisho';
$string['status_recording'] = 'Inarekodi';
$string['status_finished'] = 'Imekamilika';
$string['status_flagged'] = 'Imewekewa flag kwa ukaguzi';
$string['status_reviewed'] = 'Imekaguliwa';
$string['settings_provider'] = 'Mtoa huduma wa uthibitishaji wa utambulisho';
$string['settings_provider_desc'] = 'aws = AWS Rekognition (production), mock = mock ya ndani (majaribio/dev). Weka kwa kila mazingira.';
$string['settings_aws_region'] = 'Region ya AWS';
$string['settings_aws_key'] = 'ID ya access key ya AWS';
$string['settings_aws_secret'] = 'Secret access key ya AWS';
$string['settings_aws_s3_bucket'] = 'S3 bucket ya rekodi';
$string['settings_match_threshold'] = 'Kiwango cha ulinganifu wa utambulisho (%)';
$string['settings_match_threshold_desc'] = 'Alama ya chini kabisa ya ulinganifu wa uso ili kupita hatua ya utambulisho. Chaguo-msingi 85.';
$string['settings_retention_days'] = 'Muda wa kuhifadhi rekodi (siku)';
$string['settings_retention_days_desc'] = 'Idadi ya siku ambazo rekodi huhifadhiwa kabla ya kufutwa. Chaguo-msingi 90.';
$string['settings_recording_chunk_secs'] = 'Ukubwa wa kipande cha rekodi (sekunde)';
$string['settings_recording_chunk_secs_desc'] = 'Vipande vya video hupakiwa kila baada ya muda gani. Chaguo-msingi sekunde 30.';
$string['settings_default_reviewer'] = 'User ID ya mkaguzi chaguo-msingi';
$string['settings_default_reviewer_desc'] = 'Mtumiaji anayepokea vipindi vilivyowekewa flag ikiwa hakuna mkaguzi maalum aliyeteuliwa.';
$string['messageprovider:session_flagged'] = 'Kipindi kinachosimamiwa kimewekewa flag kwa ukaguzi';
$string['messageprovider:session_reviewed'] = 'Kipindi chako kinachosimamiwa kimekaguliwa';
$string['messageprovider:identity_failed'] = 'Uthibitishaji wa utambulisho umeshindikana';
$string['error_consent_required'] = 'Ridhaa inahitajika kabla ya kuanza mtihani unaosimamiwa.';
$string['error_identity_required'] = 'Uthibitishaji wa utambulisho lazima ukamilike kabla ya kuanza.';
$string['error_no_provider'] = 'Hakuna mtoa huduma wa utambulisho aliyesanidiwa.';
$string['error_session_not_found'] = 'Kipindi cha usimamizi hakijapatikana.';
$string['error_session_state'] = 'Hali ya kipindi si sahihi kwa kitendo hiki.';
$string['error_review_not_allowed'] = 'Huna ruhusa ya kukagua kipindi hiki.';
$string['privacy:metadata:local_sentientia_proctor_sessions'] = 'Vipindi vya mitihani inayosimamiwa';
$string['privacy:metadata:local_sentientia_proctor_identity'] = 'Alama za uthibitishaji wa utambulisho (picha hufutwa baada ya kulinganishwa)';
$string['privacy:metadata:local_sentientia_proctor_events'] = 'Matukio ya tabia kwa kila jaribio';
$string['privacy:metadata:local_sentientia_proctor_recordings'] = 'Funguo za S3 za rekodi za webcam/skrini';
$string['privacy:metadata:local_sentientia_proctor_reviews'] = 'Maelezo na maamuzi ya wakaguzi binadamu';
$string['privacy:metadata:aws_rekognition'] = 'AWS Rekognition (kulinganisha uso kwa ajili ya utambulisho)';
$string['privacy:metadata:aws_rekognition:photo'] = 'Picha ya kitambulisho na selfie (hufutwa baada ya kulinganishwa, alama pekee huhifadhiwa)';
$string['privacy:metadata:aws_s3'] = 'AWS S3 (hifadhi ya rekodi)';
$string['privacy:metadata:aws_s3:video'] = 'Vipande vya video vya jaribio la mtihani';
