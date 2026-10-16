<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * The amount in words on a tax invoice (spec 2026-10-14, sections 8 and 11): the Indian system, "Rupees ... and ...
 * Paise only". Pure function, no database.
 *
 * @package    local_sentientia_cart
 * @category   test
 * @covers     \local_sentientia_cart\amount_in_words
 *
 * @group local_sentientia_cart
 * @group local_sentientia_cart_gst
 */
final class amount_in_words_test extends \basic_testcase {

    /**
     * @return array<string, array{0: float|int|string, 1: string}>
     */
    public static function amounts(): array {
        return [
            'zero'                    => [0, 'Rupees Zero only'],
            'one rupee'               => [1, 'Rupees One only'],
            'teens'                   => [13, 'Rupees Thirteen only'],
            'round tens'              => [40, 'Rupees Forty only'],
            'tens and units'          => [99, 'Rupees Ninety Nine only'],
            'a hundred'               => [100, 'Rupees One Hundred only'],
            'hundreds'                => [101, 'Rupees One Hundred One only'],
            'no "and" inside a number' => [999, 'Rupees Nine Hundred Ninety Nine only'],
            'a thousand'              => [1000, 'Rupees One Thousand only'],
            'the usual invoice'       => [1180, 'Rupees One Thousand One Hundred Eighty only'],
            'thousands'               => [12345, 'Rupees Twelve Thousand Three Hundred Forty Five only'],
            'ninety-nine thousand'    => [99999, 'Rupees Ninety Nine Thousand Nine Hundred Ninety Nine only'],
            'one lakh'                => [100000, 'Rupees One Lakh only'],
            'lakh grouping'           => [123456, 'Rupees One Lakh Twenty Three Thousand Four Hundred Fifty Six only'],
            'lakhs, no thousand'      => [500000, 'Rupees Five Lakh only'],
            'ninety-nine lakh'        => [9999999, 'Rupees Ninety Nine Lakh Ninety Nine Thousand Nine Hundred Ninety Nine only'],
            'one crore'               => [10000000, 'Rupees One Crore only'],
            'crore, lakh, thousand'   => [12345678, 'Rupees One Crore Twenty Three Lakh Forty Five Thousand Six Hundred Seventy Eight only'],
            'a lakh inside a crore'   => [10100000, 'Rupees One Crore One Lakh only'],
            'a hundred crore'         => [1000000000, 'Rupees One Hundred Crore only'],
            'a thousand crore'        => [10000000000, 'Rupees One Thousand Crore only'],
            'paise'                   => [1180.50, 'Rupees One Thousand One Hundred Eighty and Fifty Paise only'],
            'one paisa'               => [0.01, 'Rupees Zero and One Paise only'],
            'lakh and paise'          => [123456.5, 'Rupees One Lakh Twenty Three Thousand Four Hundred Fifty Six and Fifty Paise only'],
            'paise teens'             => [5.19, 'Rupees Five and Nineteen Paise only'],
            'a string amount'         => ['2360.00', 'Rupees Two Thousand Three Hundred Sixty only'],
        ];
    }

    /**
     * @dataProvider amounts
     * @param float|int|string $amount
     * @param string $expected
     * @return void
     */
    public function test_the_amount_is_said_the_indian_way($amount, string $expected): void {
        $this->assertSame($expected, amount_in_words::from_amount($amount));
    }

    public function test_float_noise_cannot_make_a_wrong_paisa(): void {
        // 0.1 + 0.2 is 0.30000000000000004: the amount is rounded to the paisa before it is said.
        $this->assertSame('Rupees Zero and Thirty Paise only', amount_in_words::from_amount(0.1 + 0.2));
        $this->assertSame('Rupees Nineteen and Ninety Nine Paise only', amount_in_words::from_amount(19.99));
        $this->assertSame('Rupees Eleven and Fifteen Paise only', amount_in_words::from_amount(11.15));
    }

    public function test_it_works_in_paise_too(): void {
        $this->assertSame('Rupees One Thousand One Hundred Eighty only', amount_in_words::from_paise(118000));
        $this->assertSame('Rupees Zero and Five Paise only', amount_in_words::from_paise(5));
    }

    public function test_a_negative_amount_is_minus(): void {
        $this->assertSame('Minus Rupees Five and Fifty Paise only', amount_in_words::from_amount(-5.5));
    }

    public function test_a_negative_number_of_whole_rupees_is_a_coding_error(): void {
        $this->expectException(\coding_exception::class);
        amount_in_words::integer_words(-1);
    }
}
