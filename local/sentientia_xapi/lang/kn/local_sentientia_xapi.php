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
 * Kannada strings for local_sentientia_xapi.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia xAPI / LRS';
$string['settings_pagetitle'] = 'Sentientia xAPI / LRS ಸೆಟ್ಟಿಂಗ್‌ಗಳು';
$string['setting_lrs_token'] = 'LRS Bearer ಟೋಕನ್';
$string['setting_lrs_token_desc'] = 'ಬಾಹ್ಯ xAPI ಕ್ಲೈಂಟ್‌ಗಳನ್ನು (ಉದಾ. SCORM ಕಂಟೆಂಟ್, cmi5 AU) ಆಥೆಂಟಿಕೇಟ್ ಮಾಡಲು ಬಳಸುವ ಸೀಕ್ರೆಟ್ ಟೋಕನ್. ಕನಿಷ್ಠ 32 ಅಕ್ಷರಗಳು. ಗೌಪ್ಯವಾಗಿಡಿ — ಸೋರಿಕೆಯಾಗಿರಬಹುದು ಎಂಬ ಅನುಮಾನ ಬಂದರೆ ತಕ್ಷಣ ಬದಲಾಯಿಸಿ.';
$string['setting_lrs_basic_user'] = 'LRS basic-auth ಯೂಸರ್‌ನೇಮ್';
$string['setting_lrs_basic_user_desc'] = 'Bearer ಟೋಕನ್ ಜೊತೆಗೆ /lrs/statements ಎಂಡ್‌ಪಾಯಿಂಟ್ ಸ್ವೀಕರಿಸುವ ಐಚ್ಛಿಕ HTTP Basic ಆಥೆಂಟಿಕೇಶನ್ ಯೂಸರ್‌ನೇಮ್. basic-auth ಆಫ್ ಮಾಡಲು ಖಾಲಿ ಬಿಡಿ.';
$string['setting_lrs_basic_pass'] = 'LRS basic-auth ಪಾಸ್‌ವರ್ಡ್';
$string['setting_lrs_basic_pass_desc'] = 'HTTP Basic ಆಥೆಂಟಿಕೇಶನ್ ಪಾಸ್‌ವರ್ಡ್ (ಮೇಲಿನ ಯೂಸರ್‌ನೇಮ್ ಜೊತೆ ಬಳಸಿ). basic-auth ಆಫ್ ಮಾಡಲು ಖಾಲಿ ಬಿಡಿ.';
$string['setting_retention_days'] = 'ಸ್ಟೇಟ್‌ಮೆಂಟ್ ಇಟ್ಟುಕೊಳ್ಳುವ ಅವಧಿ (ದಿನಗಳು)';
$string['setting_retention_days_desc'] = 'ಇಷ್ಟು ದಿನಗಳಿಗಿಂತ ಹಳೆಯ xAPI ಸ್ಟೇಟ್‌ಮೆಂಟ್‌ಗಳನ್ನು ರಾತ್ರಿಯ ಕ್ಲೀನಪ್ ಟಾಸ್ಕ್ ತೆರವುಗೊಳಿಸುತ್ತದೆ. ಶಾಶ್ವತವಾಗಿ ಇಟ್ಟುಕೊಳ್ಳಲು 0 ಹಾಕಿ. ಡೀಫಾಲ್ಟ್: 730 (2 ವರ್ಷ).';
$string['setting_emit_login'] = 'ಲಾಗಿನ್ ಆದಾಗ ಸ್ಟೇಟ್‌ಮೆಂಟ್ ಕಳುಹಿಸಿ';
$string['setting_emit_login_desc'] = 'ಆನ್ ಆಗಿದ್ದಾಗ, ಯೂಸರ್ ಲಾಗಿನ್ ಆದಾಗಲೆಲ್ಲಾ "experienced" ವರ್ಬ್ ಇರುವ ಸ್ಟೇಟ್‌ಮೆಂಟ್ ಕಳುಹಿಸಲಾಗುತ್ತದೆ. ಹೆಚ್ಚು ಟ್ರಾಫಿಕ್ ಇರುವ ಸೈಟ್‌ಗಳು ಇದನ್ನು ಆಫ್ ಮಾಡಬಹುದು.';
$string['setting_lrs_rate_limit'] = 'LRS ರೇಟ್ ಮಿತಿ (ಪ್ರತಿ ವಿಂಡೋಗೆ ರಿಕ್ವೆಸ್ಟ್‌ಗಳು)';
$string['setting_lrs_rate_limit_desc'] = 'ಆಥೆಂಟಿಕೇಟ್ ಆದ ಒಂದು ಕ್ಲೈಂಟ್ (ರಿಜಿಸ್ಟರ್ ಆದ ಕ್ಲೈಂಟ್ ಕ್ರೆಡೆನ್ಶಿಯಲ್, ಅಥವಾ ಸೈಟ್-ವೈಡ್ ಫಾಲ್‌ಬ್ಯಾಕ್ ಟೋಕನ್/basic ಕ್ರೆಡೆನ್ಶಿಯಲ್) ಪ್ರತಿ ವಿಂಡೋದಲ್ಲಿ ಮಾಡಬಹುದಾದ /lrs/statements ರಿಕ್ವೆಸ್ಟ್‌ಗಳ ಗರಿಷ್ಠ ಸಂಖ್ಯೆ. ಡೀಫಾಲ್ಟ್: 600.';
$string['setting_lrs_rate_window'] = 'LRS ರೇಟ್ ಮಿತಿ ವಿಂಡೋ (ಸೆಕೆಂಡ್‌ಗಳು)';
$string['setting_lrs_rate_window_desc'] = 'ನಿಗದಿತ ರೇಟ್-ಮಿತಿ ವಿಂಡೋದ ಅವಧಿ, ಸೆಕೆಂಡ್‌ಗಳಲ್ಲಿ. ಡೀಫಾಲ್ಟ್: 60 (ಅಂದರೆ ಡೀಫಾಲ್ಟ್ ಆಗಿ ಪ್ರತಿ ಕ್ಲೈಂಟ್‌ಗೆ 60 ಸೆಕೆಂಡ್‌ಗಳಲ್ಲಿ 600 ರಿಕ್ವೆಸ್ಟ್‌ಗಳು).';
$string['xapi:viewstatements'] = 'LRS ವ್ಯೂವರ್‌ನಲ್ಲಿ xAPI ಸ್ಟೇಟ್‌ಮೆಂಟ್‌ಗಳನ್ನು ನೋಡಿ';
$string['xapi:deletestatements'] = 'LRS ನಿಂದ xAPI ಸ್ಟೇಟ್‌ಮೆಂಟ್‌ಗಳನ್ನು ಡಿಲೀಟ್ ಮಾಡಿ';
$string['xapi:managelrs'] = 'LRS ಸೆಟ್ಟಿಂಗ್‌ಗಳು ಮತ್ತು ಕ್ರೆಡೆನ್ಶಿಯಲ್‌ಗಳನ್ನು ನಿರ್ವಹಿಸಿ';
$string['error_lrs_disabled'] = 'xAPI LRS ಸದ್ಯಕ್ಕೆ ಆಫ್ ಆಗಿದೆ. ನಿಮ್ಮ ಅಡ್ಮಿನ್ ಅನ್ನು ಸಂಪರ್ಕಿಸಿ.';
$string['error_lrs_auth'] = 'ಆಥೆಂಟಿಕೇಶನ್ ವಿಫಲವಾಗಿದೆ. ಸರಿಯಾದ Bearer ಟೋಕನ್ ಅಥವಾ Basic ಕ್ರೆಡೆನ್ಶಿಯಲ್‌ಗಳನ್ನು ನೀಡಿ.';
$string['error_lrs_invalid_json'] = 'ರಿಕ್ವೆಸ್ಟ್ ಬಾಡಿ ಸರಿಯಾದ JSON ಅಲ್ಲ.';
$string['error_lrs_invalid_stmt'] = 'ಸ್ಟೇಟ್‌ಮೆಂಟ್ ಪರಿಶೀಲನೆ ವಿಫಲವಾಗಿದೆ: {$a}';
$string['error_lrs_tenant'] = 'ಟೆನೆಂಟ್ ಪತ್ತೆಮಾಡಲು ಆಗಲಿಲ್ಲ. ಆಕ್ಟರ್ account ನ homePage ರಿಜಿಸ್ಟರ್ ಆದ ಟೆನೆಂಟ್‌ಗೆ ಹೊಂದಿಕೆಯಾಗುತ್ತದೆಯೇ ಎಂದು ಖಚಿತಪಡಿಸಿಕೊಳ್ಳಿ.';
$string['error_lrs_method'] = 'ಈ ಎಂಡ್‌ಪಾಯಿಂಟ್‌ನಲ್ಲಿ ಈ HTTP ಮೆಥಡ್‌ಗೆ ಬೆಂಬಲವಿಲ್ಲ.';
$string['error_lrs_not_found'] = 'ಸ್ಟೇಟ್‌ಮೆಂಟ್ ಸಿಗಲಿಲ್ಲ.';
$string['error_lrs_ratelimited'] = 'ರೇಟ್ ಮಿತಿ ಮೀರಿದೆ: ಈ ಕ್ಲೈಂಟ್‌ಗೆ ಪ್ರತಿ ವಿಂಡೋಗೆ {$a} ರಿಕ್ವೆಸ್ಟ್‌ಗಳ ಮಿತಿ ಇದೆ. Retry-After ಅವಧಿ ಮುಗಿದ ನಂತರ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ.';
$string['verb_completed'] = 'ಪೂರ್ಣಗೊಳಿಸಿದರು';
$string['verb_experienced'] = 'ಅನುಭವ ಪಡೆದರು';
$string['verb_passed'] = 'ಪಾಸ್ ಆದರು';
$string['verb_failed'] = 'ಫೇಲ್ ಆದರು';
$string['verb_attempted'] = 'ಪ್ರಯತ್ನಿಸಿದರು';
$string['verb_answered'] = 'ಉತ್ತರಿಸಿದರು';
$string['verb_launched'] = 'ಲಾಂಚ್ ಮಾಡಿದರು';
$string['verb_initialized'] = 'ಆರಂಭಿಸಿದರು';
$string['verb_terminated'] = 'ಕೊನೆಗೊಳಿಸಿದರು';
$string['verb_suspended'] = 'ವಿರಾಮಗೊಳಿಸಿದರು';
$string['verb_resumed'] = 'ಮುಂದುವರಿಸಿದರು';
$string['verb_satisfied'] = 'ಪೂರೈಸಿದರು';
$string['validate_actor_required'] = 'ಆಕ್ಟರ್ ಕಡ್ಡಾಯ.';
$string['validate_actor_missing_objecttype'] = 'ಆಕ್ಟರ್‌ಗೆ objectType Agent ಅಥವಾ Group ಇರಬೇಕು.';
$string['validate_actor_missing_ifi'] = 'ಆಕ್ಟರ್‌ಗೆ ನಿಖರವಾಗಿ ಒಂದೇ IFI (mbox, mbox_sha1sum, openid, ಅಥವಾ account) ಇರಬೇಕು.';
$string['validate_actor_mbox_format'] = 'ಆಕ್ಟರ್ mbox ಒಂದು mailto: URI ಆಗಿರಬೇಕು.';
$string['validate_actor_account_missing'] = 'ಆಕ್ಟರ್ account ನಲ್ಲಿ homePage ಮತ್ತು name ಇರಬೇಕು.';
$string['validate_verb_required'] = 'ವರ್ಬ್ ಕಡ್ಡಾಯ.';
$string['validate_verb_id_required'] = 'ವರ್ಬ್‌ಗೆ ಒಂದು id (IRI) ಇರಬೇಕು.';
$string['validate_verb_id_iri'] = 'ವರ್ಬ್ id ಸರಿಯಾದ IRI ಆಗಿರಬೇಕು.';
$string['validate_object_required'] = 'ಆಬ್ಜೆಕ್ಟ್ ಕಡ್ಡಾಯ.';
$string['validate_object_id_required'] = 'ಆಬ್ಜೆಕ್ಟ್‌ಗೆ ಒಂದು id (IRI) ಇರಬೇಕು.';
$string['validate_object_id_iri'] = 'ಆಬ್ಜೆಕ್ಟ್ id ಸರಿಯಾದ IRI ಆಗಿರಬೇಕು.';
$string['validate_result_score_range'] = 'ಫಲಿತಾಂಶದ ಸ್ಕೋರ್‌ನ scaled ಮೌಲ್ಯ -1.0 ಮತ್ತು 1.0 ನಡುವೆ ಇರಬೇಕು.';
$string['validate_result_score_raw_max'] = 'ಫಲಿತಾಂಶದ ಸ್ಕೋರ್‌ನ raw ಮೌಲ್ಯ max ಅನ್ನು ಮೀರಬಾರದು.';
$string['validate_context_registration_uuid'] = 'ಕಾಂಟೆಕ್ಸ್ಟ್ registration ಸರಿಯಾದ UUID ಆಗಿರಬೇಕು.';
$string['validate_timestamp_format'] = 'ಟೈಮ್‌ಸ್ಟ್ಯಾಂಪ್ ಸರಿಯಾದ ISO 8601 ದಿನಾಂಕ-ಸಮಯ ಸ್ಟ್ರಿಂಗ್ ಆಗಿರಬೇಕು.';
$string['validate_id_uuid'] = 'ಸ್ಟೇಟ್‌ಮೆಂಟ್ id ಸರಿಯಾದ UUID ಆಗಿರಬೇಕು.';
$string['cmi5_session'] = 'cmi5 ಸೆಷನ್';
$string['cmi5_session_initialized'] = 'ಸೆಷನ್ ಆರಂಭವಾಗಿದೆ';
$string['cmi5_session_terminated'] = 'ಸೆಷನ್ ಮುಗಿದಿದೆ';
$string['cmi5_au_passed'] = 'ಅಸೈನಬಲ್ ಯೂನಿಟ್ ಪಾಸ್ ಆಗಿದೆ';
$string['cmi5_au_failed'] = 'ಅಸೈನಬಲ್ ಯೂನಿಟ್ ಫೇಲ್ ಆಗಿದೆ';
$string['cmi5_au_completed'] = 'ಅಸೈನಬಲ್ ಯೂನಿಟ್ ಪೂರ್ಣಗೊಂಡಿದೆ';
$string['lrs_viewer_title'] = 'xAPI ಸ್ಟೇಟ್‌ಮೆಂಟ್ ವ್ಯೂವರ್';
$string['lrs_viewer_heading'] = 'LRS — ಇತ್ತೀಚಿನ ಸ್ಟೇಟ್‌ಮೆಂಟ್‌ಗಳು';
$string['lrs_col_timestamp'] = 'ಟೈಮ್‌ಸ್ಟ್ಯಾಂಪ್';
$string['lrs_col_actor'] = 'ಆಕ್ಟರ್';
$string['lrs_col_verb'] = 'ವರ್ಬ್';
$string['lrs_col_object'] = 'ಆಬ್ಜೆಕ್ಟ್';
$string['lrs_col_score'] = 'ಸ್ಕೋರ್';
$string['lrs_col_success'] = 'ಯಶಸ್ವಿ';
$string['lrs_col_tenant'] = 'ಟೆನೆಂಟ್';
$string['lrs_no_statements'] = 'ಇನ್ನೂ ಯಾವುದೇ ಸ್ಟೇಟ್‌ಮೆಂಟ್ ದಾಖಲಾಗಿಲ್ಲ.';
$string['lrs_endpoint_label'] = 'LRS ಎಂಡ್‌ಪಾಯಿಂಟ್ URL';
$string['lrs_endpoint_desc'] = 'ನಿಮ್ಮ xAPI / cmi5 ಕಂಟೆಂಟ್ ಅಥವಾ LRS ಕ್ಲೈಂಟ್‌ನಲ್ಲಿ ಈ URL ಅನ್ನು LRS ಎಂಡ್‌ಪಾಯಿಂಟ್ ಆಗಿ ಬಳಸಿ.';
$string['privacy:metadata:local_sentientia_xapi_statements'] = 'ಈ ಯೂಸರ್ ಕಳುಹಿಸಿದ ಅಥವಾ ಈ ಯೂಸರ್‌ಗಾಗಿ ಕಳುಹಿಸಲಾದ xAPI ಸ್ಟೇಟ್‌ಮೆಂಟ್‌ಗಳು.';
$string['privacy:metadata:local_sentientia_xapi_statements:actorid'] = 'xAPI ಆಕ್ಟರ್‌ಗೆ ಲಿಂಕ್ ಆಗಿರುವ Moodle ಯೂಸರ್ id.';
$string['privacy:metadata:local_sentientia_xapi_statements:actor'] = 'JSON ಆಕ್ಟರ್ ಆಬ್ಜೆಕ್ಟ್ (ಇಮೇಲ್ ಅಥವಾ ಖಾತೆಯ ಐಡೆಂಟಿಫೈಯರ್ ಇರಬಹುದು).';
$string['privacy:metadata:local_sentientia_xapi_statements:verb'] = 'xAPI ವರ್ಬ್ IRI.';
$string['privacy:metadata:local_sentientia_xapi_statements:object'] = 'JSON ಆಬ್ಜೆಕ್ಟ್ (ಚಟುವಟಿಕೆ, ಏಜೆಂಟ್, ಇತ್ಯಾದಿ).';
$string['privacy:metadata:local_sentientia_xapi_statements:result'] = 'JSON ಫಲಿತಾಂಶ (ಸ್ಕೋರ್, ಯಶಸ್ಸು, ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ).';
$string['privacy:metadata:local_sentientia_xapi_statements:context'] = 'JSON ಕಾಂಟೆಕ್ಸ್ಟ್.';
$string['privacy:metadata:local_sentientia_xapi_statements:timestored'] = 'LRS ನಲ್ಲಿ ಸ್ಟೇಟ್‌ಮೆಂಟ್ ಸೇವ್ ಆದ ಸಮಯದ ಟೈಮ್‌ಸ್ಟ್ಯಾಂಪ್.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions'] = 'cmi5 ಸೆಷನ್ ಟ್ರ್ಯಾಕಿಂಗ್ ದಾಖಲೆಗಳು.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:userid'] = 'Moodle ಯೂಸರ್ id.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:registration'] = 'cmi5 ರಿಜಿಸ್ಟ್ರೇಶನ್ UUID.';
$string['task_purge_old_statements'] = 'ಹಳೆಯ xAPI ಸ್ಟೇಟ್‌ಮೆಂಟ್‌ಗಳನ್ನು ತೆರವುಗೊಳಿಸಿ';
