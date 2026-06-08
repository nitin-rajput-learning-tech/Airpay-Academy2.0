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
 * Library functions — Airpay Organization Engine.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get org logo URL by file item ID.
 *
 * Drop-in replacement for costcenter_logo() from local/costcenter/lib.php.
 * Can be called as sentientia_org_logo($itemid) from core_renderer.
 *
 * @param int $itemid  File item ID stored in org_logo / costcenter_logo field
 * @return string  Logo URL or empty string
 */
function sentientia_org_logo(int $itemid): string {
    return \local_sentientia_org\branding_manager::get_logo_url($itemid);
}

/**
 * Serve plugin files (logos).
 *
 * Required callback for pluginfile.php to serve org_logo files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context  $context
 * @param string   $filearea
 * @param array    $args
 * @param bool     $forcedownload
 * @param array    $options
 * @return bool
 */
function local_sentientia_org_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'org_logo') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_sentientia_org', $filearea, $itemid, $filepath, $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
    return true;
}
