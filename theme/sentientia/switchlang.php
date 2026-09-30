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
 * Apply a choice from the sidebar language switcher (persona-pass fix D7).
 *
 * Reached from the links that theme_sentientia\language_switcher::get_context()
 * builds: /theme/sentientia/switchlang.php?lang=hi&sesskey=...&returnurl=...
 *
 * Why an endpoint and not core's ?lang=xx: core only sets $SESSION->lang, so
 * the choice was lost at the next login and, being a GET that changes state,
 * carried no sesskey. This one checks the sesskey, applies the language at once
 * and saves it to the user's profile (see language_switcher::switch_to()).
 *
 * Behind the default-OFF flag ux.languageSwitcher.enabled: while it is off this
 * page refuses, so nothing changes for a site that has not turned it on.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login(null, false);
require_sesskey();

$PAGE->set_url(new moodle_url('/theme/sentientia/switchlang.php'));
$PAGE->set_context(context_system::instance());

if (!\theme_sentientia\language_switcher::is_enabled()) {
    throw new \moodle_exception('langswitch_disabled', 'theme_sentientia');
}

$lang = required_param('lang', PARAM_SAFEDIR);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

if (!\theme_sentientia\language_switcher::switch_to($lang)) {
    throw new \moodle_exception('langswitch_invalid', 'theme_sentientia');
}

redirect($returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/my/'));
