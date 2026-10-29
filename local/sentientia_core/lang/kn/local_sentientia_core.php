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
 * Kannada strings for local_sentientia_core.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'Sentientia Core ಪ್ಲಗಿನ್ ಸಂಸ್ಥೆಯ ಯೂನಿಟ್ ಸದಸ್ಯತ್ವದ ಸಾಲುಗಳನ್ನು (ಯೂಸರ್, ಯೂನಿಟ್, ರೋಲ್, ನೇರ ಮ್ಯಾನೇಜರ್) local_sentientia_org_member ನಲ್ಲಿ ಮತ್ತು ಇಂಪೋರ್ಟ್ ಮಾಡಿದ BizLMS ಅಡ್ಮಿನ್ ಲಾಗ್ ಅನ್ನು local_sentientia_admin_log ನಲ್ಲಿ ಸಂಗ್ರಹಿಸುತ್ತದೆ (ಯಾರು ಯಾವಾಗ ಕೋರ್ಸ್ ರಚಿಸಿದರು, ಅಪ್‌ಡೇಟ್ ಮಾಡಿದರು ಅಥವಾ ಡಿಲೀಟ್ ಮಾಡಿದರು, ಜೊತೆಗೆ ಆ ವ್ಯಕ್ತಿಯ ಮೊದಲ ಹೆಸರನ್ನು ಹೇಳುವ ವಿವರಣೆ). ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿಯಲ್ಲಿ (ಕಸ್ಟಮರ್ + ಟೆನೆಂಟ್ ಕಾನ್ಫಿಗರೇಶನ್: ಹೆಸರುಗಳು, ರೂಟ್ id ಗಳು, ಸ್ಟೇಟಸ್) ಯಾವುದೇ ವೈಯಕ್ತಿಕ ಡೇಟಾ ಇಲ್ಲ.';
