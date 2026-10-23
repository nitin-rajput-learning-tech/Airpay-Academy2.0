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
 * Kannada strings for local_sentientia_recommendations.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_recommendations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳು';
$string['sentientia_recommendations:view'] = 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್‌ನಲ್ಲಿ AI ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳನ್ನು ನೋಡಿ';
$string['sentientia_recommendations:generate'] = 'ಕಲಿಯುವವರಿಗಾಗಿ AI ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಿ';
$string['sentientia_recommendations:manage_all'] = 'ಎಲ್ಲಾ ಕಲಿಯುವವರ AI ಶಿಫಾರಸು ಇತಿಹಾಸವನ್ನು ಮ್ಯಾನೇಜ್ ಮಾಡಿ';
$string['privacy:metadata'] = 'Sentientia LMS ನ AI ಶಿಫಾರಸುಗಳ ಪ್ಲಗಿನ್ ವೈಯಕ್ತೀಕರಿಸಿದ ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳನ್ನು ಮತ್ತು ಪ್ರತಿಯೊಂದಕ್ಕೂ ಒಂದು ಚಿಕ್ಕ ಕಾರಣವನ್ನು ಸಂಗ್ರಹಿಸುತ್ತದೆ. ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ON ಆಗಿದ್ದಾಗ, ಅನಾಮಧೇಯಗೊಳಿಸಿದ ಕಲಿಯುವವರ ಪ್ರೊಫೈಲ್ (ರೋಲ್, ಟೆನೆಂಟ್, ಪೂರ್ಣಗೊಳಿಸಿದ ಕೋರ್ಸ್ ID ಗಳು, ಸ್ಕಿಲ್ ಟ್ಯಾಗ್‌ಗಳು) ಪ್ರಕ್ರಿಯೆಗಾಗಿ Anthropic ಗೆ ಕಳುಹಿಸಲಾಗುತ್ತದೆ.';
$string['privacy:metadata:rec'] = 'ಪ್ರತಿ ಕಲಿಯುವವರಿಗಾಗಿ AI ಜನರೇಟ್ ಮಾಡಿದ ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳು.';
$string['privacy:metadata:rec:userid'] = 'ಈ ಶಿಫಾರಸು ಯಾವ ಕಲಿಯುವವರಿಗಾಗಿ ಇದೆಯೋ ಅವರು.';
$string['privacy:metadata:rec:courseid'] = 'ಶಿಫಾರಸು ಮಾಡಿದ ಕೋರ್ಸ್.';
$string['privacy:metadata:rec:score'] = 'ಈ ಶಿಫಾರಸಿಗೆ Claude ನೀಡಿದ ಕಾನ್ಫಿಡೆನ್ಸ್ ಸ್ಕೋರ್.';
$string['privacy:metadata:rec:reasoning'] = 'ಈ ಕೋರ್ಸ್ ಏಕೆ ಸೂಕ್ತವಾದ ಮುಂದಿನ ಹೆಜ್ಜೆ ಎಂಬುದನ್ನು ವಿವರಿಸುವ ಚಿಕ್ಕ ಕಾರಣ.';
$string['privacy:metadata:rec:tokens'] = 'ವೆಚ್ಚದ ಲೆಕ್ಕಕ್ಕಾಗಿ Anthropic API ಟೋಕನ್ ಬಳಕೆ.';
$string['privacy:metadata:rec:status'] = 'ಶಿಫಾರಸಿನ ಸ್ಥಿತಿ: ಸಕ್ರಿಯ, ಕೈಬಿಡಲಾಗಿದೆ, ಎನ್‌ರೋಲ್ ಆಗಿದೆ, ಅವಧಿ ಮುಗಿದಿದೆ.';
$string['privacy:metadata:rec:generated_at'] = 'ಶಿಫಾರಸನ್ನು ಜನರೇಟ್ ಮಾಡಿದ ಸಮಯ.';
$string['privacy:metadata:rec:timecreated'] = 'ಶಿಫಾರಸನ್ನು ರಚಿಸಿದ ಸಮಯ.';
$string['privacy:metadata:rec:timemodified'] = 'ಶಿಫಾರಸನ್ನು ಕೊನೆಯದಾಗಿ ಬದಲಾಯಿಸಿದ ಸಮಯ.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ಆನ್ ಆಗಿದ್ದಾಗ, ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಲು ಅನಾಮಧೇಯಗೊಳಿಸಿದ ಕಲಿಯುವವರ ಪ್ರೊಫೈಲ್ ಅನ್ನು Anthropic ಗೆ ಕಳುಹಿಸಲಾಗುತ್ತದೆ. ಪ್ರತಿ API ಕಾಲ್‌ಗೂ ಮೊದಲು ದೃಢೀಕರಣ ಹಂತ ಕಡ್ಡಾಯ.';
$string['privacy:metadata:anthropic:profile_role'] = 'ಕಲಿಯುವವರ ರೋಲ್ ಲೇಬಲ್ (ಉದಾ. ಲರ್ನರ್, ಮ್ಯಾನೇಜರ್).';
$string['privacy:metadata:anthropic:profile_completed'] = 'ಕಲಿಯುವವರು ಪೂರ್ಣಗೊಳಿಸಿದ ಕೋರ್ಸ್ ID ಗಳು.';
$string['privacy:metadata:anthropic:profile_skills'] = 'ಕಲಿಯುವವರ ಹೆಸರಿನಲ್ಲಿ ಸದ್ಯ ದಾಖಲಾಗಿರುವ ಸ್ಕಿಲ್ ಟ್ಯಾಗ್‌ಗಳು.';
$string['privacy:metadata:anthropic:model'] = 'ಬಳಸಿದ Anthropic ಮಾಡೆಲ್ ಐಡೆಂಟಿಫೈಯರ್.';
$string['nav_generate'] = 'AI ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_page_title'] = 'AI ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_page_heading'] = 'ವೈಯಕ್ತೀಕರಿಸಿದ ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_intro'] = 'ಒಬ್ಬ ಕಲಿಯುವವರನ್ನು ಆಯ್ಕೆ ಮಾಡಿ ಮತ್ತು Anthropic Claude ನಿಂದ ವೈಯಕ್ತೀಕರಿಸಿದ ಕೋರ್ಸ್ ಶಿಫಾರಸುಗಳ ಒಂದು ಬ್ಯಾಚ್ ಕೇಳಿ. ಪ್ರತಿ ಶಿಫಾರಸಿನ ಜೊತೆಗೆ ಒಂದು ಚಿಕ್ಕ ಕಾರಣ ಇರುತ್ತದೆ, ಅದನ್ನು ಕಲಿಯುವವರು ತಮ್ಮ ಡ್ಯಾಶ್‌ಬೋರ್ಡ್‌ನಲ್ಲಿ ನೋಡಬಹುದು.';
$string['generate_form_targetuser'] = 'ಟಾರ್ಗೆಟ್ ಕಲಿಯುವವರು (ಯೂಸರ್ ID)';
$string['generate_form_targetuser_help'] = 'ಈ ಬ್ಯಾಚ್ ಯಾವ ಕಲಿಯುವವರಿಗಾಗಿ ಜನರೇಟ್ ಆಗುತ್ತದೆಯೋ ಅವರ ಯೂಸರ್ ID. ಡೀಫಾಲ್ಟ್ ಆಗಿ ನಿಮ್ಮದೇ ಯೂಸರ್ ID ಇರುತ್ತದೆ.';
$string['generate_form_num'] = 'ಶಿಫಾರಸುಗಳ ಸಂಖ್ಯೆ';
$string['generate_form_num_help'] = '1 ರಿಂದ {$a} ವರೆಗೆ. ಶಿಫಾರಸು ಮಾಡಬಹುದಾದ ಕೋರ್ಸ್‌ಗಳ ಕ್ಯಾಟಲಾಗ್ ತುಂಬಾ ಚಿಕ್ಕದಾಗಿದ್ದರೆ Claude ಕಡಿಮೆ ಶಿಫಾರಸುಗಳನ್ನು ನೀಡಬಹುದು.';
$string['generate_form_model'] = 'ಮಾಡೆಲ್';
$string['generate_confirm_label'] = 'ಈ ಕಲಿಯುವವರ ಪ್ರೊಫೈಲ್‌ನೊಂದಿಗೆ Anthropic ಗೆ ಕಾಲ್ ಮಾಡಲು ನಾನು ಖಚಿತಪಡಿಸುತ್ತೇನೆ';
$string['generate_confirm_help'] = 'ಇದು ಪ್ರತಿ ಕಾಲ್‌ನ ದೃಢೀಕರಣ ಹಂತ. ಲೈವ್ API ಫ್ಲ್ಯಾಗ್ ON ಆಗಿದ್ದಾಗ, ಪ್ರೊಫೈಲ್ + ಕ್ಯಾಟಲಾಗ್ ಗಾತ್ರದ ಆಧಾರದ ಮೇಲೆ ಈ ಸಬ್‌ಮಿಷನ್‌ಗೆ ನಿಜವಾದ ಹಣ ಖರ್ಚಾಗುತ್ತದೆ. ರದ್ದುಗೊಳಿಸಲು ಟಿಕ್ ತೆಗೆಯಿರಿ.';
$string['generate_submit'] = 'ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಿ';
$string['generate_cancel'] = 'ರದ್ದುಮಾಡಿ';
$string['generate_success'] = 'ಶಿಫಾರಸು ಬ್ಯಾಚ್ ಜನರೇಟ್ ಆಗಿದೆ. ಬ್ಯಾಚ್ ID: {$a->batchid}. {$a->mode} ಮೋಡ್‌ನಲ್ಲಿ {$a->count} ಶಿಫಾರಸುಗಳನ್ನು ರಚಿಸಲಾಗಿದೆ. ಟೋಕನ್‌ಗಳು: {$a->tokens_in} ಇನ್ / {$a->tokens_out} ಔಟ್.';
$string['mode_mock_badge'] = 'ಮಾಕ್ ಮೋಡ್ — ಲೈವ್ API ಕಾಲ್ ಇಲ್ಲ (ನಿಜವಾದ ಜನರೇಶನ್‌ಗಾಗಿ sentientia.recommendations.live_api = ON ಸೆಟ್ ಮಾಡಿ)';
$string['mode_live_badge'] = 'ಲೈವ್ API — Anthropic ಕಾಲ್‌ನ ಬಿಲ್ ನಿಮ್ಮ ಅಕೌಂಟ್‌ಗೆ ಬರುತ್ತದೆ';
$string['mode_disabled_badge'] = 'ಫೀಚರ್ ಆಫ್ ಆಗಿದೆ — Switchboard ನಲ್ಲಿ sentientia.recommendations.enabled = ON ಸೆಟ್ ಮಾಡಿ';
$string['mode_no_apikey_badge'] = 'API ಕೀ ಕಾನ್ಫಿಗರ್ ಆಗಿಲ್ಲ — ಸೈಟ್ ಅಡ್ಮಿನ್‌ನಲ್ಲಿ local_sentientia_recommendations | api_key ಸೆಟ್ ಮಾಡಿ';
$string['err_feature_off'] = 'AI ಶಿಫಾರಸುಗಳ ಫೀಚರ್ ಆಫ್ ಆಗಿದೆ. sentientia.recommendations.enabled ಆನ್ ಮಾಡಲು ಅಡ್ಮಿನಿಸ್ಟ್ರೇಟರ್‌ಗೆ ಕೇಳಿ.';
$string['err_no_capability'] = 'AI ಶಿಫಾರಸುಗಳನ್ನು ಜನರೇಟ್ ಮಾಡಲು ನಿಮಗೆ ಅನುಮತಿ ಇಲ್ಲ.';
$string['err_invalid_count'] = 'ಶಿಫಾರಸುಗಳ ಸಂಖ್ಯೆ {$a->min} ಮತ್ತು {$a->max} ನಡುವೆ ಇರಬೇಕು.';
$string['err_confirm_required'] = 'API ಕಾಲ್ ಮಾಡಲು ನೀವು ದೃಢೀಕರಣ ಚೆಕ್‌ಬಾಕ್ಸ್ ಅನ್ನು ಟಿಕ್ ಮಾಡಬೇಕು.';
$string['err_api_key_not_set'] = 'ಯಾವುದೇ Anthropic API ಕೀ ಕಾನ್ಫಿಗರ್ ಆಗಿಲ್ಲ. ಲೈವ್ ಕಾಲ್ ಮಾಡಲು ಸಾಧ್ಯವಿಲ್ಲ.';
$string['err_api_failed'] = 'Anthropic API ಕಾಲ್ ವಿಫಲವಾಗಿದೆ: {$a}';
$string['err_parser_zero'] = 'Claude ಉತ್ತರಿಸಿದೆ, ಆದರೆ ಬಳಸಬಹುದಾದ ಯಾವುದೇ ಶಿಫಾರಸುಗಳನ್ನು ಪಾರ್ಸ್ ಮಾಡಲು ಆಗಲಿಲ್ಲ.';
$string['err_cost_cap_reached'] = 'ನಿಮ್ಮ ಕಸ್ಟಮರ್ ಅಕೌಂಟ್ ಇಂದು {$a->used} ಟೋಕನ್‌ಗಳನ್ನು ಬಳಸಿದೆ (ದೈನಂದಿನ ಸಾಫ್ಟ್ ಕ್ಯಾಪ್ {$a->cap}). ನಾಳೆ ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ ಅಥವಾ ಕ್ಯಾಪ್ ಹೆಚ್ಚಿಸಲು ಅಡ್ಮಿನ್‌ಗೆ ಕೇಳಿ.';
$string['err_user_not_found'] = 'ಟಾರ್ಗೆಟ್ ಕಲಿಯುವವರು ಸಿಗಲಿಲ್ಲ ಅಥವಾ ಡಿಲೀಟ್ ಆಗಿದ್ದಾರೆ.';
$string['err_candidates_empty'] = 'ಈ ಕಲಿಯುವವರಿಗೆ ಶಿಫಾರಸು ಮಾಡಬಹುದಾದ ಯಾವುದೇ ಕೋರ್ಸ್‌ಗಳು ಲಭ್ಯವಿಲ್ಲ.';
$string['err_profile_invalid'] = 'ಕಲಿಯುವವರ ಪ್ರೊಫೈಲ್ ಅಮಾನ್ಯವಾಗಿದೆ.';
$string['err_profile_contains_pii'] = 'ಕಲಿಯುವವರ ಪ್ರೊಫೈಲ್‌ನಲ್ಲಿ PII ಪ್ಯಾಟರ್ನ್‌ಗಳು (ಆಧಾರ್ ಅಥವಾ PAN) ಇವೆ. ಅವನ್ನು ತೆಗೆದು ಮತ್ತೆ ಸಬ್‌ಮಿಟ್ ಮಾಡಿ.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic Claude API ಗಾಗಿ ಕ್ರೆಡೆನ್ಶಿಯಲ್‌ಗಳು. sentientia.recommendations.enabled ಮತ್ತು sentientia.recommendations.live_api ಎರಡೂ ON ಆಗಿದ್ದಾಗ ಮಾತ್ರ ಈ ಕೀ ಬಳಸಲಾಗುತ್ತದೆ.';
$string['setting_api_key'] = 'Anthropic API ಕೀ';
$string['setting_api_key_desc'] = 'console.anthropic.com ನಿಂದ API ಕೀ ಪೇಸ್ಟ್ ಮಾಡಿ. ಇದನ್ನು ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿ ಸಂಗ್ರಹಿಸಲಾಗುತ್ತದೆ. ಈ ವ್ಯಾಲ್ಯೂ ಅನ್ನು ಎಂದಿಗೂ ಸೋರ್ಸ್ ಕಂಟ್ರೋಲ್‌ಗೆ ಕಮಿಟ್ ಮಾಡಬೇಡಿ.';
$string['setting_default_model'] = 'ಡೀಫಾಲ್ಟ್ ಮಾಡೆಲ್';
$string['setting_default_model_desc'] = 'ಜನರೇಟರ್ ಯಾವುದೇ ಮಾಡೆಲ್ ಸೂಚಿಸದಿದ್ದಾಗ ಬಳಸುವ Anthropic ಮಾಡೆಲ್. ಶಿಫಾರಸು: claude-sonnet-4-6.';
$string['setting_max_output_tokens'] = 'ಗರಿಷ್ಠ ಔಟ್‌ಪುಟ್ ಟೋಕನ್‌ಗಳು';
$string['setting_max_output_tokens_desc'] = 'ಪ್ರತಿ ಕಾಲ್‌ಗೆ Anthropic max_tokens ಮೇಲಿನ ಕಟ್ಟುನಿಟ್ಟಿನ ಮಿತಿ. ಡೀಫಾಲ್ಟ್ 2048.';
$string['settings_heading_limits'] = 'ಮಿತಿಗಳು ಮತ್ತು ಕೋಟಾಗಳು';
$string['settings_heading_limits_desc'] = 'ವೆಚ್ಚವನ್ನು ನಿಯಂತ್ರಣದಲ್ಲಿಡಲು ಪ್ರತಿ ಬ್ಯಾಚ್ ಮತ್ತು ಪ್ರತಿ ಕಸ್ಟಮರ್ ಮಿತಿಗಳು.';
$string['setting_max_recommendations'] = 'ಪ್ರತಿ ಬ್ಯಾಚ್‌ಗೆ ಗರಿಷ್ಠ ಶಿಫಾರಸುಗಳು';
$string['setting_max_recommendations_desc'] = 'ಪ್ರತಿ ಜನರೇಶನ್‌ನಲ್ಲಿ ಶಿಫಾರಸುಗಳ ಗರಿಷ್ಠ ಮಿತಿ. ಡೀಫಾಲ್ಟ್ 5.';
$string['setting_max_history_items'] = 'ಗರಿಷ್ಠ ಕಂಪ್ಲೀಷನ್ ಇತಿಹಾಸ ಐಟಂಗಳು';
$string['setting_max_history_items_desc'] = 'ಕಲಿಯುವವರ ಇತ್ತೀಚಿನ ಎಷ್ಟು ಕಂಪ್ಲೀಷನ್‌ಗಳನ್ನು ಪ್ರಾಂಪ್ಟ್‌ಗೆ ಸೇರಿಸಬೇಕು. ಡೀಫಾಲ್ಟ್ 50.';
$string['setting_daily_cost_cap'] = 'ಪ್ರತಿ ಕಸ್ಟಮರ್ ದೈನಂದಿನ ಟೋಕನ್ ಕ್ಯಾಪ್';
$string['setting_daily_cost_cap_desc'] = 'ಪ್ರತಿ ಕಸ್ಟಮರ್‌ಗೆ ದಿನಕ್ಕೆ ಶಿಫಾರಸು ಜನರೇಶನ್ ಬಳಸುವ ಟೋಕನ್‌ಗಳ (ಇನ್‌ಪುಟ್ + ಔಟ್‌ಪುಟ್) ಸಾಫ್ಟ್ ಕ್ಯಾಪ್. ಮಿತಿ ಮೀರಿದ ನಂತರ, ಮಧ್ಯರಾತ್ರಿಯವರೆಗೆ generate.php ಎರರ್ ತೋರಿಸುತ್ತದೆ.';
$string['settings_heading_prompt'] = 'ಪ್ರಾಂಪ್ಟ್ ಕಾನ್ಫಿಗರೇಶನ್';
$string['settings_heading_prompt_desc'] = 'ಪ್ರಾಂಪ್ಟ್‌ಗಳ ವರ್ಷನ್‌ಗಳನ್ನು ಕೋಡ್‌ನಲ್ಲಿ ನಿರ್ವಹಿಸಲಾಗುತ್ತದೆ (prompt_builder::VERSION). ಈ ಫ್ರೀ-ಟೆಕ್ಸ್ಟ್ ಫೀಲ್ಡ್ ಮಾಹಿತಿಗಾಗಿ ಮಾತ್ರ.';
$string['setting_prompt_template_note'] = 'ಪ್ರಾಂಪ್ಟ್ ಟೆಂಪ್ಲೇಟ್ ನೋಟ್';
$string['setting_prompt_template_note_desc'] = 'ಈಗಿನ ಪ್ರಾಂಪ್ಟ್ ಟೆಂಪ್ಲೇಟ್ ಬಗ್ಗೆ ಮುಕ್ತ ನೋಟ್‌ಗಳು — ಅಡ್ಮಿನ್ UI ನಲ್ಲಿ ಮಾತ್ರ ಕಾಣಿಸುತ್ತವೆ. ಇವು prompt_builder ಅನ್ನು ಓವರ್‌ರೈಡ್ ಮಾಡುವುದಿಲ್ಲ.';
$string['block_title'] = 'ನಿಮಗಾಗಿ ಶಿಫಾರಸುಗಳು';
$string['block_empty'] = 'ನಿಮಗೆ ಇನ್ನೂ ಯಾವುದೇ ಶಿಫಾರಸುಗಳಿಲ್ಲ. ಸ್ವಲ್ಪ ಸಮಯದ ನಂತರ ಮತ್ತೆ ನೋಡಿ.';
$string['block_disabled'] = 'AI ಶಿಫಾರಸುಗಳು ಸದ್ಯ ಆಫ್ ಆಗಿವೆ.';
$string['block_view_all'] = 'ಎಲ್ಲವನ್ನೂ ನೋಡಿ';
$string['block_dismiss'] = 'ಆಸಕ್ತಿ ಇಲ್ಲ';
$string['block_why'] = 'ಇದು ಯಾಕೆ?';
$string['block_score'] = 'ಹೊಂದಾಣಿಕೆ';
$string['tokens_used_today'] = 'ಇಂದು ಬಳಸಿದ ಟೋಕನ್‌ಗಳು (ಇಡೀ ಕಸ್ಟಮರ್): {$a->used} / {$a->cap}';
$string['recommendation_card'] = 'ಶಿಫಾರಸು #{$a}';
