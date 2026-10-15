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

namespace theme_sentientia;

/**
 * Structural tests for the dropdown bridge (theme_sentientia/dropdown_bridge).
 *
 * The theme ships Bootstrap 4.6, whose data-api reads data-toggle="dropdown". Core 5.x templates
 * (action menus such as the row menu of the certificate templates) carry only data-bs-toggle, so on a
 * click nothing opened. The bridge opens those, and must never touch an element that carries
 * data-toggle (an element with both attributes would be toggled twice: opened and closed at once).
 *
 * The behaviour itself is JavaScript, so this suite guards the wiring and the one selector rule the
 * whole design rests on. The click behaviour is covered by the visual pass (certificate templates row
 * menu, org tree row menu, evaluation question card menu).
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dropdown_bridge_test extends \basic_testcase {

    /**
     * Read a file of the theme.
     *
     * @param string $relative path below theme/sentientia
     * @return string the file content
     */
    private function theme_file(string $relative): string {
        $path = dirname(__DIR__) . '/' . $relative;
        $this->assertFileExists($path, $relative . ' is missing');
        return (string) file_get_contents($path);
    }

    /**
     * The module exists as source and as a build (production serves only the build).
     */
    public function test_module_has_source_and_build(): void {
        $src = $this->theme_file('amd/src/dropdown_bridge.js');
        $build = $this->theme_file('amd/build/dropdown_bridge.min.js');
        $this->assertFileExists(dirname(__DIR__) . '/amd/build/dropdown_bridge.min.js.map');

        $this->assertStringContainsString('@module     theme_sentientia/dropdown_bridge', $src);
        $this->assertStringStartsWith('define("theme_sentientia/dropdown_bridge"', $build);
    }

    /**
     * The loader (started by every theme layout) starts the bridge, in source and in the build.
     */
    public function test_loader_starts_the_bridge(): void {
        $src = $this->theme_file('amd/src/loader.js');
        $this->assertStringContainsString("import * as DropdownBridge from './dropdown_bridge';", $src);
        $this->assertStringContainsString('DropdownBridge.init();', $src);

        $build = $this->theme_file('amd/build/loader.min.js');
        $this->assertStringContainsString('"./dropdown_bridge"', $build);
        $this->assertStringContainsString('DropdownBridge.init()', $build);
    }

    /**
     * The bridge only selects Bootstrap 5 only toggles. An element that carries data-toggle belongs to
     * the Bootstrap 4 data-api, and taking it as well would toggle it twice per click.
     */
    public function test_bridge_never_selects_elements_that_carry_data_toggle(): void {
        foreach (['amd/src/dropdown_bridge.js', 'amd/build/dropdown_bridge.min.js'] as $file) {
            $content = $this->theme_file($file);
            $this->assertMatchesRegularExpression(
                '/\[data-bs-toggle="dropdown"\]:not\(\[data-toggle="dropdown"\]\)/',
                $content,
                $file . ' must select data-bs-toggle toggles that do NOT carry data-toggle'
            );
        }
    }

    /**
     * An element that carries both attributes is Bootstrap 4's only. Where core's Bootstrap 5 data-api is
     * loaded it would match the same element and toggle it a second time (open, then closed at once,
     * FX-20 round 1), so the bridge takes data-bs-toggle off it for the length of the click and puts it
     * back in the next task.
     */
    public function test_bridge_keeps_bootstrap_5_off_elements_with_both_attributes(): void {
        $src = $this->theme_file('amd/src/dropdown_bridge.js');
        $this->assertStringContainsString(
            "const BOTH_SELECTOR = '[data-toggle=\"dropdown\"][data-bs-toggle=\"dropdown\"]';",
            $src
        );
        $this->assertStringContainsString("toggle.removeAttribute('data-bs-toggle');", $src);
        $this->assertStringContainsString(
            "window.setTimeout(() => toggle.setAttribute('data-bs-toggle', 'dropdown'), 0);",
            $src
        );
        // It happens in the capture phase handler, ahead of every document level handler.
        $this->assertMatchesRegularExpression(
            '/const onClickCapture = .*?target\.closest\(BOTH_SELECTOR\).*?hideFromBootstrap5\(both\);/s',
            $src
        );

        $build = $this->theme_file('amd/build/dropdown_bridge.min.js');
        $this->assertStringContainsString(
            '[data-toggle="dropdown"][data-bs-toggle="dropdown"]',
            $build
        );
        $this->assertStringContainsString('removeAttribute("data-bs-toggle")', $build);
    }

    /**
     * The bridge compares the open state before any handler ran (capture phase on window) with the state
     * after every document level handler ran (bubble phase on window). That ordering is what stops it
     * from acting when core's Bootstrap 5 data-api already toggled the menu.
     */
    public function test_bridge_watches_both_phases_on_window(): void {
        $src = $this->theme_file('amd/src/dropdown_bridge.js');
        $this->assertStringContainsString("window.addEventListener('click', onClickCapture, true);", $src);
        $this->assertStringContainsString("window.addEventListener('click', onClickBubble, false);", $src);
        $this->assertStringContainsString('openState(toggle) !== seen.before', $src);
    }

    /**
     * After opening a menu the bridge writes data-toggle on the toggle, so that the Bootstrap 4 data-api
     * can close the menu on an outside click, Escape or Tab (it finds open menus through that attribute).
     */
    public function test_bridge_hands_the_element_to_bootstrap_4_once(): void {
        $src = $this->theme_file('amd/src/dropdown_bridge.js');
        $this->assertStringContainsString("toggle.setAttribute('data-toggle', 'dropdown');", $src);
        $this->assertStringContainsString("\$(toggle).dropdown('toggle');", $src);
    }
}
