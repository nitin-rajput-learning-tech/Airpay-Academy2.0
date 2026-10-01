<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_users\bizlms\clean;
use local_sentientia_users\bizlms\transcript_parser;

/**
 * The pure helpers of the users import (ADR-032, mapping doc section 10): how a BizLMS transcript row's free text
 * is read, and how a source value is made to fit a Sentientia column. No database, no clock.
 *
 * @package    local_sentientia_users
 * @category   test
 * @covers     \local_sentientia_users\bizlms\transcript_parser
 * @covers     \local_sentientia_users\bizlms\clean
 *
 * @group local_sentientia_users
 * @group bizlms_import
 */
final class transcript_parser_test extends \basic_testcase {

    /** The status map the owner signed. */
    private const MAP = [
        'normalise' => 'lower(trim(status_raw))',
        'completed' => ['completed', 'complete', 'passed', 'pass', 'attended', 'yes'],
        'inprogress' => ['in progress', 'inprogress', 'started', 'registered', 'enrolled'],
        'failed' => ['failed', 'fail', 'not passed'],
        'notstarted' => ['not started', 'pending', 'assigned'],
        'cancelled' => ['cancelled', 'withdrawn', 'no show', 'absent'],
        'otherwise' => 'unknown',
    ];

    private function tz(string $name = 'Asia/Kolkata'): \DateTimeZone {
        return new \DateTimeZone($name);
    }

    private function midnight(string $ymd, string $tz = 'Asia/Kolkata'): int {
        return (new \DateTimeImmutable($ymd . ' 00:00:00', new \DateTimeZone($tz)))->getTimestamp();
    }

    /**
     * @return array<string, array{0: string, 1: ?string}> text => [text, expected Y-m-d or null]
     */
    public static function dates(): array {
        return [
            'd/m/Y' => ['15/06/2016', '2016-06-15'],
            'd-m-Y' => ['15-06-2016', '2016-06-15'],
            'Y-m-d' => ['2016-06-15', '2016-06-15'],
            'd-M-Y' => ['15-Jun-2016', '2016-06-15'],
            'padded' => ['  15/06/2016 ', '2016-06-15'],
            'day first, never month first' => ['03/04/2016', '2016-04-03'],
            'Excel serial' => ['42536', '2016-06-15'],
            'Excel serial with a fraction' => ['42536.75', '2016-06-15'],
            'a date that does not exist' => ['31/02/2016', null],
            'a month first date with day 13 or more' => ['06/15/2016', null],
            'empty' => ['', null],
            'words' => ['June 2016', null],
            'a bare year' => ['2016', null],
            'a number too small for a day number' => ['12345', null],
            'a number too large for a day number' => ['99999', null],
        ];
    }

    /**
     * @dataProvider dates
     */
    public function test_a_completion_date_is_read_in_the_server_timezone(string $text, ?string $expected): void {
        $this->assertSame($expected === null ? null : $this->midnight($expected),
            transcript_parser::date($text, $this->tz()));
    }

    public function test_the_same_date_is_a_different_instant_in_another_timezone(): void {
        $this->assertNotSame(transcript_parser::date('15/06/2016', $this->tz('Asia/Kolkata')),
            transcript_parser::date('15/06/2016', $this->tz('UTC')));
        $this->assertSame($this->midnight('2016-06-15', 'UTC'), transcript_parser::date('15/06/2016', $this->tz('UTC')));
    }

    public function test_null_is_not_a_date(): void {
        $this->assertNull(transcript_parser::date(null, $this->tz()));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?float}>
     */
    public static function scores(): array {
        return [
            'a number' => ['85', 85.0],
            'a percentage' => ['85%', 85.0],
            'padded with a decimal' => [' 7.5 ', 7.5],
            'rounded to two places' => ['66.666', 66.67],
            'not applicable' => ['n/a', null],
            'empty' => ['', null],
            'null' => [null, null],
            'more than a NUMBER(10,2) holds' => ['1e12', null],
        ];
    }

