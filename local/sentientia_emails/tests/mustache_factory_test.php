<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * The Mustache engine factory (Moodle 5.3 compat FX-04).
 *
 * Moodle 5.2 and 5.3 autoload Mustache 3.0 as \Mustache\Engine only; the legacy \Mustache_Engine is
 * a class-not-found there. The factory must hand back an engine that renders on whichever Moodle is
 * running, and prefer the current class whenever it exists.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @covers     \local_sentientia_emails\mustache_factory
 */
final class mustache_factory_test extends \basic_testcase {

    public function test_engine_renders_a_string_template(): void {
        $engine = mustache_factory::engine();
        $this->assertSame('Hello Priya', $engine->render('Hello {{name}}', ['name' => 'Priya']));
    }

    public function test_engine_escapes_variables_like_the_override_renderer_expects(): void {
        $engine = mustache_factory::engine();
        $out = $engine->render('<p>{{name}}</p>', ['name' => '<b>x</b>']);
        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringContainsString('&lt;b&gt;', $out);
    }

    public function test_engine_prefers_the_current_mustache_class(): void {
        $expected = class_exists(\Mustache\Engine::class) ? \Mustache\Engine::class : \Mustache_Engine::class;
        $this->assertInstanceOf($expected, mustache_factory::engine());
    }

    public function test_a_broken_template_throws_something_catchable_as_throwable(): void {
        // The three call sites catch \Throwable so the file-template fallback always runs.
        $this->expectException(\Throwable::class);
        mustache_factory::engine()->render('text {{/nothing}}', []);
    }
}
