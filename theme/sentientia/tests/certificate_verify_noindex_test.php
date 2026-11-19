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

use theme_sentientia\output\core_renderer;

/**
 * Tests for the robots meta tag on the certificate verification page (2026-11-19, theme 2026111901).
 *
 * Since decision D32 a verification link (certificate QR code, gallery "Verify", LinkedIn) shows the result,
 * the holder's full name included, without a second press of Verify. Nitin approved (2026-11-19) asking search
 * engines not to index that page or follow its links, from the theme only: core_renderer::standard_head_html()
 * appends <meta name="robots" content="noindex, nofollow" /> for the page type admin-tool-certificate-index and
 * nothing for any other page.
 *
 * PHPUnit reads no doc-comment metadata from a class that has attributes, so the group is an attribute too.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('theme_sentientia')]
#[\PHPUnit\Framework\Attributes\CoversClass(core_renderer::class)]
final class certificate_verify_noindex_test extends \advanced_testcase {

    /** @var string The page type of admin/tool/certificate/index.php. */
    private const VERIFY_PAGETYPE = 'admin-tool-certificate-index';

    /**
     * The verification page gets "noindex, nofollow", exactly once.
     */
    public function test_the_verification_page_gets_noindex_nofollow(): void {
        $meta = core_renderer::robots_noindex_meta(self::VERIFY_PAGETYPE);
        $this->assertSame('<meta name="robots" content="noindex, nofollow" />' . "\n", $meta);
        $this->assertSame(1, substr_count($meta, '<meta'));
    }

    /**
     * Every other page, the other certificate pages and near-miss page types included, gets no robots tag.
     */
    public function test_other_pages_get_no_robots_tag(): void {
        $others = [
            'site-index',
            'my-index',
            'login-index',
            'login-signup',
            'admin-tool-certificate-my',
            'admin-tool-certificate-view',
            'admin-tool-certificate-manage_templates',
            'local-sentientia_pages-certificates',
            'admin-tool-certificate-index-x',
            'ADMIN-TOOL-CERTIFICATE-INDEX',
            '',
        ];
        foreach ($others as $pagetype) {
            $this->assertSame('', core_renderer::robots_noindex_meta($pagetype), "page type '{$pagetype}'");
        }
    }

    /**
     * Moodle derives the page type admin-tool-certificate-index from the verification script, which sets none itself.
     */
    public function test_the_verification_script_has_that_page_type(): void {
        global $SCRIPT;
        $this->resetAfterTest();

        $saved = $SCRIPT;
        try {
            $SCRIPT = '/admin/tool/certificate/index.php';
            $page = new \moodle_page();
            $this->assertSame(self::VERIFY_PAGETYPE, $page->pagetype);
        } finally {
            $SCRIPT = $saved;
        }

        // The vendor page script does not override the derived page type.
        $script = dirname(__DIR__, 3) . '/admin/tool/certificate/index.php';
        if (is_file($script)) {
            $this->assertStringNotContainsString('set_pagetype', (string) file_get_contents($script));
        }
    }

    /**
     * standard_head_html(), which every theme layout prints in <head>, appends the tag for the current page type.
     */
    public function test_standard_head_html_appends_the_tag(): void {
        $source = (string) file_get_contents(dirname(__DIR__) . '/classes/output/core_renderer.php');
        $start = strpos($source, 'public function standard_head_html()');
        $this->assertNotFalse($start);
        $end = strpos($source, 'public function ', $start + 1);
        $this->assertNotFalse($end);
        $method = substr($source, $start, $end - $start);

        $call = '$output .= self::robots_noindex_meta($this->page->pagetype);';
        $this->assertStringContainsString($call, $method);
        // Appended after the tenant and dark-mode blocks, immediately before the method returns.
        $this->assertMatchesRegularExpression('/' . preg_quote($call, '/') . '\s+return \$output;/', $method);

        // The theme's <head> partial prints standard_head_html() for every layout that includes it.
        $head = (string) file_get_contents(dirname(__DIR__) . '/templates/head.mustache');
        $this->assertStringContainsString('{{{ output.standard_head_html }}}', $head);
    }
}