    /**
     * @dataProvider scores
     */
    public function test_a_score(?string $text, ?float $expected): void {
        $actual = transcript_parser::score($text);
        if ($expected === null) {
            $this->assertNull($actual);
        } else {
            $this->assertEqualsWithDelta($expected, $actual, 0.0001);
        }
    }

    /**
     * @return array<string, array{0: ?string, 1: ?float}>
     */
    public static function hours(): array {
        return [
            'hours and minutes' => ['1:30', 1.5],
            'minutes only' => ['0:45', 0.75],
            'a decimal' => ['2.5', 2.5],
            'whole hours' => ['2', 2.0],
            'sixty or more minutes is not a time' => ['1:75', null],
            'negative' => ['-3', null],
            'words' => ['abc', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    /**
     * @dataProvider hours
     */
    public function test_training_hours(?string $text, ?float $expected): void {
        $actual = transcript_parser::hours($text);
        if ($expected === null) {
            $this->assertNull($actual);
        } else {
            $this->assertEqualsWithDelta($expected, $actual, 0.0001);
        }
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function statuses(): array {
        return [
            'completed' => ['completed', 'completed'],
            'case and padding are ignored' => ['  COMPLETED ', 'completed'],
            'passed' => ['Passed', 'completed'],
            'attended' => ['attended', 'completed'],
            'in progress' => ['In Progress', 'inprogress'],
            'registered' => ['registered', 'inprogress'],
            'failed' => ['not passed', 'failed'],
            'not started' => ['Pending', 'notstarted'],
            'no show' => ['No Show', 'cancelled'],
            'a word nobody listed' => ['Weird', 'unknown'],
            'empty' => ['', 'unknown'],
            'null' => [null, 'unknown'],
        ];
    }

    /**
     * @dataProvider statuses
     */
    public function test_a_status_is_normalised_by_the_signed_list(?string $raw, string $expected): void {
        $this->assertSame($expected, transcript_parser::status($raw, self::MAP));
    }

    public function test_the_otherwise_status_comes_from_the_map(): void {
        $this->assertSame('other', transcript_parser::status('x', ['completed' => ['yes'], 'otherwise' => 'other']));
        $this->assertSame('unknown', transcript_parser::status('x', ['completed' => ['yes']]));
    }

    public function test_every_status_a_map_can_give_is_listed_for_verify(): void {
        $this->assertEqualsCanonicalizing(['completed', 'inprogress', 'failed', 'notstarted', 'cancelled', 'unknown'],
            transcript_parser::statuses(self::MAP));
    }

    // The fitting helpers.

    public function test_a_counter_is_non_negative_and_fits_an_int(): void {
        $this->assertSame([0, false], clean::count(null));
        $this->assertSame([0, false], clean::count(''));
        $this->assertSame([7, false], clean::count('7'));
        $this->assertSame([0, true], clean::count(-4));
        $this->assertSame([2147483647, true], clean::count(5000000000));
        $this->assertSame([2147483647, false], clean::count(2147483647));
    }

    public function test_a_time_or_id_that_is_not_usable_is_zero(): void {
        $this->assertSame(1700000000, clean::time(1700000000));
        $this->assertSame(0, clean::time(null));
        $this->assertSame(0, clean::time(-5));
        $this->assertSame(0, clean::time(5000000000));
        $this->assertSame(12, clean::id('12'));
        $this->assertSame(0, clean::id(null));
        $this->assertSame(0, clean::id(-1));
    }

    public function test_text_that_is_not_utf8_is_repaired_and_reported(): void {
        $this->assertSame(['plain', false], clean::utf8('plain'));
        $this->assertSame(['', false], clean::utf8(null));
        [$text, $repaired] = clean::utf8("bad \xC3( byte");
        $this->assertTrue($repaired);
        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
        $this->assertStringStartsWith('bad ', $text);
    }
}
