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
 * Kannada strings for local_sentientia_ai.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_ai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia AI ಗೇಟ್‌ವೇ';
$string['sentientia_ai:viewledger'] = 'AI ಖರ್ಚಿನ ಲೆಡ್ಜರ್ ನೋಡಿ';
$string['sentientia_ai:manage'] = 'AI ಗೇಟ್‌ವೇ ರನ್‌ಟೈಮ್ ಕಂಟ್ರೋಲ್‌ಗಳನ್ನು ನಿರ್ವಹಿಸಿ';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'ಪ್ರತಿಯೊಂದು Sentientia AI ಫೀಚರ್ ಬಳಸುವ ಒಂದೇ ಒಂದು ಕೇಂದ್ರ ಕೀ. ಮೈಗ್ರೇಶನ್ ಸಮಯದಲ್ಲಿ ಪ್ರತಿ ಪ್ಲಗಿನ್‌ನ ಹಳೆಯ ಕೀಗಳು ಫಾಲ್‌ಬ್ಯಾಕ್ ಆಗಿ ಕೆಲಸ ಮಾಡುತ್ತಲೇ ಇರುತ್ತವೆ; ಎಲ್ಲವನ್ನೂ ಈ ಕೀಗೆ ಒಗ್ಗೂಡಿಸಿ, ಹಳೆಯ ಕೀಗಳನ್ನು ಕ್ಲಿಯರ್ ಮಾಡಿ.';
$string['setting_api_key'] = 'API ಕೀ';
$string['setting_api_key_desc'] = 'Anthropic API ಕೀ. ಇದನ್ನು ಎಂದಿಗೂ ಕಮಿಟ್ ಮಾಡುವುದಿಲ್ಲ, ಲಾಗ್ ಮಾಡುವುದಿಲ್ಲ, ಎರರ್ ಮೆಸೇಜ್‌ಗಳಲ್ಲಿ ಸೇರಿಸುವುದಿಲ್ಲ. ಲೈವ್ ಕಾಲ್‌ಗಳಿಗೆ ಇದರ ಜೊತೆಗೆ ಎರಡೂ ಗೇಟ್‌ವೇ ಫೀಚರ್ ಫ್ಲ್ಯಾಗ್‌ಗಳು ಆನ್ ಆಗಿರಬೇಕು ಮತ್ತು ಕೋಟಾದಲ್ಲಿ ಜಾಗ ಉಳಿದಿರಬೇಕು.';
$string['setting_default_model'] = 'ಡೀಫಾಲ್ಟ್ ಮಾಡೆಲ್';
$string['setting_default_model_desc'] = 'ಕಾಲ್ ಮಾಡುವ ಫೀಚರ್ ಯಾವುದೇ ಮಾಡೆಲ್ ಸೂಚಿಸದಿದ್ದಾಗ ಬಳಸುವ ಮಾಡೆಲ್ (ಉದಾ. claude-sonnet-4-6).';
$string['settings_heading_quotas'] = 'ಫೇಲ್-ಕ್ಲೋಸ್ಡ್ ಖರ್ಚಿನ ಕೋಟಾಗಳು';
$string['settings_heading_quotas_desc'] = 'ಖರ್ಚಿನ ಲೆಡ್ಜರ್ ಆಧಾರದ ಮೇಲೆ ಪ್ರತಿ ಲೈವ್ ಕಾಲ್‌ಗೆ ಮೊದಲೇ ಜಾರಿಯಾಗುವ ಕಟ್ಟುನಿಟ್ಟಾದ ಮಿತಿಗಳು. ಸೊನ್ನೆ ಅಥವಾ ಖಾಲಿ ಮೌಲ್ಯ ಎಂದರೆ ಯಾವುದೇ ಲೈವ್ ಕಾಲ್ ಇಲ್ಲ (ಎಂದಿಗೂ ಅನ್‌ಲಿಮಿಟೆಡ್ ಅಲ್ಲ) — ಅನುಮೋದನೆ ಪಡೆದ AI ಬಜೆಟ್ ನಿರ್ಧಾರದ ಪ್ರಕಾರ ಕಟ್ಟುನಿಟ್ಟಾದ ಮಿತಿ (ಮೆಮೋ 2026-08-04, Addendum A). ಅವಧಿಗಳು ಸರ್ವರ್‌ನ ಲೋಕಲ್ ಕ್ಯಾಲೆಂಡರ್ ದಿನಗಳು/ತಿಂಗಳುಗಳು. ವೆಚ್ಚಗಳು ಬಜೆಟ್‌ಗಾಗಿ ಪ್ರೈಸಿಂಗ್-ಮ್ಯಾಪ್ ಆಧಾರಿತ ಅಂದಾಜುಗಳು, ಇನ್‌ವಾಯ್ಸ್‌ಗಳಲ್ಲ.';
$string['setting_daily_tokens_global'] = 'ದಿನದ ಟೋಕನ್ ಮಿತಿ (ಗ್ಲೋಬಲ್)';
$string['setting_daily_tokens_global_desc'] = 'ಎಲ್ಲಾ ಕಸ್ಟಮರ್‌ಗಳು ಮತ್ತು ಫೀಚರ್‌ಗಳು ಸೇರಿ ಒಂದು ಕ್ಯಾಲೆಂಡರ್ ದಿನಕ್ಕೆ ಅನುಮತಿಸಲಾದ ಲೈವ್ ಟೋಕನ್‌ಗಳು (ಇನ್‌ಪುಟ್ + ಔಟ್‌ಪುಟ್). 0 = ಲೈವ್ ಕಾಲ್‌ಗಳು ಬ್ಲಾಕ್.';
$string['setting_daily_tokens_customer'] = 'ದಿನದ ಟೋಕನ್ ಮಿತಿ (ಪ್ರತಿ ಕಸ್ಟಮರ್)';
$string['setting_daily_tokens_customer_desc'] = 'ಯಾವುದೇ ಒಬ್ಬ ಕಸ್ಟಮರ್‌ಗೆ ಒಂದು ಕ್ಯಾಲೆಂಡರ್ ದಿನಕ್ಕೆ ಅನುಮತಿಸಲಾದ ಲೈವ್ ಟೋಕನ್‌ಗಳು. 0 = ಲೈವ್ ಕಾಲ್‌ಗಳು ಬ್ಲಾಕ್.';
$string['setting_monthly_cost_cap'] = 'ತಿಂಗಳ ವೆಚ್ಚದ ಮಿತಿ (USD, ಅಂದಾಜು)';
$string['setting_monthly_cost_cap_desc'] = 'ಪ್ರತಿ ಕ್ಯಾಲೆಂಡರ್ ತಿಂಗಳಿಗೆ ಅಂದಾಜು ಖರ್ಚಿನ ಗರಿಷ್ಠ ಮಿತಿ. ಲೆಡ್ಜರ್‌ನ ಈ ತಿಂಗಳ ಇದುವರೆಗಿನ ಅಂದಾಜು ಈ ಮಿತಿಯನ್ನು ತಲುಪಿದಾಗ, ಮುಂದಿನ ಲೈವ್ ಕಾಲ್‌ಗಳನ್ನು ನಿರಾಕರಿಸಲಾಗುತ್ತದೆ. 0 = ಲೈವ್ ಕಾಲ್‌ಗಳು ಬ್ಲಾಕ್.';
$string['ledger_title'] = 'AI ಖರ್ಚಿನ ಲೆಡ್ಜರ್';
$string['ledger_intro'] = 'ಪ್ರತಿಯೊಂದು ಗೇಟ್‌ವೇ ಕಾಲ್ — ಮಾಕ್, ಲೈವ್, ವಿಫಲವಾದ ಅಥವಾ ನಿರಾಕರಿಸಿದ — ಒಂದು ಸಾಲು. ಪ್ರತಿ ಲೈವ್ ಕಾಲ್‌ಗೆ ಮೊದಲು ಈ ಸಂಖ್ಯೆಗಳ ಆಧಾರದ ಮೇಲೆ ಕೋಟಾಗಳನ್ನು ಜಾರಿಗೊಳಿಸಲಾಗುತ್ತದೆ.';
$string['ledger_today'] = 'ಇಂದು (ಲೈವ್ ಟೋಕನ್‌ಗಳು)';
$string['ledger_month'] = 'ಈ ತಿಂಗಳು ಇದುವರೆಗೆ (ಅಂದಾಜು USD)';
$string['ledger_bycomponent'] = 'ಕಳೆದ 30 ದಿನಗಳು, ಫೀಚರ್ ಪ್ರಕಾರ';
$string['ledger_recent'] = 'ಇತ್ತೀಚಿನ ಕಾಲ್‌ಗಳು';
$string['ledger_col_time'] = 'ಸಮಯ';
$string['ledger_col_component'] = 'ಫೀಚರ್';
$string['ledger_col_purpose'] = 'ಉದ್ದೇಶ';
$string['ledger_col_user'] = 'ಯೂಸರ್';
$string['ledger_col_model'] = 'ಮಾಡೆಲ್';
$string['ledger_col_tokens'] = 'ಟೋಕನ್‌ಗಳು (ಇನ್/ಔಟ್)';
$string['ledger_col_cost'] = 'ಅಂದಾಜು ವೆಚ್ಚ';
$string['ledger_col_mode'] = 'ಮೋಡ್';
$string['ledger_col_calls'] = 'ಕಾಲ್‌ಗಳು';
$string['ledger_empty'] = 'ಇನ್ನೂ ಯಾವುದೇ ಗೇಟ್‌ವೇ ಕಾಲ್‌ಗಳು ದಾಖಲಾಗಿಲ್ಲ.';
$string['privacy:exportpath'] = 'AI ಗೇಟ್‌ವೇ ಬಳಕೆ';
$string['privacy:metadata:ledger'] = 'ಯೂಸರ್ ಟ್ರಿಗರ್ ಮಾಡುವ ಪ್ರತಿ AI ಕಾಲ್‌ಗೆ AI ಗೇಟ್‌ವೇ ಖರ್ಚಿನ ಲೆಡ್ಜರ್ ಒಂದು ಸಾಲನ್ನು ದಾಖಲಿಸುತ್ತದೆ. ಪ್ರಾಂಪ್ಟ್ ಮತ್ತು ರೆಸ್ಪಾನ್ಸ್ ಟೆಕ್ಸ್ಟ್ ಅನ್ನು ಎಂದಿಗೂ ಸಂಗ್ರಹಿಸುವುದಿಲ್ಲ — ಕೇವಲ ಬಳಕೆಯ ಲೆಕ್ಕ ಮಾತ್ರ.';
$string['privacy:metadata:ledger:userid'] = 'ಯಾರ ಆ್ಯಕ್ಷನ್‌ನಿಂದ AI ಕಾಲ್ ಟ್ರಿಗರ್ ಆಯಿತೋ ಆ ಯೂಸರ್.';
$string['privacy:metadata:ledger:component'] = 'ಕಾಲ್ ಮಾಡಿದ ಪ್ಲಗಿನ್.';
$string['privacy:metadata:ledger:purpose'] = 'ಕಾಲ್‌ನ ಘೋಷಿತ ಉದ್ದೇಶ (ಉದಾ. quiz_generation).';
$string['privacy:metadata:ledger:model'] = 'ಬಳಸಿದ AI ಮಾಡೆಲ್.';
$string['privacy:metadata:ledger:prompttokens'] = 'API ತಿಳಿಸಿದ ಇನ್‌ಪುಟ್ ಟೋಕನ್‌ಗಳು.';
$string['privacy:metadata:ledger:completiontokens'] = 'API ತಿಳಿಸಿದ ಔಟ್‌ಪುಟ್ ಟೋಕನ್‌ಗಳು.';
$string['privacy:metadata:ledger:estcost'] = 'USD ನಲ್ಲಿ ಕಾಲ್‌ನ ಅಂದಾಜು ವೆಚ್ಚ.';
$string['privacy:metadata:ledger:mode'] = 'ಕಾಲ್ ಮಾಕ್, ಲೈವ್, ವಿಫಲವಾದ ಅಥವಾ ನಿರಾಕರಿಸಿದ ಕಾಲ್ ಆಗಿತ್ತೇ ಎಂಬುದು.';
$string['privacy:metadata:ledger:timecreated'] = 'ಕಾಲ್ ಮಾಡಿದ ಸಮಯ.';
$string['privacy:metadata:anthropic'] = 'ಲೈವ್ ಮೋಡ್ ಆನ್ ಆಗಿದ್ದಾಗ, ರೆಸ್ಪಾನ್ಸ್ ಜನರೇಟ್ ಮಾಡಲು ಪ್ರಾಂಪ್ಟ್ ಟೆಕ್ಸ್ಟ್ ಅನ್ನು Anthropic API ಗೆ ಕಳುಹಿಸಲಾಗುತ್ತದೆ. ಪ್ರಾಂಪ್ಟ್‌ಗಳಲ್ಲಿ ಉದ್ಯೋಗಿಗಳ PII ಸೇರಿಸಬಾರದು (ಕಾಲ್ ಮಾಡುವ ಫೀಚರ್‌ಗಳ ಇನ್‌ಪುಟ್ ನಿಯಮಗಳು ಇದನ್ನು ಜಾರಿಗೊಳಿಸುತ್ತವೆ).';
$string['privacy:metadata:anthropic:prompttext'] = 'AI ರಿಕ್ವೆಸ್ಟ್‌ನ ಪ್ರಾಂಪ್ಟ್ ಟೆಕ್ಸ್ಟ್.';
