<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_cart;

defined('MOODLE_INTERNAL') || die();

/**
 * An amount of money in words, the Indian way (GST tax invoice, spec section 8).
 *
 * "Rupees One Lakh Twenty Three Thousand Four Hundred Fifty Six and Fifty Paise only". Grouping is the Indian one:
 * thousand, lakh (100 000) and crore (10 000 000); a number of crore or more says "Crore" for the rest, so
 * 1 000 crore is "One Thousand Crore". No "and" is put inside a number (the only "and" is the one that joins the
 * rupees to the paise), and nothing is hyphenated.
 *
 * Pure: no database, no language. The words are English on purpose: the tax document is the legal text and its
 * words are the same in every interface language (the labels around them are lang strings).
 *
 * @package    local_sentientia_cart
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class amount_in_words {

    /** @var string[] The words of 0 to 19. */
    private const SMALL = [
        'Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    /** @var string[] The words of the tens, 20 to 90, indexed by tens digit. */
    private const TENS = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy',
        8 => 'Eighty', 9 => 'Ninety'];

    /**
     * An amount in rupees (with paise as decimals) in words.
     *
     * The amount is rounded to the paisa first, so 0.1 + 0.2 cannot say "Thirty Paise" and "Zero Paise".
     *
     * @param float|int|string $amount Rupees, for example 1180.5.
     * @return string "Rupees One Thousand One Hundred Eighty and Fifty Paise only"
     */
    public static function from_amount($amount): string {
        return self::from_paise((int) round(((float) $amount) * 100));
    }

    /**
     * An amount in paise in words.
     *
     * @param int $paise The amount in paise; a negative amount is "Minus ...".
     * @return string
     */
    public static function from_paise(int $paise): string {
        $negative = $paise < 0;
        $paise = abs($paise);
        $rupees = intdiv($paise, 100);
        $rest = $paise % 100;

        $text = 'Rupees ' . self::integer_words($rupees);
        if ($rest > 0) {
            $text .= ' and ' . self::integer_words($rest) . ' Paise';
        }
        $text .= ' only';
        return $negative ? 'Minus ' . $text : $text;
    }

    /**
     * A whole number in words, Indian grouping.
     *
     * @param int $n Zero or more.
     * @return string "Zero", "Twelve", "One Lakh Twenty Three Thousand Four Hundred Fifty Six", ...
     */
    public static function integer_words(int $n): string {
        if ($n < 0) {
            throw new \coding_exception('amount_in_words::integer_words() takes a number of zero or more');
        }
        if ($n === 0) {
            return self::SMALL[0];
        }
        $parts = [];
        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        $lakh = intdiv($n, 100000);
        $n %= 100000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
        if ($crore > 0) {
            // More than 99 crore: the crore count is itself said in Indian grouping ("One Thousand Crore").
            $parts[] = self::integer_words($crore) . ' Crore';
        }
        if ($lakh > 0) {
            $parts[] = self::below_hundred($lakh) . ' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = self::below_hundred($thousand) . ' Thousand';
        }
        if ($n > 0) {
            $parts[] = self::below_thousand($n);
        }
        return implode(' ', $parts);
    }

    /**
     * 1 to 999.
     *
     * @param int $n
     * @return string
     */
    private static function below_thousand(int $n): string {
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $words = [];
        if ($hundreds > 0) {
            $words[] = self::SMALL[$hundreds] . ' Hundred';
        }
        if ($rest > 0) {
            $words[] = self::below_hundred($rest);
        }
        return implode(' ', $words);
    }

    /**
     * 1 to 99.
     *
     * @param int $n
     * @return string
     */
    private static function below_hundred(int $n): string {
        if ($n < 20) {
            return self::SMALL[$n];
        }
        $unit = $n % 10;
        return self::TENS[intdiv($n, 10)] . ($unit > 0 ? ' ' . self::SMALL[$unit] : '');
    }
}
