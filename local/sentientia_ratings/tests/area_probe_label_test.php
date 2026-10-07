<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ratings;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_ratings\bizlms\area_map;
use local_sentientia_ratings\bizlms\importer;

/**
 * Doc item "ratings security" (owner decisions, 2026-10-07) and CRS-10: the rows whose area is a vulnerability scanner's
 * probe string are labelled as such in the parity report.
 *
 * The April 2026 copy holds 194 local_like rows whose likearea is an injection or path-traversal payload (19 of them longer
 * than 100 characters, timestamps from 2024-01 to 2025-12, none with a real user). They are skipped as unknown_area, as
 * before; what changes is the detail code in the map and in the report lines, which now says scanner_payload instead of
 * area_not_mapped or area_too_long. The value itself stays in the legacy table. An area that is a plain name (an unmapped
 * plugin, the certification area, a long run of letters) keeps its old detail.
 *
 * @package    local_sentientia_ratings
 * @category   test
 * @covers     \local_sentientia_ratings\bizlms\area_map
 * @group      local_sentientia_ratings
 * @group      bizlms_import
 */
final class area_probe_label_test extends \advanced_testcase {

    /**
     * A dry-run context for the ratings importer. Resolving an area that is not mapped never reads the lookups.
     *
     * @return context
     */
    private function ctx(): context {
        $this->resetAfterTest();
        return context::build(new importer(), true, 0, decisions::from_array([]));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function probes(): array {
        return [
            'sql injection' => ["x' OR 1=1--"],
            'quote and comment' => ['local_courses\' AND SLEEP(5)#'],
            'path traversal' => ['../../../../etc/passwd'],
            'windows traversal' => ['..\\..\\windows\\win.ini'],
            'script tag' => ['<script>alert(1)</script>'],
            'percent escape' => ['%27%20OR%201%3D1'],
            'white space' => ['local courses'],
            'long traversal over the column width' => [str_repeat('../', 60)],
            'null byte' => ["local_courses\0"],
            'non ascii' => ["local_course\u{00e9}"],
        ];
    }

    /**
     * @dataProvider probes
     * @param string $area
     */
    public function test_a_probe_string_is_labelled_scanner_payload(string $area): void {
        $ctx = $this->ctx();
        $this->assertTrue(area_map::is_probe($area));
        $this->assertSame([null, null, 'unknown_area', 'scanner_payload'], area_map::resolve($ctx, 5, $area));
    }

    public function test_a_plain_name_keeps_the_detail_it_always_had(): void {
        $ctx = $this->ctx();
        $this->assertSame([null, null, 'unknown_area', 'no_area'], area_map::resolve($ctx, 5, null));
        $this->assertSame([null, null, 'unknown_area', 'no_area'], area_map::resolve($ctx, 5, ''));
        $this->assertSame([null, null, 'unknown_area', 'certification_area'], area_map::resolve($ctx, 5, 'local_certification'));
        $this->assertSame([null, null, 'unknown_area', 'area_not_mapped'], area_map::resolve($ctx, 5, 'local_unknown'));
        $this->assertSame([null, null, 'unknown_area', 'area_not_mapped'], area_map::resolve($ctx, 5, 'mod_some-plugin.v2'));
        $this->assertSame([null, null, 'unknown_area', 'area_too_long'],
            area_map::resolve($ctx, 5, str_repeat('a', 150)), 'a long run of plain letters is too long, not a probe');
        $this->assertFalse(area_map::is_probe('local_courses'));
        $this->assertFalse(area_map::is_probe(''));
    }

    public function test_the_label_is_a_detail_code_the_framework_accepts(): void {
        // outcome::skip() accepts codes only: lower-case words joined by colons.
        $outcome = \local_sentientia_platform\bizlms\outcome::skip(1, 'unknown_area', 'scanner_payload');
        $this->assertSame('scanner_payload', $outcome->detail);
        $this->assertSame('unknown_area', $outcome->reason);
    }

    public function test_a_mapped_area_is_never_a_probe(): void {
        foreach (area_map::legacy_areas() as $area) {
            $this->assertFalse(area_map::is_probe($area), $area);
        }
    }
}
