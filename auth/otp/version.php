<?php
// This file is not a part of Moodle - http://moodle.org/
// This is a none core contributed module.
//
// This is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// The GNU General Public License
// can be see at <http://www.gnu.org/licenses/>.

/**
 * ADwebservice authentication plugin version specification.
 *
 * @package    auth
 * @subpackage otp
 * @copyright  2022 Sreenivas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2022122900;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2014050800;        // Requires this Moodle version
$plugin->component = 'auth_otp';       // Full name of the plugin (used for diagnostics)
