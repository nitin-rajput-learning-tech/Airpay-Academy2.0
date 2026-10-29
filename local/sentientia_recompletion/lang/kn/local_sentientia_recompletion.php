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
 * Kannada strings for local_sentientia_recompletion.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ';
$string['rules'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ನಿಯಮಗಳು';
$string['history'] = 'ರೀಸೆಟ್ ಹಿಸ್ಟರಿ';
$string['bulkreset'] = 'ಬಲ್ಕ್ ರೀಸೆಟ್';
$string['sentientia_recompletion:view'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ನಿಯಮಗಳು ಮತ್ತು ಹಿಸ್ಟರಿಯನ್ನು ನೋಡಿ';
$string['sentientia_recompletion:manage'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ನಿಯಮಗಳನ್ನು ನಿರ್ವಹಿಸಿ';
$string['sentientia_recompletion:reset'] = 'ಯೂಸರ್‌ಗಳ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯನ್ನು ಕೈಯಾರೆ ರೀಸೆಟ್ ಮಾಡಿ';
$string['enabled'] = 'ಆನ್ ಆಗಿದೆ';
$string['disabled'] = 'ಆಫ್ ಆಗಿದೆ';
$string['running'] = 'ರನ್ ಆಗುತ್ತಿದೆ';
$string['settings_pre_notify_days'] = 'ಮುಂಚಿತ ನೋಟಿಫಿಕೇಶನ್ ಅವಧಿ (ದಿನಗಳು)';
$string['settings_pre_notify_days_desc'] = 'ಯೂಸರ್‌ಗಳ ಕಂಪ್ಲೈಯನ್ಸ್ ಅವಧಿ ಮುಗಿಯುವುದಕ್ಕೆ ಇಷ್ಟು ದಿನಗಳ ಮುಂಚೆಯೇ ಅವರಿಗೆ ನೋಟಿಫೈ ಮಾಡಿ. ಡೀಫಾಲ್ಟ್ 30.';
$string['settings_max_batch'] = 'ಪ್ರತಿ cron ರನ್‌ನಲ್ಲಿ ರೀಸೆಟ್ ಮಾಡಬಹುದಾದ ಗರಿಷ್ಠ ಯೂಸರ್‌ಗಳು';
$string['settings_max_batch_desc'] = 'ತಪ್ಪಾಗಿ ಕಾನ್ಫಿಗರ್ ಮಾಡಿದ ನಿಯಮವು ಒಂದೇ cron ರನ್‌ನಲ್ಲಿ ಸಾವಿರಾರು ಯೂಸರ್‌ಗಳನ್ನು ರೀಸೆಟ್ ಮಾಡದಂತೆ ತಡೆಯುವ ಸುರಕ್ಷತಾ ಮಿತಿ. ಡೀಫಾಲ್ಟ್ 500.';
$string['settings_dry_run_default'] = 'ಡ್ರೈ-ರನ್ ಮೋಡ್ (ಡೀಫಾಲ್ಟ್ ಆಫ್)';
$string['settings_dry_run_default_desc'] = 'ಆನ್ ಆಗಿದ್ದಾಗ, ದೈನಂದಿನ cron ಏನನ್ನು ರೀಸೆಟ್ ಮಾಡುತ್ತಿತ್ತು ಎಂಬುದನ್ನು ಲಾಗ್ ಮಾಡುತ್ತದೆ, ಆದರೆ ನಿಜವಾಗಿ ಏನನ್ನೂ ರೀಸೆಟ್ ಮಾಡುವುದಿಲ್ಲ. ಹೊಸ ನಿಯಮಗಳನ್ನು ಟೆಸ್ಟ್ ಮಾಡಲು ಉಪಯುಕ್ತ.';
$string['rule_name'] = 'ನಿಯಮದ ಹೆಸರು';
$string['rule_courseid'] = 'ಕೋರ್ಸ್ (ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಆನ್ ಆಗಿರುವ ಎಲ್ಲಾ ಕೋರ್ಸ್‌ಗಳಿಗೆ 0 ಎಂದೇ ಬಿಡಿ)';
$string['rule_period_days'] = 'ರೀಸೆಟ್ ಅವಧಿ (ದಿನಗಳು)';
$string['rule_period_days_help'] = 'ಪ್ರತಿ N ದಿನಗಳಿಗೊಮ್ಮೆ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯನ್ನು ರೀಸೆಟ್ ಮಾಡಿ. 365 = ವಾರ್ಷಿಕ, 90 = ತ್ರೈಮಾಸಿಕ. ಆಫ್ ಮಾಡಲು 0 ಹಾಕಿ.';
$string['rule_trigger'] = 'ಟ್ರಿಗರ್';
$string['rule_trigger_completion'] = 'ಪೂರ್ಣಗೊಳಿಸಿದ N ದಿನಗಳ ನಂತರ';
$string['rule_trigger_enrolment'] = 'ಎನ್‌ರೋಲ್ ಆದ N ದಿನಗಳ ನಂತರ';
$string['rule_trigger_fixed'] = 'ನಿಗದಿತ ಕ್ಯಾಲೆಂಡರ್ ದಿನಾಂಕದಂದು';
$string['rule_fixed_date'] = 'ನಿಗದಿತ ದಿನಾಂಕ (ಟ್ರಿಗರ್ = ನಿಗದಿತ ಆಗಿದ್ದರೆ)';
$string['rule_reset_grades'] = 'ಗ್ರೇಡ್‌ಗಳನ್ನೂ ರೀಸೆಟ್ ಮಾಡಬೇಕೇ?';
$string['rule_reset_attempts'] = 'ಕ್ವಿಜ್ ಪ್ರಯತ್ನಗಳನ್ನೂ ರೀಸೆಟ್ ಮಾಡಬೇಕೇ?';
$string['rule_enabled'] = 'ಆನ್ ಆಗಿದೆ';
$string['messageprovider:recompletion_due_soon'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಡೆಡ್‌ಲೈನ್ ಹತ್ತಿರ';
$string['messageprovider:recompletion_reset'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ರೀಸೆಟ್ (ಪೂರ್ಣಗೊಂಡಿದೆ)';
$string['msg_reset_subject'] = 'ಮತ್ತೆ ಪೂರ್ಣಗೊಳಿಸಿ: \'{$a->course}\' ರೀಸೆಟ್ ಆಗಿದೆ';
$string['msg_reset_body'] = '\'{$a->course}\' ಅನ್ನು ನೀವು ಹಿಂದೆ {$a->previous} ರಂದು ಪೂರ್ಣಗೊಳಿಸಿದ್ದೀರಿ. {$a->days} ದಿನಗಳಿಗೊಮ್ಮೆ ಮತ್ತೆ ಪೂರ್ಣಗೊಳಿಸುವ ನಿಯಮದ ಪ್ರಕಾರ, ಕಂಪ್ಲೈಯನ್ಸ್ ಉಳಿಸಿಕೊಳ್ಳಲು ನೀವು ಇದನ್ನು ಮತ್ತೆ ಪೂರ್ಣಗೊಳಿಸಬೇಕು.';
$string['msg_due_subject'] = 'ಮತ್ತೆ ಪೂರ್ಣಗೊಳಿಸಲು {$a->days} ದಿನ ಬಾಕಿ: \'{$a->course}\'';
$string['msg_due_body'] = 'ಗಮನಿಸಿ — \'{$a->course}\' ನಲ್ಲಿ ನಿಮ್ಮ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯ ಅವಧಿ {$a->days} ದಿನಗಳಲ್ಲಿ ಮುಗಿಯುತ್ತದೆ. ಕಂಪ್ಲೈಯನ್ಸ್ ಉಳಿಸಿಕೊಳ್ಳಲು ಅದಕ್ಕೂ ಮೊದಲೇ ಇದನ್ನು ಮತ್ತೆ ಪೂರ್ಣಗೊಳಿಸಲು ಪ್ಲಾನ್ ಮಾಡಿ.';
$string['event_completion_reset'] = 'ಕೋರ್ಸ್ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ರೀಸೆಟ್';
$string['nrules'] = '{$a} ನಿಯಮಗಳು';
$string['rules_empty'] = 'ಇನ್ನೂ ಯಾವುದೇ ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ನಿಯಮ ಕಾನ್ಫಿಗರ್ ಆಗಿಲ್ಲ.';
$string['history_empty'] = 'ಇನ್ನೂ ಯಾವುದೇ ರೀಸೆಟ್ ಆಗಿಲ್ಲ.';
$string['no_courses_resetable'] = 'ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಟ್ರ್ಯಾಕಿಂಗ್ ಆನ್ ಆಗಿರುವ ಯಾವುದೇ ಕೋರ್ಸ್ ಇಲ್ಲ — ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಗೆ ಕೋರ್ಸ್ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಕಾನ್ಫಿಗರ್ ಆಗಿರಬೇಕು.';
$string['history_title'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಹಿಸ್ಟರಿ';
$string['history_back'] = 'ನಿಯಮಗಳಿಗೆ ಹಿಂತಿರುಗಿ';
$string['history_prev'] = 'ಹಿಂದಿನ';
$string['history_next'] = 'ಮುಂದಿನ';
$string['history_filtered'] = 'ಒಂದು ಕೋರ್ಸ್ ಅಥವಾ ಒಬ್ಬ ಕಲಿಯುವವರನ್ನು ಮಾತ್ರ ತೋರಿಸಲಾಗುತ್ತಿದೆ.';
$string['history_clear_filter'] = 'ಎಲ್ಲಾ ರೀಸೆಟ್‌ಗಳನ್ನು ತೋರಿಸಿ';
$string['hcol_when'] = 'ಯಾವಾಗ';
$string['hcol_user'] = 'ಯೂಸರ್';
$string['hcol_course'] = 'ಕೋರ್ಸ್';
$string['hcol_reason'] = 'ಕಾರಣ';
$string['hcol_previous'] = 'ಹಿಂದಿನ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ';
$string['hcol_grades'] = 'ಗ್ರೇಡ್‌ಗಳು ರೀಸೆಟ್?';
$string['hcol_attempts'] = 'ಪ್ರಯತ್ನಗಳು ರೀಸೆಟ್?';
$string['badge_dryrun'] = 'ಡ್ರೈ-ರನ್';
$string['badge_legacy'] = 'ಲೆಗಸಿ';
$string['badge_self'] = 'ಸ್ವಯಂ';
$string['badge_deleted_user'] = 'ಡಿಲೀಟ್ ಆದ ಯೂಸರ್';
$string['badge_inferred'] = 'ಅಂದಾಜು';
$string['badge_inferred_title'] = 'ಈ ರೀಸೆಟ್‌ನ ಲಾಗ್ ಸಾಲು ಸಿಗಲಿಲ್ಲ, ಆದ್ದರಿಂದ ಅದರ ಸಮಯವನ್ನು ಅದು ಕೊನೆಗೊಳಿಸಿದ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯಿಂದ ಲೆಕ್ಕಹಾಕಲಾಗಿದೆ.';
$string['link_evidence'] = 'ಪುರಾವೆ';
$string['badge_legacy_rule'] = 'BizLMS ನಿಂದ ಇಂಪೋರ್ಟ್ ಮಾಡಿದ್ದು';
$string['legacy_settings'] = 'ಈ ಕೋರ್ಸ್‌ನ BizLMS ಸೆಟ್ಟಿಂಗ್‌ಗಳು';
$string['legacy_unfinished_parity'] = 'ಈ ನಿಯಮ BizLMS ನಿಂದ ಬಂದಿದೆ. Sentientia ಎಂಜಿನ್ ಇನ್ನೂ ಅದರ ಎಲ್ಲಾ ಸೆಟ್ಟಿಂಗ್‌ಗಳನ್ನು ಅದೇ ರೀತಿ ಜಾರಿಗೊಳಿಸುವುದಿಲ್ಲ: SCORM ಟ್ರ್ಯಾಕಿಂಗ್ ಅನ್ನು ಯಾವಾಗಲೂ ತೆರವುಗೊಳಿಸಲಾಗುತ್ತದೆ, ಅವಧಿಯನ್ನು ಪೂರ್ಣ ದಿನಗಳಲ್ಲಿ ಎಣಿಸಲಾಗುತ್ತದೆ, ಮತ್ತು ಹೆಚ್ಚುವರಿ ಪ್ರಯತ್ನ, ಅಸೈನ್‌ಮೆಂಟ್, LTI, ಪ್ರಶ್ನಾವಳಿ ಮತ್ತು ಕಸ್ಟಮ್ ಇಮೇಲ್ ಆಯ್ಕೆಗಳನ್ನು ಕೇವಲ ಮಾಹಿತಿಗಾಗಿ ಇಡಲಾಗಿದೆ. ಆ ಕಾರಣಕ್ಕೆ ಇದನ್ನು ಇನ್ನೂ ಆನ್ ಮಾಡಲು ಸಾಧ್ಯವಿಲ್ಲ. ಕಲಿಯುವವರನ್ನು ಈಗಲೇ ರೀಸೆಟ್ ಮಾಡಲು, ನಿಮ್ಮದೇ ಒಂದು ನಿಯಮವನ್ನು ರಚಿಸಿ: ಅದು ಕಾನ್ಫಿಗರ್ ಮಾಡಿದಂತೆಯೇ ಕೆಲಸ ಮಾಡುತ್ತದೆ.';
$string['legacy_enable_blocked'] = 'ಈ ನಿಯಮ BizLMS ನಿಂದ ಬಂದಿದೆ ಮತ್ತು ಇದನ್ನು ಇನ್ನೂ ಆನ್ ಮಾಡಲು ಸಾಧ್ಯವಿಲ್ಲ: Sentientia ಎಂಜಿನ್ ಅದರ ಎಲ್ಲಾ ಸೆಟ್ಟಿಂಗ್‌ಗಳನ್ನು ಅದೇ ರೀತಿ ಜಾರಿಗೊಳಿಸುವುದಿಲ್ಲ (ನಿಯಮದ ಮೇಲಿನ ಸೂಚನೆಯನ್ನು ನೋಡಿ). ಕಲಿಯುವವರನ್ನು ರೀಸೆಟ್ ಮಾಡಲು ನಿಮ್ಮದೇ ಒಂದು ನಿಯಮವನ್ನು ರಚಿಸಿ; ಅದು ಕಾನ್ಫಿಗರ್ ಮಾಡಿದಂತೆಯೇ ಕೆಲಸ ಮಾಡುತ್ತದೆ.';
$string['legacy_dead_scorm'] = 'ಈ ಕೋರ್ಸ್‌ನಲ್ಲಿ ಹಳೆಯ SCORM ಸೆಟ್ಟಿಂಗ್ ಹೆಸರು (deletescormdata) ಮಾತ್ರ ಇತ್ತು, ಅದನ್ನು BizLMS ಪ್ಲಗಿನ್ ಎಂದಿಗೂ ಓದಲಿಲ್ಲ: SCORM ಗಾಗಿ ಅದು ಏನನ್ನೂ ಮಾಡಲಿಲ್ಲ.';
$string['legacy_days'] = '{$a} ದಿನಗಳು';
$string['legacy_choice_0'] = 'ಏನೂ ಇಲ್ಲ';
$string['legacy_choice_1'] = 'ಡಿಲೀಟ್ ಮಾಡಿ';
$string['legacy_choice_2'] = 'ಒಂದು ಹೆಚ್ಚುವರಿ ಪ್ರಯತ್ನಕ್ಕೆ ಅವಕಾಶ ನೀಡಿ';
$string['legacy_enable'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಆನ್ ಆಗಿತ್ತು';
$string['legacy_recompletionduration'] = 'ಅವಧಿ';
$string['legacy_deletegradedata'] = 'ಗ್ರೇಡ್‌ಗಳನ್ನು ಡಿಲೀಟ್ ಮಾಡಿ';
$string['legacy_archivecompletiondata'] = 'ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಡೇಟಾವನ್ನು ಆರ್ಕೈವ್ ಮಾಡಿ';
$string['legacy_quiz'] = 'ಕ್ವಿಜ್ ಪ್ರಯತ್ನಗಳು';
$string['legacy_archivequiz'] = 'ಕ್ವಿಜ್ ಪ್ರಯತ್ನಗಳನ್ನು ಆರ್ಕೈವ್ ಮಾಡಿ';
$string['legacy_scorm'] = 'SCORM';
$string['legacy_archivescorm'] = 'SCORM ಡೇಟಾವನ್ನು ಆರ್ಕೈವ್ ಮಾಡಿ';
$string['legacy_assign'] = 'ಅಸೈನ್‌ಮೆಂಟ್‌ಗಳು';
$string['legacy_lti'] = 'LTI ಗ್ರೇಡ್‌ಗಳು';
$string['legacy_archivelti'] = 'LTI ಗ್ರೇಡ್‌ಗಳನ್ನು ಆರ್ಕೈವ್ ಮಾಡಿ';
$string['legacy_questionnaire'] = 'ಪ್ರಶ್ನಾವಳಿ ಪ್ರತಿಕ್ರಿಯೆಗಳು';
$string['legacy_archivequestionnaire'] = 'ಪ್ರಶ್ನಾವಳಿ ಪ್ರತಿಕ್ರಿಯೆಗಳನ್ನು ಆರ್ಕೈವ್ ಮಾಡಿ';
$string['legacy_pulse'] = 'Pulse ನೋಟಿಫಿಕೇಶನ್‌ಗಳು';
$string['legacy_recompletionemailenable'] = 'ರೀಸೆಟ್ ಆದಾಗ ಕಲಿಯುವವರಿಗೆ ಇಮೇಲ್ ಕಳುಹಿಸಿ';
$string['evidence_title'] = 'ರೀಸೆಟ್ ಪುರಾವೆ';
$string['evidence_back'] = 'ಹಿಸ್ಟರಿಗೆ ಹಿಂತಿರುಗಿ';
$string['evidence_learner'] = 'ಕಲಿಯುವವರು';
$string['evidence_course'] = 'ಕೋರ್ಸ್';
$string['evidence_reset_at'] = 'ರೀಸೆಟ್ ಸಮಯ';
$string['evidence_reason'] = 'ಕಾರಣ';
$string['evidence_reset_by'] = 'ರೀಸೆಟ್ ಮಾಡಿದವರು';
$string['evidence_previous'] = 'ರೀಸೆಟ್‌ಗೆ ಮೊದಲು ಪೂರ್ಣಗೊಂಡಿದ್ದು';
$string['evidence_grades_reset'] = 'ಗ್ರೇಡ್‌ಗಳು ರೀಸೆಟ್ ಆಗಿವೆ';
$string['evidence_attempts_reset'] = 'ಕ್ವಿಜ್ ಪ್ರಯತ್ನಗಳು ರೀಸೆಟ್ ಆಗಿವೆ';
$string['evidence_inferred'] = 'ಅಂದಾಜು';
$string['evidence_inferred_note'] = 'ಈ ರೀಸೆಟ್‌ನ ಸಮಯ ಅಂದಾಜು ಮಾತ್ರ. ರೀಸೆಟ್‌ನ ಲಾಗ್ ಸಾಲು ಸಿಗಲಿಲ್ಲ, ಆದ್ದರಿಂದ (ಪೂರ್ಣಗೊಳಿಸಿದ ಸಮಯ + ನಿಯಮದ ಅವಧಿ) ಮತ್ತು ಮುಂದಿನ ಸೈಕಲ್‌ನ ಮೊದಲ ಪುರಾವೆ — ಇವೆರಡರಲ್ಲಿ ಯಾವುದು ಮೊದಲೋ ಅದನ್ನೇ ಸಮಯವಾಗಿ ತೆಗೆದುಕೊಳ್ಳಲಾಗಿದೆ.';
$string['evidence_unattached_note'] = 'ಈ ಪುರಾವೆಯನ್ನು ಯಾವುದೇ ರೀಸೆಟ್‌ಗೆ ಜೋಡಿಸಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ: ರೀಸೆಟ್‌ನ ಲಾಗ್ ಸಾಲನ್ನು ತೆರವುಗೊಳಿಸಲಾಗಿದೆ ಮತ್ತು ಅದನ್ನು ಲೆಕ್ಕಹಾಕಲು ಯಾವುದೇ ಆರ್ಕೈವ್ ಮಾಡಿದ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಉಳಿದಿರಲಿಲ್ಲ.';
$string['evidence_none'] = 'ಈ ರೀಸೆಟ್‌ಗೆ ಯಾವುದೇ ಪುರಾವೆ ಆರ್ಕೈವ್ ಆಗಿಲ್ಲ.';
$string['evidence_more'] = 'ಇನ್ನೂ {$a} ಸಾಲುಗಳನ್ನು ತೋರಿಸಲಾಗಿಲ್ಲ.';
$string['evidence_redacted'] = '(ಮರೆಮಾಡಲಾಗಿದೆ)';
$string['evidence_course_gone'] = '(ಡಿಲೀಟ್ ಆದ ಕೋರ್ಸ್)';
$string['evidence_scheduled'] = 'ಶೆಡ್ಯೂಲ್ಡ್ ಟಾಸ್ಕ್';
$string['evidence_self'] = 'ಕಲಿಯುವವರು';
$string['evidence_admin'] = 'ಒಬ್ಬ ಅಡ್ಮಿನ್';
$string['evidence_admin_erased'] = 'ಒಬ್ಬ ಅಡ್ಮಿನ್ (ಅಳಿಸಲಾಗಿದೆ)';
$string['evidence_not_recorded'] = 'ದಾಖಲಾಗಿಲ್ಲ';
$string['evidence_view_off'] = 'ರೀಸೆಟ್ ಪುರಾವೆ ವೀಕ್ಷಣೆ ಆನ್ ಆಗಿಲ್ಲ.';
$string['evidence_not_found'] = 'ಅಂತಹ ರೀಸೆಟ್ ಇಲ್ಲ, ಅಥವಾ ಅದು ಬೇರೆ ಟೆನೆಂಟ್‌ಗೆ ಸೇರಿದೆ.';
$string['evidence_type_course_completion'] = 'ಕೋರ್ಸ್ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ';
$string['evidence_type_criteria_completion'] = 'ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ಮಾನದಂಡಗಳು';
$string['evidence_type_activity_completion'] = 'ಚಟುವಟಿಕೆ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಗಳು';
$string['evidence_type_quiz_attempt'] = 'ಕ್ವಿಜ್ ಪ್ರಯತ್ನಗಳು';
$string['evidence_type_quiz_grade'] = 'ಕ್ವಿಜ್ ಗ್ರೇಡ್‌ಗಳು';
$string['evidence_type_scorm_track'] = 'SCORM ಟ್ರ್ಯಾಕಿಂಗ್';
$string['evidence_type_lti_grade'] = 'LTI ಗ್ರೇಡ್‌ಗಳು';
$string['evidence_type_questionnaire_response'] = 'ಪ್ರಶ್ನಾವಳಿ ಪ್ರತಿಕ್ರಿಯೆಗಳು';
$string['evidence_type_questionnaire_answer'] = 'ಪ್ರಶ್ನಾವಳಿ ಉತ್ತರಗಳು';
$string['evidence_type_gradebook_grade'] = 'ಗ್ರೇಡ್‌ಗಳು';
$string['col_state'] = 'ಸ್ಥಿತಿ';
$string['col_enrolled'] = 'ಎನ್‌ರೋಲ್ ಆಗಿದೆ';
$string['col_started'] = 'ಶುರುವಾಗಿದೆ';
$string['col_completed'] = 'ಪೂರ್ಣಗೊಂಡಿದೆ';
$string['col_criteria'] = 'ಮಾನದಂಡ';
$string['col_grade'] = 'ಗ್ರೇಡ್';
$string['col_when'] = 'ಯಾವಾಗ';
$string['col_activity'] = 'ಚಟುವಟಿಕೆ';
$string['col_quiz'] = 'ಕ್ವಿಜ್';
$string['col_attempt'] = 'ಪ್ರಯತ್ನ';
$string['col_marks'] = 'ಅಂಕಗಳು';
$string['col_scorm'] = 'SCORM';
$string['col_element'] = 'ಎಲಿಮೆಂಟ್';
$string['col_value'] = 'ಮೌಲ್ಯ';
$string['col_tool'] = 'LTI ಟೂಲ್';
$string['col_questionnaire'] = 'ಪ್ರಶ್ನಾವಳಿ';
$string['col_question'] = 'ಪ್ರಶ್ನೆ';
$string['col_answer'] = 'ಉತ್ತರ';
$string['col_item'] = 'ಗ್ರೇಡ್ ಐಟಂ';
$string['privacy:metadata:local_sentientia_recompletion_rules'] = 'ಮರು-ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ನಿಯಮಗಳ ವಿವರಗಳು';
$string['privacy:metadata:local_sentientia_recompletion_history'] = 'ಪ್ರತಿ ಯೂಸರ್‌ನ ರೀಸೆಟ್ ಆಡಿಟ್ ಲಾಗ್';
$string['privacy:metadata:local_sentientia_recompletion_history:userid'] = 'ಯಾರ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ ರೀಸೆಟ್ ಆಯಿತೋ ಆ ಯೂಸರ್';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'ರೀಸೆಟ್ ಆದ ಕೋರ್ಸ್';
$string['privacy:metadata:local_sentientia_recompletion_history:reason'] = 'ರೀಸೆಟ್ ಏಕೆ ನಡೆಯಿತು';
$string['privacy:metadata:local_sentientia_recompletion_history:reset_by_userid'] = 'ಇನ್ನೊಬ್ಬರ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯನ್ನು ರೀಸೆಟ್ ಮಾಡಿದ ಅಡ್ಮಿನ್ (ಶೆಡ್ಯೂಲ್ಡ್ ಟಾಸ್ಕ್ ಮಾಡಿದ್ದರೆ ಖಾಲಿ)';
$string['privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted'] = 'ರೀಸೆಟ್ ಆಗುವ ಮೊದಲು ಯೂಸರ್ ಕೊನೆಯ ಬಾರಿ ಕೋರ್ಸ್ ಪೂರ್ಣಗೊಳಿಸಿದ ಸಮಯ';
$string['privacy:metadata:local_sentientia_recompletion_history:timecreated'] = 'ರೀಸೆಟ್ ನಡೆದ ಸಮಯ';
$string['privacy:metadata:local_sentientia_recompletion_history:source'] = 'ರೀಸೆಟ್ ಅನ್ನು Sentientia ಮಾಡಿತೇ ಅಥವಾ BizLMS ಪ್ಲಗಿನ್‌ನಿಂದ ಇಂಪೋರ್ಟ್ ಮಾಡಲಾಯಿತೇ ಎಂಬುದು';
$string['privacy:metadata:local_sentientia_recompletion_archive'] = 'ರೀಸೆಟ್ ಡಿಲೀಟ್ ಮಾಡಿದ ಪುರಾವೆ: ಅದು ಕೊನೆಗೊಳಿಸಿದ ಸೈಕಲ್‌ನ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆ, ಮಾನದಂಡಗಳು, ಚಟುವಟಿಕೆ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಗಳು, ಕ್ವಿಜ್ ಪ್ರಯತ್ನಗಳು ಮತ್ತು ಗ್ರೇಡ್‌ಗಳು, SCORM ಟ್ರ್ಯಾಕಿಂಗ್, LTI ಗ್ರೇಡ್‌ಗಳು ಮತ್ತು ಪ್ರಶ್ನಾವಳಿ ಉತ್ತರಗಳು';
$string['privacy:metadata:local_sentientia_recompletion_archive:userid'] = 'ಈ ಪುರಾವೆ ಯಾರದ್ದೋ ಆ ಯೂಸರ್';
$string['privacy:metadata:local_sentientia_recompletion_archive:courseid'] = 'ಪುರಾವೆ ಸೇರಿರುವ ಕೋರ್ಸ್';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemtype'] = 'ಈ ಸಾಲು ಯಾವ ರೀತಿಯ ಪುರಾವೆ ಎಂಬುದು';
$string['privacy:metadata:local_sentientia_recompletion_archive:cmid'] = 'ಪುರಾವೆ ಸೇರಿರುವ ಚಟುವಟಿಕೆ';
$string['privacy:metadata:local_sentientia_recompletion_archive:instanceid'] = 'ಪುರಾವೆ ಸೇರಿರುವ ಕ್ವಿಜ್, SCORM ಪ್ಯಾಕೇಜ್, LTI ಟೂಲ್, ಪ್ರಶ್ನಾವಳಿ, ಮಾನದಂಡ ಅಥವಾ ಗ್ರೇಡ್ ಐಟಂ';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemkey'] = 'SCORM ಎಲಿಮೆಂಟ್ ಅಥವಾ ಪ್ರಶ್ನಾವಳಿಯ ಪ್ರಶ್ನೆ';
$string['privacy:metadata:local_sentientia_recompletion_archive:state'] = 'ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯ ಸ್ಥಿತಿ, ಪ್ರಯತ್ನದ ಸ್ಥಿತಿ ಅಥವಾ ಉತ್ತರ';
$string['privacy:metadata:local_sentientia_recompletion_archive:grade'] = 'ಗ್ರೇಡ್, ಅಂಕಗಳು ಅಥವಾ ಸ್ಕೋರ್';
$string['privacy:metadata:local_sentientia_recompletion_archive:timeevent'] = 'ಆರ್ಕೈವ್ ಮಾಡಿದ ಘಟನೆ ನಡೆದ ಸಮಯ';
$string['privacy:metadata:local_sentientia_recompletion_archive:payload'] = 'ಆರ್ಕೈವ್ ಮಾಡಿದ ಸಾಲು ಇದ್ದ ಹಾಗೆಯೇ; ಇದರಲ್ಲಿ ಚಟುವಟಿಕೆ ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯನ್ನು ಓವರ್‌ರೈಡ್ ಮಾಡಿದ ಅಡ್ಮಿನ್, ಕೊನೆಯದಾಗಿ ಗ್ರೇಡ್ ಬದಲಾಯಿಸಿದ ಶಿಕ್ಷಕರು ಮತ್ತು ಅವರು ಬರೆದ ಯಾವುದೇ ಫೀಡ್‌ಬ್ಯಾಕ್, ಕಲಿಯುವವರು ಪ್ರಶ್ನಾವಳಿಯಲ್ಲಿ ಟೈಪ್ ಮಾಡಿದ್ದು, ಮತ್ತು SCORM ಟ್ರ್ಯಾಕಿಂಗ್‌ನ ಫ್ರೀ ಟೆಕ್ಸ್ಟ್ ಸೇರಿವೆ';
$string['privacy:metadata:local_sentientia_recompletion_archive:timecreated'] = 'ಪುರಾವೆಯನ್ನು ಆರ್ಕೈವ್ ಮಾಡಿದ ಸಮಯ';
$string['privacy:export:resets_performed'] = 'ನಾನು ಮಾಡಿದ ರೀಸೆಟ್‌ಗಳು';
$string['privacy:export:evidence'] = 'ರೀಸೆಟ್‌ಗಳ ಮೂಲಕ ಆರ್ಕೈವ್ ಆದ ಪುರಾವೆ';
