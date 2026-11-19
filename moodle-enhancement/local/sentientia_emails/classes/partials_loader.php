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
 * Partials loader for edited (DB override) e-mail bodies: this plugin's e-mail partials only.
 *
 * It extends core's own loader, so the same class works on Moodle 5.1 (Mustache 2.x,
 * \Mustache_Loader_FilesystemLoader) and on 5.2/5.3 (Mustache 3.0, \Mustache\Loader\FilesystemLoader),
 * and a partial resolves exactly as it does for a file template (theme overrides included).
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves {{> local_sentientia_emails/partials/NAME}} and nothing else.
 *
 * An override body is written by a template manager. It may include the e-mail partials the Design
 * Studio offers; any other name (another component's template, this plugin's manage/* page templates,
 * a ../ path) loads as an empty template, which is what Mustache does for a partial it cannot find.
 */
class partials_loader extends \core\output\mustache_filesystem_loader {

    /** @var string The only partials an override body may include. */
    public const PREFIX = 'local_sentientia_emails/partials/';

    /**
     * Load a partial by name.
     *
     * @param string $name the partial name, e.g. local_sentientia_emails/partials/footer_note
     * @return string Mustache source; '' for a name outside PREFIX or a partial that does not exist
     */
    public function load($name) {
        $name = (string) $name;
        if (!self::is_allowed($name)) {
            return '';
        }
        try {
            return parent::load($name);
        } catch (\Exception $e) {
            // No such file: mustache_template_finder throws moodle_exception (\core\exception\moodle_exception
            // on 5.2+, which a catch of the \moodle_exception alias may not match), the Mustache loader its
            // UnknownTemplateException (namespaced differently on 5.1 and 5.3). Either way: a missing partial
            // renders as nothing, as Mustache does itself.
            return '';
        }
    }

    /**
     * Whether an override body may include this partial.
     *
     * @param string $name
     * @return bool
     */
    public static function is_allowed(string $name): bool {
        if (!str_starts_with($name, self::PREFIX)) {
            return false;
        }
        return (bool) preg_match('/^[a-z0-9_]+$/', substr($name, strlen(self::PREFIX)));
    }
}