$string['settings_tenant_identity'] = 'ಟೆನೆಂಟ್ ಗುರುತು';
$string['setting_legacy_openpath'] = 'BizLMS open_path ನಿಂದ ಟೆನೆಂಟ್ ಪತ್ತೆಮಾಡಿ (ಲೆಗಸಿ)';
$string['setting_legacy_openpath_desc'] = 'ಇದು ಆನ್ ಆಗಿದ್ದಾಗ (ಡೀಫಾಲ್ಟ್), Sentientia ಯೂಸರ್‌ನ ಟೆನೆಂಟ್ ಅನ್ನು ಲೆಗಸಿ BizLMS <code>open_path</code> ಪ್ರೊಫೈಲ್ ಫೀಲ್ಡ್‌ನಿಂದ ಪತ್ತೆಮಾಡುತ್ತದೆ — ಈಗಿನ ಪ್ರೊಡಕ್ಷನ್ ವರ್ತನೆಯಂತೆಯೇ. ಇದನ್ನು ಆಫ್ ಮಾಡುವುದನ್ನು Sentientia ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ ಸಿದ್ಧವಾದ ನಂತರ ADR-018 Wave 3+ ಗಾಗಿ ಮೀಸಲಿಡಲಾಗಿದೆ; ಅಲ್ಲಿಯವರೆಗೆ ಸರ್ವೀಸ್ ಹೇಗಿದ್ದರೂ ಸುರಕ್ಷಿತವಾಗಿ <code>open_path</code> ಗೆ ಹಿಂತಿರುಗುತ್ತದೆ. ಪ್ರೊಡಕ್ಷನ್‌ನಲ್ಲಿ ಆನ್ ಆಗಿಯೇ ಇಡಿ.';
$string['settings_org'] = 'ಸಂಸ್ಥೆಯ ಹೈರಾರ್ಕಿ';
$string['setting_org_legacy'] = 'BizLMS ನಿಂದ ಮ್ಯಾನೇಜರ್ / ಸಂಸ್ಥೆ ಪತ್ತೆಮಾಡಿ (ಲೆಗಸಿ)';
$string['setting_org_legacy_desc'] = 'ಇದು ಆನ್ ಆಗಿದ್ದಾಗ (ಡೀಫಾಲ್ಟ್), Sentientia ಯೂಸರ್‌ನ ಮ್ಯಾನೇಜರ್ ಅನ್ನು ಲೆಗಸಿ BizLMS <code>open_supervisorid</code> ಫೀಲ್ಡ್‌ನಿಂದ ಪತ್ತೆಮಾಡುತ್ತದೆ — ಈಗಿನ ಪ್ರೊಡಕ್ಷನ್ ವರ್ತನೆಯಂತೆಯೇ. ಇದನ್ನು ಆಫ್ ಮಾಡುವುದನ್ನು Sentientia ಸಂಸ್ಥೆ ಮಾಡೆಲ್ ಸಿದ್ಧವಾದ ನಂತರ ADR-020 Wave 3.2+ ಗಾಗಿ ಮೀಸಲಿಡಲಾಗಿದೆ; ಅಲ್ಲಿಯವರೆಗೆ ಸರ್ವೀಸ್ ಹೇಗಿದ್ದರೂ ಸುರಕ್ಷಿತವಾಗಿ <code>open_supervisorid</code> ಗೆ ಹಿಂತಿರುಗುತ್ತದೆ. ಪ್ರೊಡಕ್ಷನ್‌ನಲ್ಲಿ ಆನ್ ಆಗಿಯೇ ಇಡಿ.';
$string['setting_org_dualwrite'] = 'ಲೆಗಸಿ ಸಂಸ್ಥೆ ಗ್ರಾಫ್ ಅನ್ನು Sentientia ಸಂಸ್ಥೆ ಮಾಡೆಲ್‌ಗೆ ಮಿರರ್ ಮಾಡಿ (ಡ್ಯುಯಲ್-ರೈಟ್)';
$string['setting_org_dualwrite_desc'] = 'ಇದು ಆನ್ ಆಗಿದ್ದಾಗ, ಒಂದು ಶೆಡ್ಯೂಲ್ಡ್ ಟಾಸ್ಕ್ ಆಗಾಗ ಲೆಗಸಿ BizLMS ಸಂಸ್ಥೆ ಗ್ರಾಫ್ ಅನ್ನು (<code>open_path</code> ವೆಚ್ಚ ಕೇಂದ್ರದ ಟ್ರೀ + <code>open_supervisorid</code> ಮ್ಯಾನೇಜರ್ ಲಿಂಕ್‌ಗಳು) Sentientia ಸಂಸ್ಥೆ ಮಾಡೆಲ್ ಟೇಬಲ್‌ಗಳಿಗೆ ಮಿರರ್ ಮಾಡುತ್ತದೆ. ಲೆಗಸಿ ಗ್ರಾಫ್ ಇನ್ನೂ ಮುಖ್ಯ ಮೂಲವಾಗಿಯೇ ಉಳಿಯುತ್ತದೆ — ಮುಂದೆ ಬರುವ ಕಟ್‌ಓವರ್‌ಗೆ ಮುಂಚೆ ಇದು ಹೊಸ ಟೇಬಲ್‌ಗಳನ್ನು ಅಪ್‌ಡೇಟ್ ಆಗಿ ಇಡುತ್ತದೆ ಅಷ್ಟೇ. ಡೀಫಾಲ್ಟ್ ಆಫ್ (ಟಾಸ್ಕ್ ಏನೂ ಮಾಡುವುದಿಲ್ಲ): ಪ್ಯಾರಿಟಿ ಚೆಕ್ ಅಥವಾ ರಿಹರ್ಸಲ್‌ಗಾಗಿ ಮಾಡೆಲ್ ತುಂಬಿಸಲು ಮಾತ್ರ ಆನ್ ಮಾಡಿ, ನಂತರ ಫ್ಲಿಪ್ ಮಾಡುವ ಬಗ್ಗೆ ಯೋಚಿಸುವ ಮೊದಲು <code>cli/parity_check_org.php</code> ರನ್ ಮಾಡಿ. ಇದು ಮ್ಯಾನೇಜರ್ ಪತ್ತೆಮಾಡುವ ವಿಧಾನವನ್ನು ಬದಲಾಯಿಸುವುದಿಲ್ಲ — ಅದನ್ನು ಇನ್ನೂ ಮೇಲಿನ “BizLMS ನಿಂದ ಮ್ಯಾನೇಜರ್ / ಸಂಸ್ಥೆ ಪತ್ತೆಮಾಡಿ (ಲೆಗಸಿ)” ಫ್ಲ್ಯಾಗ್ ನಿಯಂತ್ರಿಸುತ್ತದೆ.';
$string['task_reconcile_org'] = 'ಲೆಗಸಿ ಗ್ರಾಫ್‌ನಿಂದ Sentientia ಸಂಸ್ಥೆ ಮಾಡೆಲ್ ಅನ್ನು ರಿಕನ್ಸೈಲ್ ಮಾಡಿ';
$string['error_outoftenant'] = 'ನಿಮಗೆ ಈ ರಿಸೋರ್ಸ್‌ಗೆ ಆಕ್ಸೆಸ್ ಇಲ್ಲ — ಇದು ಬೇರೆ ಟೆನೆಂಟ್‌ಗೆ ಸೇರಿದೆ.';
$string['sentientia_core:managetenants'] = 'Sentientia ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ ನಿರ್ವಹಿಸಿ';
$string['settings_tenant_registry'] = 'ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ';
$string['setting_legacy_registry'] = 'ಹಾರ್ಡ್‌ಕೋಡ್ ಮಾಡಿದ ಅಲೋ-ಲಿಸ್ಟ್‌ನಿಂದ ಟೆನೆಂಟ್‌ಗಳನ್ನು ಪರಿಶೀಲಿಸಿ (ಲೆಗಸಿ)';
$string['setting_legacy_registry_desc'] = 'ಇದು ಆನ್ ಆಗಿದ್ದಾಗ (ಡೀಫಾಲ್ಟ್), Sentientia ಟೆನೆಂಟ್ ರೂಟ್‌ಗಳನ್ನು ಲೆಗಸಿ ಹಾರ್ಡ್‌ಕೋಡ್ ಮಾಡಿದ ಅಲೋ-ಲಿಸ್ಟ್ (<code>[1, 77, 177]</code>) ಜೊತೆ ಪರಿಶೀಲಿಸುತ್ತದೆ — ಈಗಿನ ಪ್ರೊಡಕ್ಷನ್ ವರ್ತನೆಯಂತೆಯೇ. ಇದನ್ನು ಆಫ್ ಮಾಡಿದರೆ, ಬದಲಿಗೆ Sentientia ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿಯನ್ನು (ಕೆಳಗಿನ <em>ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ ನಿರ್ವಹಿಸಿ</em> ಪುಟ) ಓದುತ್ತದೆ. ರಿಜಿಸ್ಟ್ರಿಯನ್ನು ಸೀಡ್ ಮಾಡಿ, <code>cli/parity_check_tenants.php</code> ಮೂಲಕ 100% ಪ್ಯಾರಿಟಿ ಖಚಿತಪಡಿಸಿಕೊಂಡ ನಂತರವೇ ಆಫ್ ಮಾಡಿ (ಮೊದಲು ಕ್ಲೋನ್ DB ಯಲ್ಲಿ ರಿಹರ್ಸಲ್ ಮಾಡಿ). ಕಟ್‌ಓವರ್ ಆಗುವವರೆಗೆ ಪ್ರೊಡಕ್ಷನ್‌ನಲ್ಲಿ ಆನ್ ಆಗಿಯೇ ಇಡಿ.';
$string['error_invalidtenant'] = 'ಗೊತ್ತಿಲ್ಲದ ಟೆನೆಂಟ್ — ಈ id ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿಯಲ್ಲಿ ಇಲ್ಲ.';
$string['managetenants'] = 'ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ ನಿರ್ವಹಿಸಿ';
$string['registry_flag_legacy_on'] = 'ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ ಸದ್ಯ ನಿಷ್ಕ್ರಿಯವಾಗಿದೆ — ಟೆನೆಂಟ್ ಪರಿಶೀಲನೆಗೆ ಈಗ ಲೆಗಸಿ ಹಾರ್ಡ್‌ಕೋಡ್ ಮಾಡಿದ ಅಲೋ-ಲಿಸ್ಟ್ ಬಳಕೆಯಾಗುತ್ತಿದೆ. ಇಲ್ಲಿ ನಿರ್ವಹಿಸುವ ಸಾಲುಗಳು "ಹಾರ್ಡ್‌ಕೋಡ್ ಮಾಡಿದ ಅಲೋ-ಲಿಸ್ಟ್‌ನಿಂದ ಟೆನೆಂಟ್‌ಗಳನ್ನು ಪರಿಶೀಲಿಸಿ (ಲೆಗಸಿ)" ಸೆಟ್ಟಿಂಗ್ ಆಫ್ ಆದ ನಂತರವೇ ಜಾರಿಗೆ ಬರುತ್ತವೆ.';
$string['registry_flag_legacy_off'] = 'ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟ್ರಿ ಈಗ ಲೈವ್ ಆಗಿದೆ — ಟೆನೆಂಟ್ ಪರಿಶೀಲನೆ ಈಗ ಈ ಸಾಲುಗಳನ್ನೇ ಓದುತ್ತದೆ. ಇಲ್ಲಿ ಒಂದು ಟೆನೆಂಟ್ ಅನ್ನು ತೆಗೆದುಹಾಕಿದರೆ ಅಥವಾ ಸಸ್ಪೆಂಡ್ ಮಾಡಿದರೆ, ಟೆನೆಂಟ್ ವ್ಯಾಪ್ತಿಯ ರಿಸೋರ್ಸ್‌ಗಳನ್ನು ಯಾರು ಆಕ್ಸೆಸ್ ಮಾಡಬಹುದು ಎಂಬುದರ ಮೇಲೆ ತಕ್ಷಣ ಪರಿಣಾಮ ಬೀರುತ್ತದೆ.';
$string['customers'] = 'ಕಸ್ಟಮರ್‌ಗಳು';
$string['tenants'] = 'ಟೆನೆಂಟ್‌ಗಳು';
$string['tenantcount'] = 'ಟೆನೆಂಟ್‌ಗಳು';
$string['customer_missing'] = '(ಗೊತ್ತಿಲ್ಲದ ಕಸ್ಟಮರ್)';
$string['nocustomers'] = 'ಇನ್ನೂ ಯಾವುದೇ ಕಸ್ಟಮರ್ ಇಲ್ಲ. ಕೆಳಗೆ ಒಬ್ಬರನ್ನು ಸೇರಿಸಿ, ಅಥವಾ cli/seed_tenants.php ರನ್ ಮಾಡಿ.';
$string['notenants'] = 'ಇನ್ನೂ ಯಾವುದೇ ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟರ್ ಆಗಿಲ್ಲ. ಕೆಳಗೆ ಒಂದನ್ನು ಸೇರಿಸಿ, ಅಥವಾ cli/seed_tenants.php ರನ್ ಮಾಡಿ.';
$string['actions'] = 'ಆ್ಯಕ್ಷನ್‌ಗಳು';
$string['suspend'] = 'ಸಸ್ಪೆಂಡ್ ಮಾಡಿ';
$string['activate'] = 'ಸಕ್ರಿಯಗೊಳಿಸಿ';
$string['tenant_statuschanged'] = 'ಟೆನೆಂಟ್ ಸ್ಟೇಟಸ್ ಅಪ್‌ಡೇಟ್ ಆಗಿದೆ.';
$string['customer_saved'] = 'ಕಸ್ಟಮರ್ ಸೇವ್ ಆಗಿದೆ.';
$string['tenant_saved'] = 'ಟೆನೆಂಟ್ ಸೇವ್ ಆಗಿದೆ.';
$string['addcustomer'] = 'ಕಸ್ಟಮರ್ ಸೇರಿಸಿ';
$string['addtenant'] = 'ಟೆನೆಂಟ್ ಸೇರಿಸಿ';
$string['addcustomer_first'] = 'ಟೆನೆಂಟ್ ರಿಜಿಸ್ಟರ್ ಮಾಡುವ ಮೊದಲು ಒಬ್ಬ ಕಸ್ಟಮರ್ ಅನ್ನು ಸೇರಿಸಿ — ಪ್ರತಿ ಟೆನೆಂಟ್ ಯಾವುದಾದರೂ ಒಬ್ಬ ಕಸ್ಟಮರ್‌ಗೆ ಸೇರಿರಬೇಕು.';
$string['status_active'] = 'ಸಕ್ರಿಯ';
$string['status_suspended'] = 'ಸಸ್ಪೆಂಡ್ ಆಗಿದೆ';
$string['status_archived'] = 'ಆರ್ಕೈವ್ ಆಗಿದೆ';
$string['field_customername'] = 'ಕಸ್ಟಮರ್ ಹೆಸರು';
$string['field_shortname'] = 'ಚಿಕ್ಕ ಹೆಸರು';
$string['field_shortname_help'] = 'ಕಸ್ಟಮರ್‌ಗೆ ಒಂದು ಚಿಕ್ಕ, ಯೂನಿಕ್, ಮಷೀನ್-ಸ್ನೇಹಿ ಹ್ಯಾಂಡಲ್ (ಅಕ್ಷರಗಳು, ಸಂಖ್ಯೆಗಳು, _ ಮತ್ತು -). ಉದಾಹರಣೆ: <code>airpay</code>. ಒಳಗೆ ಮಾತ್ರ ಬಳಸಲಾಗುತ್ತದೆ; ಕಲಿಯುವವರಿಗೆ ಕಾಣಿಸುವುದಿಲ್ಲ.';
$string['field_status'] = 'ಸ್ಟೇಟಸ್';
$string['field_rootid'] = 'ಟೆನೆಂಟ್ ರೂಟ್ id';
$string['field_rootid_help'] = 'ಟೆನೆಂಟ್ ರೂಟ್ id. ಈಗ ಇದು BizLMS ವೆಚ್ಚ ಕೇಂದ್ರದ ರೂಟ್ (1 = Airpay, 77 = Public, 177 = ZEEA). ಇದು 0 ಗಿಂತ ದೊಡ್ಡ ಪೂರ್ಣ ಸಂಖ್ಯೆ ಆಗಿರಬೇಕು ಮತ್ತು ಇಡೀ ರಿಜಿಸ್ಟ್ರಿಯಲ್ಲಿ ಯೂನಿಕ್ ಆಗಿರಬೇಕು.';
$string['field_customer'] = 'ಮಾಲೀಕ ಕಸ್ಟಮರ್';
$string['field_tenantname'] = 'ಟೆನೆಂಟ್ ಹೆಸರು';
$string['field_idnumber'] = 'ಎಕ್ಸ್‌ಟರ್ನಲ್ id (ಐಚ್ಛಿಕ)';
$string['field_idnumber_help'] = 'ಒಂದು ಐಚ್ಛಿಕ ಎಕ್ಸ್‌ಟರ್ನಲ್ ಕೀ (ಉದಾಹರಣೆಗೆ HRMS ಐಡೆಂಟಿಫೈಯರ್), ಅಪ್‌ಸ್ಟ್ರೀಮ್ ಸಿಂಕ್ ಜೊತೆ ಈ ಟೆನೆಂಟ್ ಅನ್ನು ರೌಂಡ್-ಟ್ರಿಪ್ ಮಾಡಲು ಬಳಸಲಾಗುತ್ತದೆ. ಅನ್ವಯಿಸದಿದ್ದರೆ ಖಾಲಿ ಬಿಡಿ.';
$string['err_shortname_taken'] = 'ಈ ಚಿಕ್ಕ ಹೆಸರನ್ನು ಈಗಾಗಲೇ ಬೇರೆ ಕಸ್ಟಮರ್ ಬಳಸುತ್ತಿದ್ದಾರೆ.';
$string['err_rootid_positive'] = 'ಟೆನೆಂಟ್ ರೂಟ್ id 0 ಗಿಂತ ದೊಡ್ಡ ಪೂರ್ಣ ಸಂಖ್ಯೆ ಆಗಿರಬೇಕು.';
$string['err_rootid_taken'] = 'ಈ ಟೆನೆಂಟ್ ರೂಟ್ id ಈಗಾಗಲೇ ರಿಜಿಸ್ಟರ್ ಆಗಿದೆ.';
$string['privacy:metadata:org_member'] = 'ಯೂಸರ್‌ನ ಸಂಸ್ಥೆಯ ಯೂನಿಟ್ ಸದಸ್ಯತ್ವ: ಯೂನಿಟ್, ರೋಲ್ ಮತ್ತು ನೇರ ಮ್ಯಾನೇಜರ್';
$string['privacy:metadata:org_member:userid'] = 'ಸದಸ್ಯ';
$string['privacy:metadata:org_member:unitid'] = 'ಯೂಸರ್ ಸೇರಿರುವ ಸಂಸ್ಥೆಯ ಯೂನಿಟ್';
$string['privacy:metadata:org_member:role'] = 'ಯೂನಿಟ್‌ನೊಳಗೆ ಯೂಸರ್‌ನ ರೋಲ್';
$string['privacy:metadata:org_member:managerid'] = 'ಯೂಸರ್‌ನ ನೇರ ಮ್ಯಾನೇಜರ್';
$string['privacy:metadata:org_member:timecreated'] = 'ಸದಸ್ಯತ್ವ ದಾಖಲಾದ ಸಮಯ';
$string['sentientia_core:viewadminlog'] = 'ಇಂಪೋರ್ಟ್ ಮಾಡಿದ ಅಡ್ಮಿನ್ ಲಾಗ್ ನೋಡಿ';
$string['adminlog'] = 'ಇಂಪೋರ್ಟ್ ಮಾಡಿದ ಅಡ್ಮಿನ್ ಲಾಗ್';
$string['adminlog_intro'] = 'BizLMS ಅಡ್ಮಿನ್ ಲಾಗ್ (ಕೋರ್ಸ್ ರಚನೆ, ಅಪ್‌ಡೇಟ್ ಅಥವಾ ಡಿಲೀಟ್) ಮತ್ತು ಬಲ್ಕ್ ಕೋರ್ಸ್ ಅಪ್‌ಲೋಡ್ ಎರರ್‌ಗಳು, ಕಟ್‌ಓವರ್ ಸಮಯದಲ್ಲಿ ಇಂಪೋರ್ಟ್ ಮಾಡಲಾಗಿದೆ. ಇದು ಓದಲು ಮಾತ್ರ ಇರುವ ಹಿಸ್ಟರಿ: ಇಲ್ಲಿ ಯಾವುದನ್ನೂ ಎಡಿಟ್ ಅಥವಾ ಡಿಲೀಟ್ ಮಾಡಲು ಆಗುವುದಿಲ್ಲ. ವಿವರಣೆಗಳಲ್ಲಿ ಕ್ರಮ ಕೈಗೊಂಡ ವ್ಯಕ್ತಿಯ ಮೊದಲ ಹೆಸರು ಇರುತ್ತದೆ, ಆದ್ದರಿಂದ ಈ ಪುಟವನ್ನು ವೈಯಕ್ತಿಕ ಡೇಟಾ ಎಂದು ಪರಿಗಣಿಸಿ.';
$string['adminlog_flagoff'] = 'ಈ ರಿಪೋರ್ಟ್ ಆಫ್ ಆಗಿದೆ. sentientia.legacy_logs.report.enabled ಫೀಚರ್ ಫ್ಲ್ಯಾಗ್ ಆನ್ ಮಾಡಿದಾಗ ಇದು ಆನ್ ಆಗುತ್ತದೆ; ಪುಟ ರಿವ್ಯೂ ಆಗುವವರೆಗೆ ಆ ಫ್ಲ್ಯಾಗ್ ಆಫ್ ಆಗಿರುತ್ತದೆ.';
$string['adminlog_noentries'] = 'ಯಾವುದೇ ಎಂಟ್ರಿ ಹೊಂದಿಕೆಯಾಗುತ್ತಿಲ್ಲ.';
$string['adminlog_notenant'] = 'ನಿಮ್ಮ ಖಾತೆ ಯಾವುದೇ ಟೆನೆಂಟ್‌ಗೆ ಲಿಂಕ್ ಆಗಿಲ್ಲ, ಆದ್ದರಿಂದ ತೋರಿಸಲು ಏನೂ ಇಲ್ಲ.';
$string['adminlog_total'] = '{$a} ಎಂಟ್ರಿಗಳು';
$string['adminlog_col_when'] = 'ಯಾವಾಗ';
$string['adminlog_col_who'] = 'ಯಾರು';
$string['adminlog_col_source'] = 'ಮೂಲ';
$string['adminlog_col_event'] = 'ಈವೆಂಟ್';
$string['adminlog_col_module'] = 'ಮಾಡ್ಯೂಲ್';
$string['adminlog_col_item'] = 'ಐಟಂ';
$string['adminlog_col_description'] = 'ವಿವರಣೆ';
$string['adminlog_source_local_logs'] = 'ಕೋರ್ಸ್ ನಿರ್ವಹಣೆ ಲಾಗ್';
$string['adminlog_source_local_courseerrors'] = 'ಬಲ್ಕ್ ಕೋರ್ಸ್ ಅಪ್‌ಲೋಡ್ ಎರರ್';
$string['adminlog_filter_any'] = 'ಯಾವುದಾದರೂ';
$string['adminlog_filter_apply'] = 'ಫಿಲ್ಟರ್';
$string['adminlog_actor_deleted'] = '{$a} (ಡಿಲೀಟ್ ಆದ ಯೂಸರ್)';
$string['adminlog_actor_unknown'] = 'ಗೊತ್ತಿಲ್ಲ ಅಥವಾ ಅಳಿಸಲಾಗಿದೆ';
$string['privacy:metadata:admin_log'] = 'ಇಂಪೋರ್ಟ್ ಮಾಡಿದ BizLMS ಅಡ್ಮಿನ್ ಲಾಗ್ ಮತ್ತು ಬಲ್ಕ್ ಕೋರ್ಸ್ ಅಪ್‌ಲೋಡ್ ಎರರ್‌ಗಳು: ಯಾರು, ಯಾವುದರ ಮೇಲೆ, ಯಾವಾಗ ಕ್ರಮ ಕೈಗೊಂಡರು. ಒಬ್ಬ ವ್ಯಕ್ತಿಯ ಡೇಟಾ ಅಳಿಸಿದಾಗಲೂ ಇದನ್ನು ಹಿಸ್ಟರಿಯಾಗಿ ಇಡಲಾಗುತ್ತದೆ; ಆ ವ್ಯಕ್ತಿಯನ್ನು ಸಾಲಿನಿಂದ ತೆಗೆದುಹಾಕಲಾಗುತ್ತದೆ.';
$string['privacy:metadata:admin_log:source'] = 'ಈ ಸಾಲು ಯಾವ BizLMS ಲಾಗ್‌ನಿಂದ ಬಂತು';
$string['privacy:metadata:admin_log:event'] = 'ಏನಾಯಿತು (ಇನ್ಸರ್ಟ್, ಅಪ್‌ಡೇಟ್, ಡಿಲೀಟ್, ಅಥವಾ ಅಪ್‌ಲೋಡ್ ಎರರ್)';
$string['privacy:metadata:admin_log:module'] = 'ಎಂಟ್ರಿ ಯಾವ ರೀತಿಯ ಐಟಂ ಬಗ್ಗೆ ಇದೆ';
$string['privacy:metadata:admin_log:description'] = 'ಕ್ರಮ ಕೈಗೊಂಡ ವ್ಯಕ್ತಿಯ ಮೊದಲ ಹೆಸರನ್ನು ಹೊಂದಿರುವ ಫ್ರೀ ಟೆಕ್ಸ್ಟ್; ವ್ಯಕ್ತಿಯ ಡೇಟಾ ಅಳಿಸಿದಾಗ ಇದನ್ನು ಸ್ವಚ್ಛಗೊಳಿಸಲಾಗುತ್ತದೆ';
$string['privacy:metadata:admin_log:itemref'] = 'ಎಂಟ್ರಿ ಯಾವ ಐಟಂ ಬಗ್ಗೆ ಇದೆಯೋ ಅದರ id, ಸಾಮಾನ್ಯವಾಗಿ ಒಂದು ಕೋರ್ಸ್';
$string['privacy:metadata:admin_log:userid'] = 'ಕ್ರಮ ಕೈಗೊಂಡ ಅಥವಾ ಅಪ್‌ಲೋಡ್ ರನ್ ಮಾಡಿದ ವ್ಯಕ್ತಿ';
$string['privacy:metadata:admin_log:usermodified'] = 'BizLMS ನಲ್ಲಿ ಈ ಎಂಟ್ರಿಯನ್ನು ಕೊನೆಯದಾಗಿ ಬದಲಾಯಿಸಿದ ವ್ಯಕ್ತಿ';
$string['privacy:metadata:admin_log:actor_path'] = 'ಎಂಟ್ರಿ ಇಂಪೋರ್ಟ್ ಆದಾಗ ಕ್ರಮ ಕೈಗೊಂಡ ವ್ಯಕ್ತಿಯ ಸಂಸ್ಥೆಯ ಪಾತ್';
$string['privacy:metadata:admin_log:timecreated'] = 'BizLMS ನಲ್ಲಿ ಎಂಟ್ರಿ ಮಾಡಿದ ಸಮಯ';
$string['privacy:metadata:admin_log:timemodified'] = 'BizLMS ನಲ್ಲಿ ಎಂಟ್ರಿಯನ್ನು ಕೊನೆಯದಾಗಿ ಬದಲಾಯಿಸಿದ ಸಮಯ';
$string['privacy:metadata:course_creator'] = 'ಕೋರ್ಸ್‌ನಲ್ಲಿರುವ BizLMS \'ಕೋರ್ಸ್ ರಚಿಸಿದವರು\' ಕಾಲಮ್. ಕೋರ್ಸ್ ರಚಿಸಿದವರ ಡೇಟಾ ಅಳಿಸಿದಾಗಲೂ ಕೋರ್ಸ್ ಉಳಿಯುತ್ತದೆ; ರಚಿಸಿದವರ ಮೌಲ್ಯವನ್ನು 0 ಗೆ ಸೆಟ್ ಮಾಡಲಾಗುತ್ತದೆ.';
$string['privacy:metadata:course_creator:open_coursecreator'] = 'BizLMS ದಾಖಲಿಸಿದಂತೆ, ಕೋರ್ಸ್ ರಚಿಸಿದ ಯೂಸರ್.';
