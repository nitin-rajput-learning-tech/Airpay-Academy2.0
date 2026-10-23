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
 * Kannada strings for local_sentientia_aiquiz.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_aiquiz
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI ಕ್ವಿಜ್ ಜನರೇಷನ್';
$string['sentientia_aiquiz:generate'] = 'AI ಡ್ರಾಫ್ಟ್ ಮಾಡಿದ ಕ್ವಿಜ್ ಪ್ರಶ್ನೆಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಿ';
$string['sentientia_aiquiz:review'] = 'AI ಡ್ರಾಫ್ಟ್ ಮಾಡಿದ ಕ್ವಿಜ್ ಪ್ರಶ್ನೆಗಳನ್ನು ರಿವ್ಯೂ ಮಾಡಿ';
$string['sentientia_aiquiz:manage_all'] = 'ಎಲ್ಲಾ ಮಾಲೀಕರ AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳನ್ನು ಮ್ಯಾನೇಜ್ ಮಾಡಿ';
$string['privacy:metadata'] = 'Sentientia LMS AI ಕ್ವಿಜ್ ಪ್ಲಗಿನ್ ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳನ್ನು ಮತ್ತು ಅವುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಲು ಬಳಸಿದ ಮೂಲ ಪಠ್ಯವನ್ನು ಸಂಗ್ರಹಿಸುತ್ತದೆ. ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ON ಆಗಿದ್ದಾಗ, ಮೂಲ ಪಠ್ಯವನ್ನು ಪ್ರೊಸೆಸಿಂಗ್‌ಗಾಗಿ Anthropic ಗೆ ಕಳುಹಿಸಲಾಗುತ್ತದೆ.';
$string['privacy:metadata:draft'] = 'ಬಳಕೆದಾರರು ರಚಿಸಿದ AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳು.';
$string['privacy:metadata:draft:ownerid'] = 'ಡ್ರಾಫ್ಟ್ ರಚಿಸಿದ ಬಳಕೆದಾರ.';
$string['privacy:metadata:draft:sourcetext'] = 'ಜನರೇಷನ್‌ಗಾಗಿ ಬಳಕೆದಾರರು ನೀಡಿದ ಮೂಲ ಪಠ್ಯ, ಇದ್ದ ಹಾಗೆಯೇ.';
$string['privacy:metadata:draft:title'] = 'ಬಳಕೆದಾರರು ನೀಡಿದ ಡ್ರಾಫ್ಟ್ ಶೀರ್ಷಿಕೆ.';
$string['privacy:metadata:draft:tokens'] = 'ವೆಚ್ಚದ ಲೆಕ್ಕಕ್ಕಾಗಿ Anthropic API ಟೋಕನ್ ಬಳಕೆ.';
$string['privacy:metadata:draft:reviewed_by'] = 'ಡ್ರಾಫ್ಟ್ ಅನ್ನು ರಿವ್ಯೂ ಮಾಡಿದ ಬಳಕೆದಾರ.';
$string['privacy:metadata:draft:reviewed_at'] = 'ಡ್ರಾಫ್ಟ್ ಅನ್ನು ರಿವ್ಯೂ ಮಾಡಿದ ಸಮಯ.';
$string['privacy:metadata:draft:timecreated'] = 'ಡ್ರಾಫ್ಟ್ ರಚಿಸಿದ ಸಮಯ.';
$string['privacy:metadata:draft:timemodified'] = 'ಡ್ರಾಫ್ಟ್ ಅನ್ನು ಕೊನೆಯದಾಗಿ ಬದಲಾಯಿಸಿದ ಸಮಯ.';
$string['privacy:metadata:question'] = 'ಒಂದು ಡ್ರಾಫ್ಟ್‌ಗೆ ಸೇರಿದ ಪ್ರತ್ಯೇಕ AI-ಜನರೇಟೆಡ್ ಪ್ರಶ್ನೆಗಳು.';
$string['privacy:metadata:question:draftid'] = 'ಈ ಪ್ರಶ್ನೆ ಸೇರಿರುವ ಮುಖ್ಯ ಡ್ರಾಫ್ಟ್.';
$string['privacy:metadata:question:qtext'] = 'ಪ್ರಶ್ನೆಯ ಮುಖ್ಯ ಪಠ್ಯ.';
$string['privacy:metadata:question:qoptions'] = 'ಮಲ್ಟಿಚಾಯ್ಸ್ ಪ್ರಶ್ನೆಗಳ JSON-ಎನ್‌ಕೋಡ್ ಮಾಡಿದ ಆಯ್ಕೆಗಳು.';
$string['privacy:metadata:question:reviewer_note'] = 'ಪ್ರಶ್ನೆಯ ಬಗ್ಗೆ ರಿವ್ಯೂವರ್ ಕಾಮೆಂಟ್‌ಗಳು.';
$string['privacy:metadata:question:timecreated'] = 'ಪ್ರಶ್ನೆ ಜನರೇಟ್ ಆದ ಸಮಯ.';
$string['privacy:metadata:question:timemodified'] = 'ಪ್ರಶ್ನೆಯನ್ನು ಕೊನೆಯದಾಗಿ ಬದಲಾಯಿಸಿದ ಸಮಯ.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ಎನೇಬಲ್ ಆಗಿದ್ದಾಗ, ಕ್ವಿಜ್ ಜನರೇಷನ್‌ಗಾಗಿ ಮೂಲ ಪಠ್ಯವನ್ನು Anthropic ಗೆ ಕಳುಹಿಸಲಾಗುತ್ತದೆ. ಪ್ರತಿ API ಕಾಲ್‌ಗೂ ಮೊದಲು ದೃಢೀಕರಣ ಹಂತ ಇರುತ್ತದೆ.';
$string['privacy:metadata:anthropic:sourcetext'] = 'ಟ್ರೈನರ್ ನೀಡಿದ ಮೂಲ ಪಠ್ಯ.';
$string['privacy:metadata:anthropic:model'] = 'ಬಳಸಿದ Anthropic ಮಾಡೆಲ್ ಐಡೆಂಟಿಫೈಯರ್.';
$string['nav_generate'] = 'AI ಕ್ವಿಜ್ ಜನರೇಟ್ ಮಾಡಿ';
$string['nav_review'] = 'AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳನ್ನು ರಿವ್ಯೂ ಮಾಡಿ';
$string['generate_page_title'] = 'AI ಕ್ವಿಜ್ ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_page_heading'] = 'ಕೋರ್ಸ್ ಕಂಟೆಂಟ್‌ನಿಂದ ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್ ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_intro'] = 'ಮೂಲ ಮಟೀರಿಯಲ್ (SCORM ಟ್ರಾನ್ಸ್‌ಕ್ರಿಪ್ಟ್, ನರೇಶನ್ ಟೆಕ್ಸ್ಟ್, SOP ಭಾಗ) ಅನ್ನು ಕೆಳಗೆ ಪೇಸ್ಟ್ ಮಾಡಿ. ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್ ಜನರೇಟ್ ಮಾಡಲು Sentientia LMS ಅದನ್ನು Anthropic Claude ಗೆ ಕಳುಹಿಸುತ್ತದೆ. ಯಾವುದೇ ಪ್ರಶ್ನೆಯನ್ನು ಕೋರ್ಸ್ ಕ್ವಿಜ್‌ಗೆ ಪುಶ್ ಮಾಡುವ ಮೊದಲು ಪ್ರತಿ ಡ್ರಾಫ್ಟ್ ಅನ್ನು ಒಬ್ಬ ವ್ಯಕ್ತಿ ರಿವ್ಯೂ ಮಾಡಲೇಬೇಕು.';
$string['generate_form_title'] = 'ಡ್ರಾಫ್ಟ್ ಶೀರ್ಷಿಕೆ';
$string['generate_form_title_help'] = 'ನಂತರ ರಿವ್ಯೂ ಕ್ಯೂನಲ್ಲಿ ಈ ಡ್ರಾಫ್ಟ್ ಅನ್ನು ಸುಲಭವಾಗಿ ಹುಡುಕಲು ಒಂದು ಚಿಕ್ಕ ಲೇಬಲ್.';
$string['generate_form_course'] = 'ಕೋರ್ಸ್';
$string['generate_form_course_help'] = 'ತಯಾರಾಗುವ ಕ್ವಿಜ್ ಯಾವ ಕೋರ್ಸ್‌ಗೆ ಸೇರಿದೆ. ಈ ಆಯ್ಕೆಯನ್ನು ರಿವ್ಯೂ ಸಮಯಕ್ಕೆ ಮುಂದೂಡಲು \\"ಸೈಟ್-ವೈಡ್ / ಇನ್ನೂ ಅಸೈನ್ ಮಾಡಿಲ್ಲ\\" ಆಯ್ಕೆಮಾಡಿ.';
$string['generate_form_course_none'] = 'ಸೈಟ್-ವೈಡ್ / ಇನ್ನೂ ಅಸೈನ್ ಮಾಡಿಲ್ಲ';
$string['generate_form_source'] = 'ಮೂಲ ಕಂಟೆಂಟ್';
$string['generate_form_source_help'] = 'ಗರಿಷ್ಠ {$a} ಪದಗಳು. ಪೇಸ್ಟ್ ಮಾಡುವ ಮೊದಲು ಯಾವುದೇ PII (ಉದ್ಯೋಗಿಗಳ ಹೆಸರು, ID ಗಳು, ಗ್ರಾಹಕರ ಡೇಟಾ) ತೆಗೆದುಹಾಕಿ.';
$string['generate_form_num'] = 'ಕೇಳಬೇಕಾದ ಪ್ರಶ್ನೆಗಳ ಸಂಖ್ಯೆ';
$string['generate_form_num_help'] = '1 ರಿಂದ {$a} ವರೆಗೆ. ಮೂಲ ಪಠ್ಯ ತುಂಬಾ ಚಿಕ್ಕದಾಗಿದ್ದರೆ Claude ಕಡಿಮೆ ಪ್ರಶ್ನೆಗಳನ್ನು ಕೊಡಬಹುದು.';
$string['generate_form_model'] = 'ಮಾಡೆಲ್';
$string['generate_form_model_help'] = 'Anthropic ಮಾಡೆಲ್ ಐಡೆಂಟಿಫೈಯರ್. ಡೀಫಾಲ್ಟ್: claude-sonnet-4-6.';
$string['generate_confirm_label'] = 'ಈ ಕಂಟೆಂಟ್‌ನೊಂದಿಗೆ Anthropic ಅನ್ನು ಕಾಲ್ ಮಾಡಲು ನಾನು ದೃಢೀಕರಿಸುತ್ತೇನೆ';
$string['generate_confirm_help'] = 'ಇದು ಪ್ರತಿ ಕಾಲ್‌ನ ದೃಢೀಕರಣ ಗೇಟ್. ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ON ಆಗಿದ್ದಾಗ, ಈ ಸಬ್‌ಮಿಷನ್‌ಗೆ ಮೂಲ ಪಠ್ಯದ ಉದ್ದದ ಆಧಾರದ ಮೇಲೆ ನಿಜವಾದ ಹಣ ಖರ್ಚಾಗುತ್ತದೆ. ರದ್ದುಮಾಡಲು ಟಿಕ್ ತೆಗೆಯಿರಿ.';
$string['generate_submit'] = 'ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್ ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_cancel'] = 'ರದ್ದುಮಾಡಿ';
$string['generate_form_language'] = 'ಕ್ವಿಜ್ ಭಾಷೆ';
$string['generate_form_language_help'] = 'Claude ಗೆ ಕಳುಹಿಸುವ ಸಿಸ್ಟಮ್ ಪ್ರಾಂಪ್ಟ್ ಅನ್ನು ಆಯ್ಕೆಮಾಡುತ್ತದೆ. ಇಂಗ್ಲಿಷ್ v1 ಬೇಸ್‌ಲೈನ್ ಪ್ರಾಂಪ್ಟ್ ಬಳಸುತ್ತದೆ. ಹಿಂದಿ v2-hindi ಪ್ರಾಂಪ್ಟ್ ಬಳಸುತ್ತದೆ ಮತ್ತು ಪ್ರಶ್ನೆಗಳನ್ನು ದೇವನಾಗರಿಯಲ್ಲಿ ಕೊಡುವಂತೆ Claude ಗೆ ಕೇಳುತ್ತದೆ.';
$string['generate_form_language_en'] = 'ಇಂಗ್ಲಿಷ್ (v1)';
$string['generate_form_language_hi'] = 'हिन्दी / ಹಿಂದಿ (v2-hindi)';
$string['generate_prompt_preview_summary'] = 'Claude ನೋಡುವ ಸಿಸ್ಟಮ್ ಪ್ರಾಂಪ್ಟ್‌ನ ಪ್ರಿವ್ಯೂ (ಪ್ರಾಂಪ್ಟ್ ವರ್ಷನ್: {$a->version}, ಕಸ್ಟಮರ್: {$a->customer})';
$string['generate_prompt_preview_help'] = 'ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ON ಆಗಿರುವಾಗ ನೀವು ಜನರೇಟ್ ಕ್ಲಿಕ್ ಮಾಡಿದರೆ Anthropic ಗೆ ಕಳುಹಿಸಲಾಗುವ ನಿಖರವಾದ ಸಿಸ್ಟಮ್ ಪ್ರಾಂಪ್ಟ್ ಇದು. ಮಾಕ್ ಮೋಡ್ ನಕಲಿ ಪ್ರಶ್ನೆಗಳನ್ನು ತಯಾರಿಸುತ್ತದೆ ಮತ್ತು API ಅನ್ನು ಕಾಲ್ ಮಾಡುವುದಿಲ್ಲ. ಕೆಳಗೆ ಪ್ರತಿ-ಕಸ್ಟಮರ್ ಟೆಂಪ್ಲೇಟ್ ಕಾನ್ಫಿಗರ್ ಮಾಡಿದ್ದರೆ, ಅದು ಬೇಸ್‌ಲೈನ್ ಪ್ರಾಂಪ್ಟ್‌ನ ಮುಖ್ಯ ಭಾಗವನ್ನು ಇದ್ದ ಹಾಗೆಯೇ ಬದಲಾಯಿಸುತ್ತದೆ.';
$string['generate_prompt_preview_custom_badge'] = 'ಕಸ್ಟಮ್ ಪ್ರತಿ-ಕಸ್ಟಮರ್ ಟೆಂಪ್ಲೇಟ್ ಸಕ್ರಿಯವಾಗಿದೆ';
$string['mode_mock_badge'] = 'ಮಾಕ್ ಮೋಡ್ — ಲೈವ್ API ಕಾಲ್ ಇಲ್ಲ (ನಿಜವಾದ ಜನರೇಷನ್‌ಗಾಗಿ sentientia.aiquiz.live_api = ON ಮಾಡಿ)';
$string['mode_live_badge'] = 'ಲೈವ್ API — Anthropic ಕಾಲ್‌ನ ಬಿಲ್ ನಿಮ್ಮ ಖಾತೆಗೆ ಬರುತ್ತದೆ';
$string['mode_disabled_badge'] = 'ಫೀಚರ್ ಡಿಸೇಬಲ್ ಆಗಿದೆ — Switchboard ನಲ್ಲಿ sentientia.aiquiz.enabled = ON ಮಾಡಿ';
$string['mode_no_apikey_badge'] = 'API ಕೀ ಕಾನ್ಫಿಗರ್ ಆಗಿಲ್ಲ — ಸೈಟ್ ಅಡ್ಮಿನ್‌ನಲ್ಲಿ local_sentientia_aiquiz | api_key ಸೆಟ್ ಮಾಡಿ';
$string['err_feature_off'] = 'AI ಕ್ವಿಜ್ ಫೀಚರ್ ಡಿಸೇಬಲ್ ಆಗಿದೆ. sentientia.aiquiz.enabled ಅನ್ನು ಎನೇಬಲ್ ಮಾಡಲು ಅಡ್ಮಿನಿಸ್ಟ್ರೇಟರ್‌ಗೆ ಕೇಳಿ.';
$string['err_no_capability'] = 'AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಲು ನಿಮಗೆ ಅನುಮತಿ ಇಲ್ಲ.';
$string['err_source_empty'] = 'ಮೂಲ ಕಂಟೆಂಟ್ ಅಗತ್ಯವಿದೆ.';
$string['err_source_too_long'] = 'ಮೂಲ ಕಂಟೆಂಟ್ ನಿಗದಿತ ಪದ ಮಿತಿಯನ್ನು ಮೀರಿದೆ. ಸಬ್‌ಮಿಟ್ ಮಾಡುವ ಮೊದಲು ಅದನ್ನು ಕಡಿಮೆ ಮಾಡಿ.';
$string['err_source_contains_pii'] = 'ಮೂಲ ಕಂಟೆಂಟ್‌ನಲ್ಲಿ PII (ಆಧಾರ್ ಅಥವಾ PAN ಸಂಖ್ಯೆಗಳು) ಇರುವಂತಿದೆ. ಅದನ್ನು ಮರೆಮಾಡಿ ಮತ್ತೆ ಸಬ್‌ಮಿಟ್ ಮಾಡಿ.';
$string['err_invalid_count'] = 'ಪ್ರಶ್ನೆಗಳ ಸಂಖ್ಯೆ {$a->min} ಮತ್ತು {$a->max} ನಡುವೆ ಇರಬೇಕು.';
$string['err_token_cap_reached'] = 'ನಿಮ್ಮ ಖಾತೆ ಇಂದು {$a->used} ಟೋಕನ್‌ಗಳನ್ನು ಬಳಸಿದೆ (ದೈನಂದಿನ ಸಾಫ್ಟ್ ಕ್ಯಾಪ್ {$a->cap}). ನಾಳೆ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ ಅಥವಾ ಕ್ಯಾಪ್ ಹೆಚ್ಚಿಸಲು ಅಡ್ಮಿನ್‌ಗೆ ಕೇಳಿ.';
$string['err_confirm_required'] = 'API ಕಾಲ್ ಮಾಡಲು ನೀವು ದೃಢೀಕರಣ ಚೆಕ್‌ಬಾಕ್ಸ್ ಟಿಕ್ ಮಾಡಲೇಬೇಕು.';
$string['err_api_key_not_set'] = 'ಯಾವುದೇ Anthropic API ಕೀ ಕಾನ್ಫಿಗರ್ ಆಗಿಲ್ಲ. ಲೈವ್ ಕಾಲ್ ಮಾಡಲು ಸಾಧ್ಯವಿಲ್ಲ.';
$string['err_api_failed'] = 'Anthropic API ಕಾಲ್ ವಿಫಲವಾಗಿದೆ: {$a}';
$string['err_parser_zero'] = 'Claude ಉತ್ತರಿಸಿದೆ, ಆದರೆ ಬಳಸಬಹುದಾದ ಯಾವುದೇ ಪ್ರಶ್ನೆಗಳನ್ನು ಪಾರ್ಸ್ ಮಾಡಲು ಆಗಲಿಲ್ಲ. ಇನ್ನೂ ಉದ್ದವಾದ ಅಥವಾ ಸ್ಪಷ್ಟವಾದ ಮೂಲ ಪಠ್ಯ ಪ್ರಯತ್ನಿಸಿ.';
$string['review_page_title'] = 'AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್ ರಿವ್ಯೂ ಮಾಡಿ';
$string['review_page_heading'] = 'ಡ್ರಾಫ್ಟ್ #{$a} ರಿವ್ಯೂ ಮಾಡಿ';
$string['review_intro'] = 'ಕೆಳಗಿನ ಪ್ರತಿ ಜನರೇಟೆಡ್ ಪ್ರಶ್ನೆಯನ್ನು ರಿವ್ಯೂ ಮಾಡಿ. ಅನುಮೋದಿಸಿ, ಎಡಿಟ್ ಮಾಡಿ ಅಥವಾ ತಿರಸ್ಕರಿಸಿ — ಅನುಮೋದಿಸಿದ ಅಥವಾ ಎಡಿಟ್ ಮಾಡಿದ ಪ್ರಶ್ನೆಗಳನ್ನು ಮಾತ್ರ ಕೋರ್ಸ್ ಕ್ವಿಜ್‌ಗೆ ಪುಶ್ ಮಾಡಬಹುದು.';
$string['review_no_draft'] = 'ಡ್ರಾಫ್ಟ್ ಸಿಗಲಿಲ್ಲ ಅಥವಾ ಅದನ್ನು ನೋಡಲು ನಿಮಗೆ ಅನುಮತಿ ಇಲ್ಲ.';
$string['review_meta_owner'] = 'ಮಾಲೀಕರು';
$string['review_meta_course'] = 'ಕೋರ್ಸ್';
$string['review_meta_model'] = 'ಮಾಡೆಲ್';
$string['review_meta_prompt'] = 'ಪ್ರಾಂಪ್ಟ್ ವರ್ಷನ್';
$string['review_meta_tokens'] = 'ಬಳಸಿದ ಟೋಕನ್‌ಗಳು';
$string['review_meta_generated_at'] = 'ಜನರೇಟ್ ಆದ ಸಮಯ';
$string['review_meta_mode'] = 'ಜನರೇಷನ್ ಮೋಡ್';
$string['review_status'] = 'ಸ್ಥಿತಿ';
$string['review_status_pending'] = 'ಜನರೇಷನ್ ಬಾಕಿ ಇದೆ';
$string['review_status_generated'] = 'ರಿವ್ಯೂಗಾಗಿ ಕಾಯುತ್ತಿದೆ';
$string['review_status_approved'] = 'ಅನುಮೋದಿಸಲಾಗಿದೆ (ಪುಶ್ ಮಾಡಲು ಸಿದ್ಧ)';
$string['review_status_pushed'] = 'ಕ್ವಿಜ್ #{$a} ಗೆ ಪುಶ್ ಮಾಡಲಾಗಿದೆ';
$string['review_status_rejected'] = 'ತಿರಸ್ಕರಿಸಲಾಗಿದೆ';
$string['review_status_failed'] = 'ಜನರೇಷನ್ ವಿಫಲವಾಗಿದೆ';
$string['review_question_label'] = 'ಪ್ರಶ್ನೆ {$a}';
$string['review_question_answer'] = 'ಸರಿಯಾದ ಉತ್ತರ';
$string['review_question_explanation'] = 'ವಿವರಣೆ';
$string['review_action_approve'] = 'ಅನುಮೋದಿಸಿ';
$string['review_action_edit'] = 'ಎಡಿಟ್';
$string['review_action_reject'] = 'ತಿರಸ್ಕರಿಸಿ';
$string['review_action_save_edit'] = 'ಬದಲಾವಣೆಗಳನ್ನು ಸೇವ್ ಮಾಡಿ';
$string['review_action_cancel_edit'] = 'ಎಡಿಟ್ ರದ್ದುಮಾಡಿ';
$string['review_q_status_generated'] = 'ಇನ್ನೂ ರಿವ್ಯೂ ಆಗಿಲ್ಲ';
$string['review_q_status_approved'] = 'ಅನುಮೋದಿಸಲಾಗಿದೆ';
$string['review_q_status_edited'] = 'ಎಡಿಟ್ ಮಾಡಲಾಗಿದೆ';
$string['review_q_status_rejected'] = 'ತಿರಸ್ಕರಿಸಲಾಗಿದೆ';
$string['review_finalise'] = 'ರಿವ್ಯೂ ಅಂತಿಮಗೊಳಿಸಿ';
$string['review_finalise_help'] = 'ಈ ಡ್ರಾಫ್ಟ್ ಸಂಪೂರ್ಣವಾಗಿ ರಿವ್ಯೂ ಆಗಿದೆ ಎಂದು ಗುರುತಿಸುತ್ತದೆ. ಕನಿಷ್ಠ ಒಂದು ಪ್ರಶ್ನೆ ಅನುಮೋದಿತ ಅಥವಾ ಎಡಿಟ್ ಆಗಿದ್ದರೆ ಸ್ಥಿತಿ \\"ಅನುಮೋದಿಸಲಾಗಿದೆ\\" ಆಗುತ್ತದೆ, ಇಲ್ಲದಿದ್ದರೆ \\"ತಿರಸ್ಕರಿಸಲಾಗಿದೆ\\".';
$string['review_push_to_quiz'] = 'ಅನುಮೋದಿತ ಪ್ರಶ್ನೆಗಳನ್ನು ಕೋರ್ಸ್ ಕ್ವಿಜ್‌ಗೆ ಪುಶ್ ಮಾಡಿ';
$string['review_push_disabled'] = 'mod_quiz ಗೆ ಪುಶ್ ಮಾಡುವುದು sentientia.aiquiz.auto_push ಫ್ಲ್ಯಾಗ್ ಮೇಲೆ ಅವಲಂಬಿತವಾಗಿದೆ (ಡೀಫಾಲ್ಟ್ OFF).';
$string['review_push_success'] = 'ಅನುಮೋದಿತ ಪ್ರಶ್ನೆಗಳನ್ನು ಕ್ವಿಜ್ #{$a->quizid} ಗೆ ಪುಶ್ ಮಾಡಲಾಗಿದೆ ({$a->count} ಪ್ರಶ್ನೆಗಳು).';
$string['review_no_questions'] = 'ಈ ಡ್ರಾಫ್ಟ್‌ಗೆ ಯಾವುದೇ ಪ್ರಶ್ನೆಗಳು ಜನರೇಟ್ ಆಗಿಲ್ಲ.';
$string['review_empty_state'] = 'ನಿಮ್ಮ ಬಳಿ ಇನ್ನೂ ಯಾವುದೇ AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳಿಲ್ಲ. ಒಂದನ್ನು ರಚಿಸಲು ಜನರೇಟ್ ಪೇಜ್ ಬಳಸಿ.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic Claude API ಗಾಗಿ ಕ್ರೆಡೆನ್ಶಿಯಲ್‌ಗಳು. sentientia.aiquiz.enabled ಮತ್ತು sentientia.aiquiz.live_api ಎರಡೂ ON ಆಗಿದ್ದಾಗ ಮಾತ್ರ ಕೀ ಬಳಸಲಾಗುತ್ತದೆ.';
$string['setting_api_key'] = 'Anthropic API ಕೀ';
$string['setting_api_key_desc'] = 'console.anthropic.com ನಿಂದ API ಕೀ ಪೇಸ್ಟ್ ಮಾಡಿ. Moodle ನ configpasswordunmask ಮೂಲಕ ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿ ಸಂಗ್ರಹಿಸಲಾಗುತ್ತದೆ. ಈ ಮೌಲ್ಯವನ್ನು ಎಂದಿಗೂ ಸೋರ್ಸ್ ಕಂಟ್ರೋಲ್‌ಗೆ ಕಮಿಟ್ ಮಾಡಬೇಡಿ.';
$string['setting_default_model'] = 'ಡೀಫಾಲ್ಟ್ ಮಾಡೆಲ್';
$string['setting_default_model_desc'] = 'ಜನರೇಟರ್ ಯಾವುದೇ ಮಾಡೆಲ್ ಸೂಚಿಸದಿದ್ದಾಗ ಬಳಸುವ Anthropic ಮಾಡೆಲ್. ಶಿಫಾರಸು: claude-sonnet-4-6.';
$string['settings_heading_limits'] = 'ಮಿತಿಗಳು ಮತ್ತು ಕೋಟಾಗಳು';
$string['settings_heading_limits_desc'] = 'ವೆಚ್ಚವನ್ನು ನಿರೀಕ್ಷಿತ ಮಟ್ಟದಲ್ಲಿಡಲು ಪ್ರತಿ ಬಳಕೆದಾರ ಮತ್ತು ಪ್ರತಿ ರಿಕ್ವೆಸ್ಟ್ ಮಿತಿಗಳು.';
$string['setting_max_questions'] = 'ಪ್ರತಿ ರಿಕ್ವೆಸ್ಟ್‌ಗೆ ಗರಿಷ್ಠ ಪ್ರಶ್ನೆಗಳು';
$string['setting_max_questions_desc'] = 'ಪ್ರತಿ ಜನರೇಷನ್‌ಗೆ ಪ್ರಶ್ನೆಗಳ ಗರಿಷ್ಠ ಮಿತಿ. ಡೀಫಾಲ್ಟ್ 10.';
$string['setting_daily_token_cap'] = 'ಪ್ರತಿ ಬಳಕೆದಾರರ ದೈನಂದಿನ ಟೋಕನ್ ಕ್ಯಾಪ್';
$string['setting_daily_token_cap_desc'] = 'ಒಬ್ಬ ಬಳಕೆದಾರ ದಿನಕ್ಕೆ ಖರ್ಚು ಮಾಡಬಹುದಾದ ಟೋಕನ್‌ಗಳ (ಇನ್‌ಪುಟ್ + ಔಟ್‌ಪುಟ್) ಸಾಫ್ಟ್ ಕ್ಯಾಪ್. ಮಿತಿ ಮೀರಿದ ನಂತರ, ಮಧ್ಯರಾತ್ರಿಯವರೆಗೆ generate.php ಎರರ್ ತೋರಿಸುತ್ತದೆ.';
$string['setting_max_source_words'] = 'ಪ್ರತಿ ಡ್ರಾಫ್ಟ್‌ಗೆ ಗರಿಷ್ಠ ಮೂಲ ಪದಗಳು';
$string['setting_max_source_words_desc'] = 'ಮೂಲ ಪಠ್ಯ ಈ ಪದ ಸಂಖ್ಯೆಯನ್ನು ಮೀರಿದರೆ ತಿರಸ್ಕರಿಸಲಾಗುತ್ತದೆ. ಡೀಫಾಲ್ಟ್ 4000 — ಸುಮಾರು 8 ಪುಟಗಳು.';
$string['settings_heading_customer_prompts'] = 'ಪ್ರತಿ-ಕಸ್ಟಮರ್ ಪ್ರಾಂಪ್ಟ್ ಟೆಂಪ್ಲೇಟ್‌ಗಳು';
$string['settings_heading_customer_prompts_desc'] = 'ಪ್ರತಿ Sentientia LMS ಕಸ್ಟಮರ್‌ಗೆ Claude ಗೆ ಕಳುಹಿಸುವ ಸಿಸ್ಟಮ್ ಪ್ರಾಂಪ್ಟ್‌ನ ಐಚ್ಛಿಕ ಓವರ್‌ರೈಡ್. ಸೆಟ್ ಮಾಡಿದ್ದರೆ, ಟೆಕ್ಸ್ಟ್‌ಏರಿಯಾದ ಕಂಟೆಂಟ್ ಬೇಸ್‌ಲೈನ್ v1 (ಇಂಗ್ಲಿಷ್) ಅಥವಾ v2-hindi ಪ್ರಾಂಪ್ಟ್‌ನ ಮುಖ್ಯ ಭಾಗವನ್ನು ಇದ್ದ ಹಾಗೆಯೇ ಸಂಪೂರ್ಣವಾಗಿ ಬದಲಾಯಿಸುತ್ತದೆ. ಕೋಡ್‌ನಲ್ಲಿರುವ ಬೇಸ್‌ಲೈನ್ ಬಳಸಲು ಖಾಲಿ ಬಿಡಿ. ಯೂಸರ್-ಮೆಸೇಜ್ ವ್ರ್ಯಾಪರ್ (begin/end ಮಾರ್ಕರ್‌ಗಳು + "ನಿಖರವಾಗಿ N ಪ್ರಶ್ನೆಗಳು" ಸೂಚನೆ) ಯಾವಾಗಲೂ ಜನರೇಟ್ ಫಾರ್ಮ್‌ನ ಭಾಷಾ ಆಯ್ಕೆಯನ್ನೇ ಅನುಸರಿಸುತ್ತದೆ.';
$string['setting_customer_1_prompt_template'] = 'Airpay (ಕಸ್ಟಮರ್ 1) — ಕಸ್ಟಮ್ ಪ್ರಾಂಪ್ಟ್ ಟೆಂಪ್ಲೇಟ್';
$string['setting_customer_1_prompt_template_desc'] = 'Airpay ಗಾಗಿ ಕಸ್ಟಮರ್-ನಿರ್ದಿಷ್ಟ ಸಿಸ್ಟಮ್ ಪ್ರಾಂಪ್ಟ್ ಪೇಸ್ಟ್ ಮಾಡಿ. v1/v2-hindi ಬೇಸ್‌ಲೈನ್ ಬಳಸಲು ಖಾಲಿ ಬಿಡಿ. local_sentientia_platform/customer_1_aiquiz_prompt_template ಅಡಿಯಲ್ಲಿ ಸಂಗ್ರಹಿಸಲಾಗುತ್ತದೆ — \\local_sentientia_platform\\customer::get_customer_config() ಇದನ್ನು ಓದುತ್ತದೆ. ಕಸ್ಟಮ್ ಟೆಂಪ್ಲೇಟ್ ಸಕ್ರಿಯವಾಗಿರುವಾಗ ಜನರೇಟ್ ಆದ ಡ್ರಾಫ್ಟ್‌ಗಳ prompt_version ಅನ್ನು "custom:v1" ಅಥವಾ "custom:v2-hindi" ಎಂದು ದಾಖಲಿಸಲಾಗುತ್ತದೆ.';
$string['source_word_count'] = 'ಪದಗಳ ಸಂಖ್ಯೆ: {$a}';
$string['tokens_used_today'] = 'ಇಂದು ಬಳಸಿದ ಟೋಕನ್‌ಗಳು: {$a->used} / {$a->cap}';
$string['back_to_drafts'] = 'ಡ್ರಾಫ್ಟ್‌ಗಳ ಪಟ್ಟಿಗೆ ಹಿಂತಿರುಗಿ';
$string['drafts_list_title'] = 'ನಿಮ್ಮ AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್‌ಗಳು';
$string['push_selectcourse'] = 'ಟಾರ್ಗೆಟ್ ಕೋರ್ಸ್';
$string['push_default_quizname'] = 'AI ಜನರೇಟೆಡ್ ಕ್ವಿಜ್ (ಡ್ರಾಫ್ಟ್ {$a})';
$string['push_quiz_intro'] = 'AI ಕ್ವಿಜ್ ಡ್ರಾಫ್ಟ್ #{$a->draftid} ನಿಂದ (ಮಾಡೆಲ್: {$a->model}) ಜನರೇಟ್ ಮಾಡಲಾಗಿದೆ ಮತ್ತು ಪಬ್ಲಿಶ್ ಮಾಡುವ ಮೊದಲು ಒಬ್ಬ ವ್ಯಕ್ತಿ ರಿವ್ಯೂ ಮಾಡಿದ್ದಾರೆ. ಹಿಡನ್ ಆಗಿ ರಚಿಸಲಾಗಿದೆ — ಸ್ಥಾನ ಮತ್ತು ಸೆಟ್ಟಿಂಗ್‌ಗಳನ್ನು ರಿವ್ಯೂ ಮಾಡಿ, ನಂತರ ಅದನ್ನು ವಿಸಿಬಲ್ ಮಾಡಿ.';
$string['push_success_real'] = 'ಕ್ವಿಜ್ "{$a->quizname}" ರಚಿಸಲಾಗಿದೆ (id {$a->quizid}), ಇದರಲ್ಲಿ {$a->count} ಪ್ರಶ್ನೆ(ಗಳು) ಇವೆ. ಇದು ಹಿಡನ್ ಆಗಿದೆ — ಕೋರ್ಸ್‌ನಲ್ಲಿ ರಿವ್ಯೂ ಮಾಡಿ, ನಂತರ ವಿಸಿಬಲ್ ಮಾಡಿ.';
$string['push_err_nocourse'] = 'ಪುಶ್ ಮಾಡುವ ಮೊದಲು ಟಾರ್ಗೆಟ್ ಕೋರ್ಸ್ ಆಯ್ಕೆಮಾಡಿ — ಈ ಡ್ರಾಫ್ಟ್ ಅನ್ನು ಕೋರ್ಸ್ ಕಾಂಟೆಕ್ಸ್ಟ್ ಇಲ್ಲದೆ ಜನರೇಟ್ ಮಾಡಲಾಗಿದೆ.';
$string['push_err_notapproved'] = 'ಅಂತಿಮಗೊಳಿಸಿದ (ಅನುಮೋದಿತ) ಡ್ರಾಫ್ಟ್ ಅನ್ನು ಮಾತ್ರ ಪುಶ್ ಮಾಡಬಹುದು. ಮೊದಲು ರಿವ್ಯೂ ಅಂತಿಮಗೊಳಿಸಿ.';
$string['push_err_noquestions'] = 'ಪುಶ್ ಮಾಡಲು ಯಾವುದೇ ಅನುಮೋದಿತ ಅಥವಾ ಎಡಿಟ್ ಮಾಡಿದ ಪ್ರಶ್ನೆಗಳಿಲ್ಲ.';
$string['push_err_nobank'] = 'ಕೋರ್ಸ್ ಪ್ರಶ್ನೆ ಬ್ಯಾಂಕ್ ಅನ್ನು ಗುರುತಿಸಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ.';
$string['push_err_importcount'] = 'ಪ್ರಶ್ನೆ ಇಂಪೋರ್ಟ್ ಹೊಂದಿಕೆಯಾಗುತ್ತಿಲ್ಲ: ನಿರೀಕ್ಷಿತ {$a->expected}, ಇಂಪೋರ್ಟ್ ಆಗಿದ್ದು {$a->actual}. ಏನನ್ನೂ ಪಬ್ಲಿಶ್ ಮಾಡಿಲ್ಲ.';
$string['push_err_badoptions'] = 'ಪ್ರಶ್ನೆ {$a} ನಲ್ಲಿ ಎರಡಕ್ಕಿಂತ ಕಡಿಮೆ ಉತ್ತರ ಆಯ್ಕೆಗಳಿವೆ.';
$string['push_err_badanswer'] = 'ಪ್ರಶ್ನೆ {$a} ನ ಸರಿಯಾದ-ಉತ್ತರ ಇಂಡೆಕ್ಸ್ ಅದರ ಆಯ್ಕೆಗಳ ಹೊರಗಿದೆ.';
$string['push_err_tempfile'] = 'ಇಂಪೋರ್ಟ್ ವರ್ಕ್ ಫೈಲ್ ಬರೆಯಲು ಸಾಧ್ಯವಾಗಲಿಲ್ಲ.';
$string['push_err_import'] = 'ಪ್ರಶ್ನೆ ಇಂಪೋರ್ಟ್ ವಿಫಲವಾಗಿದೆ. ಏನನ್ನೂ ಪಬ್ಲಿಶ್ ಮಾಡಿಲ್ಲ.';
$string['push_err_failed'] = 'ಪುಶ್ ವಿಫಲವಾಗಿದೆ: {$a}';
$string['review_notfound'] = 'ಡ್ರಾಫ್ಟ್ ಸಿಗಲಿಲ್ಲ, ಅಥವಾ ಅದಕ್ಕೆ ನಿಮಗೆ ಆಕ್ಸೆಸ್ ಇಲ್ಲ.';
