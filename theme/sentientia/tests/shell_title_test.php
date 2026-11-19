<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia;

use theme_sentientia\output\core_renderer;

defined('MOODLE_INTERNAL') || die();

/**
 * The visible page title the shell prints on pages that print none of their own.
 *
 * Surface walk 2026-11-18 (checklist V3): My Courses, the catalog, My Skills, My Team and the compliance report
 * started straight with a search bar or a filter row, because the shell's h1 was screen-reader only. Those page types
 * now get the sidebar label as a visible title; any other page can opt in with the ap-page-title body class. Pages
 * that print their own title stay out of the map, so they never show two.
 *
 * @package    theme_sentientia
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_sentientia\output\core_renderer::shell_visible_title
 */
final class shell_title_test extends \advanced_testcase {

    /**
     * A fresh page with the given page type, in the system context.
     *
     * @param string $pagetype
     * @return \moodle_page
     */
    private function page(string $pagetype): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_pagetype($pagetype);
        return $page;
    }

    /**
     * The mapped page types and the sidebar string each one shows.
     *
     * @return array
     */
    public static function mapped_page_provider(): array {
        return [
            'My Courses' => ['local-sentientia_catalog-mycourses', 'nav_courses'],
            'Catalog' => ['local-sentientia_catalog-index', 'nav_catalog'],
            'My Skills' => ['local-sentientia_skills-index', 'nav_myskills'],
            'My Team' => ['local-sentientia_manager-index', 'leftmenu_tm_dashboard'],
            'Compliance report' => ['local-sentientia_compliance_report-index', 'nav_compliance'],
        ];
    }

    /**
     * Each mapped page type shows its sidebar label as the title.
     *
     * @dataProvider mapped_page_provider
     * @param string $pagetype
     * @param string $identifier theme_sentientia string identifier
     */
    public function test_mapped_page_shows_its_sidebar_label(string $pagetype, string $identifier): void {
        $this->resetAfterTest();
        $expected = get_string($identifier, 'theme_sentientia');
        $this->assertNotSame('', $expected);
        $this->assertSame($expected, core_renderer::shell_visible_title($this->page($pagetype)));
    }

    /**
     * Pages that print their own title, and are therefore not mapped.
     *
     * @return array
     */
    public static function excluded_page_provider(): array {
        return [
            'analytics' => ['local-sentientia_analytics-index'],
            'certificates' => ['local-sentientia_pages-certificates'],
            'profile' => ['local-sentientia_users-profile'],
            'public storefront' => ['local-sentientia_catalog-public'],
            'catalog course page' => ['local-sentientia_catalog-course'],
            'dashboard' => ['my-index'],
        ];
    }

    /**
     * A page that prints its own title gets no second, visible one from the shell.
     *
     * @dataProvider excluded_page_provider
     * @param string $pagetype
     */
    public function test_page_with_its_own_title_gets_none(string $pagetype): void {
        $this->resetAfterTest();
        $this->assertSame('', core_renderer::shell_visible_title($this->page($pagetype)));
    }

    /**
     * The body-class opt-in shows the page heading as text, the ampersand unescaped once (s() escapes it on output).
     */
    public function test_body_class_opt_in_shows_the_heading(): void {
        $this->resetAfterTest();
        $page = $this->page('local-sentientia_example-index');
        $page->add_body_class(core_renderer::SHELL_TITLE_BODYCLASS);
        $page->set_heading('Risk &amp; Policy');
        $this->assertSame('Risk & Policy', core_renderer::shell_visible_title($page));
    }

    /**
     * The body-class opt-in without a heading gives no visible title, so the shell keeps the screen-reader h1.
     */
    public function test_body_class_opt_in_without_a_heading_gives_none(): void {
        $this->resetAfterTest();
        $page = $this->page('local-sentientia_example-index');
        $page->add_body_class(core_renderer::SHELL_TITLE_BODYCLASS);
        $this->assertSame('', core_renderer::shell_visible_title($page));
    }

    /**
     * A page that is neither mapped nor opted in gets no visible title, even with a heading.
     */
    public function test_unmapped_page_without_the_body_class_gets_none(): void {
        $this->resetAfterTest();
        $page = $this->page('local-sentientia_example-index');
        $page->set_heading('Some heading');
        $this->assertSame('', core_renderer::shell_visible_title($page));
    }

    /**
     * The shell prints the visible title with its own class, and keeps the screen-reader h1 for every other page.
     */
    public function test_the_shell_prints_both_heading_forms(): void {
        $source = (string) file_get_contents(__DIR__ . '/../classes/output/core_renderer.php');
        $this->assertStringContainsString('<h1 class="ap-shell__title">', $source);
        $this->assertStringContainsString('<h1 class="sr-only">', $source);
        $this->assertStringContainsString('self::shell_visible_title($this->page)', $source);
    }
}
