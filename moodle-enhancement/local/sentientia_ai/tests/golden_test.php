<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai;

/**
 * Golden-set eval harness — byte-stability of the deterministic mock
 * layer. Fixtures in tests/fixtures/golden/*.json pin (request → exact
 * expected mock body). If a refactor changes mock output, this fails and
 * the author must consciously regenerate the fixtures — the same
 * discipline a live-model eval suite will apply to prompt changes once
 * the Addendum-A pilot starts (fixtures then gain expected-quality
 * rubrics instead of exact bytes).
 *
 * @package local_sentientia_ai
 * @covers \local_sentientia_ai\gateway
 */
final class golden_test extends \advanced_testcase {

    /**
     * Flush the platform flag resolver's PHP-static caches — they survive
     * resetAfterTest's DB rollback and would leak flag state from earlier
     * test classes in the same process.
     */
    protected function setUp(): void {
        parent::setUp();
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            \local_sentientia_platform\feature_flags::invalidate_caches();
        }
    }

    /**
     * Every fixture replays byte-identically through the mock path.
     */
    public function test_golden_fixtures(): void {
        $this->resetAfterTest();

        $dir = __DIR__ . '/fixtures/golden';
        $files = glob($dir . '/*.json');
        $this->assertNotEmpty($files, 'No golden fixtures found in ' . $dir);

        foreach ($files as $file) {
            $fixture = json_decode(file_get_contents($file), true);
            $this->assertIsArray($fixture, "Malformed fixture: {$file}");

            $result = client::complete($fixture['request']);

            $this->assertSame('mock', $result['mode'], basename($file));
            $this->assertSame($fixture['expected_body'], $result['body'],
                'Golden drift in ' . basename($file)
                . ' — if intentional, regenerate the fixture.');
        }
    }
}
