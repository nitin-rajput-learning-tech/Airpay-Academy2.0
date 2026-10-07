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
 * One place that decides which Mustache engine class this plugin instantiates.
 *
 * Moodle 5.1 bundles Mustache 2.x and autoloads it through PSR-0 as the underscore class
 * \Mustache_Engine. Moodle 5.2 and 5.3 bundle Mustache 3.0, which is autoloaded through PSR-4
 * as \Mustache\Engine only; the legacy underscore aliases live in lib/mustache/src/compat.php,
 * which nothing loads, so `new \Mustache_Engine()` is a class-not-found fatal there. Three
 * call sites (tenant-override email rendering, the editor's AJAX preview and the template
 * preview web service) hit exactly that. They all go through engine() now, so the same code
 * runs unchanged on 5.1, 5.2 and 5.3.
 *
 * @package    local_sentientia_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the Mustache engine for the Moodle version that is running.
 */
class mustache_factory {

    /**
     * A plain Mustache engine (no loader, no helpers) for rendering one string template.
     *
     * Prefers the Mustache 3.0 class and only falls back to the legacy class when it is
     * absent, so a site that loads the compat aliases still gets the current engine.
     *
     * @param array $options Engine options, passed straight through to the constructor.
     * @return \Mustache\Engine|\Mustache_Engine
     */
    public static function engine(array $options = []) {
        if (class_exists(\Mustache\Engine::class)) {
            return new \Mustache\Engine($options);
        }
        return new \Mustache_Engine($options);
    }
}
