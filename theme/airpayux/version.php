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
 * Airpay Academy UX theme — forked from epsilon (BizLMS).
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services (forked from eAbyas epsilon)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ADR-032 exams code fix 4 (2026-09-30): core_renderer::custom_secured_redirection() reads the exam row through
// \local_sentientia_exams\exam_manager (guarded by class_exists) instead of running SQL on {local_onlinetests},
// a table production does not define, and its redirects point at /local/sentientia_exams/. No template, SCSS
// or string change; no flag (it removes a failure, it adds no surface).
$plugin->version   = 2026100100;
$plugin->requires  = 2022041900;
$plugin->component = 'theme_airpayux';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '1.0.1-beta';
