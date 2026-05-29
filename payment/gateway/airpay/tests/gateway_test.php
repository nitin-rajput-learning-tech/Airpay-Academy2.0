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
 * Initial PHPUnit coverage for paygw_airpay::gateway.
 *
 * Scope (NIGHT-RUN-PLAYBOOK B1):
 * - get_supported_currencies()
 * - validate_gateway_form() — required-field gating
 *
 * Out of scope (documented in @see references):
 * - airpay_helper.php — depends on require'd checksum.php that calls
 *   require_login() at file scope; cannot be loaded in PHPUnit without
 *   first fixing that.
 * - checksum.php — same require_login()-at-file-scope issue.
 *
 * @package    paygw_airpay
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_airpay;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the paygw_airpay\gateway class.
 *
 * @covers \paygw_airpay\gateway
 */
final class gateway_test extends \advanced_testcase {

    /**
     * INR must be in the supported-currency list — that's the production
     * currency for Airpay Academy customer-zero. If this assertion ever
     * fails, the deploy is broken for our primary market.
     */
    public function test_inr_is_supported(): void {
        $currencies = gateway::get_supported_currencies();
        $this->assertIsArray($currencies);
        $this->assertContains('INR', $currencies);
    }

    /**
     * USD must also be supported — used by the Public tenant (id=77)
     * for international self-registered learners.
     */
    public function test_usd_is_supported(): void {
        $currencies = gateway::get_supported_currencies();
        $this->assertContains('USD', $currencies);
    }

    /**
     * Lock the currency count so future changes are deliberate. The
     * original Airpay India plugin shipped with this exact list.
     * If a new currency is intentionally added, bump the number here
     * AND add the symmetrical assertion below.
     */
    public function test_currency_list_size_is_stable(): void {
        $currencies = gateway::get_supported_currencies();
        $this->assertCount(26, $currencies);
    }

    /**
     * No duplicates in the supported-currency list. (A copy-paste
     * regression in a future edit would silently duplicate a code
     * and pass everything until a third-party validator chokes.)
     */
    public function test_no_duplicate_currencies(): void {
        $currencies = gateway::get_supported_currencies();
        $this->assertCount(
            count(array_unique($currencies)),
            $currencies,
            'Duplicate currency code detected in gateway::get_supported_currencies()'
        );
    }

    /**
     * Every code is a 3-letter uppercase ISO 4217 code — that's what
     * \core_payment\helper expects when computing fees and rounding.
     */
    public function test_all_currencies_are_iso4217_format(): void {
        foreach (gateway::get_supported_currencies() as $code) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z]{3}$/',
                $code,
                "Currency '$code' is not a 3-letter uppercase ISO 4217 code"
            );
        }
    }

    /**
     * validate_gateway_form() must set $errors['enabled'] when the
     * gateway is enabled but required credentials are missing. The
     * required fields per gateway.php::validate_gateway_form() are:
     *   brandname, username, password, secret, mercid
     *
     * NOTE: this test exercises the public static method directly with
     * a stub form + stdClass data; it does NOT instantiate the real
     * \core_payment\form\account_gateway form which requires a full
     * page context.
     */
    public function test_validate_gateway_form_blocks_enable_when_credentials_missing(): void {
        $errors = [];
        $data = (object) [
            'enabled'   => 1,
            'brandname' => '',
            'username'  => '',
            'password'  => '',
            'secret'    => '',
            'mercid'    => '',
        ];

        // Build a minimal account_gateway stub. validate_gateway_form()
        // does not call methods on the form parameter inside the
        // missing-field branch, so a partial-mock is sufficient.
        $form = $this->getMockBuilder(\core_payment\form\account_gateway::class)
            ->disableOriginalConstructor()
            ->getMock();

        gateway::validate_gateway_form($form, $data, [], $errors);

        $this->assertArrayHasKey('enabled', $errors);
        $this->assertNotEmpty($errors['enabled']);
    }

    /**
     * When all required credentials are present, validate_gateway_form()
     * must NOT populate $errors['enabled']. (Other validation steps may
     * fire elsewhere — but the missing-required-credentials branch
     * specifically should be silent on a complete form.)
     */
    public function test_validate_gateway_form_passes_when_credentials_complete(): void {
        $errors = [];
        $data = (object) [
            'enabled'   => 1,
            'brandname' => 'AirpayAcademy',
            'username'  => 'merchant_user',
            'password'  => 'merchant_pass',
            'secret'    => 's3cr3t',
            'mercid'    => 'MERC123',
        ];

        $form = $this->getMockBuilder(\core_payment\form\account_gateway::class)
            ->disableOriginalConstructor()
            ->getMock();

        gateway::validate_gateway_form($form, $data, [], $errors);

        $this->assertArrayNotHasKey('enabled', $errors);
    }

    /**
     * When the gateway is DISABLED (enabled=0), no required-field
     * validation should fire — admins can save partial credentials
     * while configuring before flipping enabled to 1.
     */
    public function test_validate_gateway_form_does_not_block_when_disabled(): void {
        $errors = [];
        $data = (object) [
            'enabled'   => 0,
            'brandname' => '',
            'username'  => '',
            'password'  => '',
            'secret'    => '',
            'mercid'    => '',
        ];

        $form = $this->getMockBuilder(\core_payment\form\account_gateway::class)
            ->disableOriginalConstructor()
            ->getMock();

        gateway::validate_gateway_form($form, $data, [], $errors);

        $this->assertArrayNotHasKey('enabled', $errors);
    }
}
