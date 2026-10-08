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
        // class_exists(\Mustache_Engine::class) is safe on every Moodle (on 5.1 it loads Mustache 2.x,
        // on 5.2 and 5.3 it resolves to no file). The mirror test, class_exists(\Mustache\Engine::class),
        // is NOT safe on 5.1 and is never called here: see the factory's docblock.
        $engine = mustache_factory::engine();
        if (class_exists(\Mustache_Engine::class)) {
            // 5.1 (Mustache 2.x), or 5.2+ with the compat aliases loaded (a subclass of the current class).
            $this->assertInstanceOf(\Mustache_Engine::class, $engine);
        } else {
            $this->assertInstanceOf(\Mustache\Engine::class, $engine);
        }
    }

    public function test_engine_can_be_built_repeatedly_in_one_request(): void {
        // On 5.1 a probe through the PSR-0 loader re-declared \Mustache_Engine on the second call, an
        // uncatchable fatal that ended the e-mail cron run. Several engines in a row must be fine.
        $first = mustache_factory::engine();
        $second = mustache_factory::engine();
        $third = mustache_factory::engine([
            'escape' => static fn($value) => strtoupper((string) $value),
        ]);
        $this->assertSame(get_class($first), get_class($second));
        $this->assertSame(get_class($first), get_class($third));
        // The options reach the constructor unchanged.
        $this->assertSame('OK', $third->render('{{v}}', ['v' => 'ok']));
        $this->assertSame('ok', $second->render('{{v}}', ['v' => 'ok']));
    }

    public function test_a_broken_template_throws_something_catchable_as_throwable(): void {
        // The three call sites catch \Throwable so the file-template fallback always runs.
        $this->expectException(\Throwable::class);
        mustache_factory::engine()->render('text {{/nothing}}', []);
    }
}
