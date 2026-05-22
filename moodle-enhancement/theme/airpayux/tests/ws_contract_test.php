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

namespace theme_airpayux;

defined('MOODLE_INTERNAL') || die();

/**
 * CI gate against WS-contract drift (Bug #6, #10, #11 class).
 *
 * theme_airpayux/datatable is a shared AMD client used by 14+ WS
 * endpoints across local_airpay_*. It always POSTs the contract
 * {search, sort, sortdir, page, perpage, filters}. Moodle's strict
 * external_function_parameters validator rejects unknown keys, so
 * every consumer WS must declare all 6 with VALUE_DEFAULT.
 *
 * Bug #6 plus Bug #10 fixed 7 endpoints that had drifted. This test
 * prevents regression. Heavy lifting lives in
 * theme_airpayux\ws_contract_scanner so the same logic runs from CLI
 * smoke tests and from PHPUnit CI.
 *
 * @package    theme_airpayux
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @group theme_airpayux
 * @group ws_contract
 */
final class ws_contract_test extends \advanced_testcase {

    /**
     * Every WS referenced by a data-region="airpay-datatable" element
     * must declare the full contract with VALUE_DEFAULT, so the shared
     * AMD client can POST {search, sort, sortdir, page, perpage,
     * filters} without rejection by the strict params validator.
     */
    public function test_every_datatable_ws_accepts_client_contract(): void {
        $this->resetAfterTest(false);

        $result = ws_contract_scanner::audit();

        $this->assertNotEmpty(
            $result['consumers'],
            'Expected at least one airpay-datatable consumer mustache. '
            . 'Either the scanner regex is broken or no plugins use the '
            . 'shared datatable any more.'
        );

        if (!empty($result['failures'])) {
            $msgs = [];
            foreach ($result['failures'] as $wsname => $missing) {
                $sources = $result['consumers'][$wsname] ?? [];
                $msgs[] = sprintf(
                    "WS `%s` missing contract keys [%s]\n  Used by:\n    %s",
                    $wsname,
                    implode(', ', $missing),
                    implode("\n    ", $sources));
            }
            $this->fail(
                "The shared theme_airpayux/datatable client POSTs "
                . "{search, sort, sortdir, page, perpage, filters} to every "
                . "WS it consumes. Each WS must declare all 6 with "
                . "VALUE_DEFAULT. Failures:\n\n"
                . implode("\n\n", $msgs)
                . "\n\nCanonical shape: local/airpay_request/classes/external/list_mine.php"
            );
        }

        $this->assertTrue($result['ok']);
    }
}
