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
 * Hindi strings for block_sentientia_cron_health.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    block_sentientia_cron_health
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia क्रॉन हेल्थ';
$string['sentientia_cron_health:addinstance'] = 'Airpay क्रॉन हेल्थ ब्लॉक जोड़ें';
$string['sentientia_cron_health:myaddinstance'] = 'मेरे Moodle पेज पर Airpay क्रॉन हेल्थ ब्लॉक जोड़ें';
$string['kpi_stuck_airpay'] = 'Airpay टास्क अटके हुए';
$string['kpi_stuck_other'] = 'अन्य टास्क अटके हुए';
$string['kpi_in_backoff'] = 'फ़ेल्यर बैकऑफ़ में';
$string['stuck_airpay_heading'] = 'अटके हुए Airpay टास्क';
$string['in_backoff_heading'] = 'फ़ेल्यर बैकऑफ़ में टास्क';
$string['view_task_logs'] = 'पूरे टास्क लॉग देखें →';
$string['severity_ok'] = 'ठीक';
$string['severity_warning'] = 'चेतावनी';
$string['severity_critical'] = 'गंभीर';
$string['kpi_aria_label'] = '{$a->value} {$a->label}, गंभीरता {$a->severity}';
$string['region_label'] = 'Airpay क्रॉन हेल्थ सारांश';
$string['overdue_label'] = '{$a} की देरी';
$string['privacy:metadata'] = 'क्रॉन हेल्थ ब्लॉक कोई भी पर्सनल डेटा सेव नहीं करता। यह Moodle की कोर टेबल्स से शेड्यूल्ड टास्क की स्थिति पढ़ता है।';
