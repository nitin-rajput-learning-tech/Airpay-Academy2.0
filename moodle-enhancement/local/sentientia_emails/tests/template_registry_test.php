<?php
// This file is part of Sentientia LMS.

/**
 * QW-EMAIL (2026-11-19): e-mail template keys, previews and edited (override) bodies.
 *
 * Nitin's UAT review: /local/sentientia_emails/preview.php?template=compliance%2Fwelcome_enrolled showed
 * "Template: local_sentientia_emails/compliancewelcome_enrolled" and "Template error: Sorry, the requested
 * file could not be found". preview.php cleaned ?template= with PARAM_ALPHANUMEXT, which drops the "/" every
 * key has, so no template in the sidebar could be previewed. editor.php, preview_ajax.php and the template
 * web services took the key raw; get_template read templates/{key}.mustache from disk with it.
 *
 * These tests pin: only a key from the registry is accepted, anywhere; every registry template has its
 * file and renders with its sample data; a preview shows what the tenant's learners receive (its override,
 * else the global one, else the file); and an edited body can include this plugin's partials (course card,
 * support footer), which used to render as nothing, and nothing else.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_emails\email_renderer
 * @covers     \local_sentientia_emails\mustache_factory
 * @covers     \local_sentientia_emails\partials_loader
 * @covers     \local_sentientia_emails\external\template_api
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Template registry, preview and override rendering.
 */
final class template_registry_test extends \advanced_testcase {

    /** @var string A registry key used for the override cases. */
    private const TPL = 'compliance/deadline_warning';

    /** @var string Text only the support-footer partial produces. */
    private const FOOTER_MARK = 'Keka Helpdesk';

    public function test_every_registry_key_is_accepted_as_itself_and_in_the_legacy_tpl_spelling(): void {
        $keys = email_renderer::template_keys();
        $this->assertCount(20, $keys, 'The preview sidebar offers 20 templates.');
        $this->assertSame(count($keys), count(array_unique($keys)));
        foreach ($keys as $key) {
            $this->assertSame($key, email_renderer::known_template_key($key));
            $this->assertSame($key, email_renderer::known_template_key(' ' . $key . ' '));
            // preview.php?tpl=compliance_welcome_enrolled (the "/" written as "_").
            $this->assertSame($key, email_renderer::known_template_key(str_replace('/', '_', $key)));
        }
    }

    public function test_anything_that_is_not_a_registry_key_is_refused(): void {
        $refused = [
            '',
            'compliancewelcome_enrolled',          // What PARAM_ALPHANUMEXT made of the key.
            'compliance/welcome/enrolled',         // What the old ?tpl= fallback made of it.
            'Compliance/Welcome_Enrolled',
            'manage/editor',                       // This plugin's own page templates.
            'manage/tab_templates',
            'partials/footer_note',                // A partial is not an e-mail.
            'core/email_html',
            '../../../config',
            'compliance/../manage/editor',
            '/compliance/welcome_enrolled',
            'compliance/welcome_enrolled.mustache',
            'bizlms/course_enrol_12',              // Legacy: preview.php reads it read-only by id, nothing else may.
        ];
        foreach ($refused as $requested) {
            $this->assertSame('', email_renderer::known_template_key($requested), "'{$requested}' must be refused.");
        }
    }

    public function test_every_registry_template_has_its_file_and_renders_its_sample(): void {
        $this->resetAfterTest();
        $dir = \core_component::get_component_directory('local_sentientia_emails') . '/templates/';
        foreach (email_renderer::template_keys() as $key) {
            $this->assertFileExists($dir . $key . '.mustache', "{$key}: the preview sidebar offers it.");
            $this->assertNotSame('', trim((string) file_get_contents($dir . $key . '.mustache')), "{$key} is empty.");

            $sample = email_context::get_sample($key);
            $this->assertArrayHasKey('subject', $sample, "{$key}: no sample subject.");
            $this->assertNotSame('Email Preview', $sample['subject'], "{$key}: falls back to the base sample.");

            $html = email_renderer::render_preview('local_sentientia_emails/' . $key, $sample, 1);
            // Every sample names Priya Singh (as the recipient or the team member).
            $this->assertStringContainsString('Priya', $html, "{$key} did not render its sample data.");
            $this->assertStringNotContainsString('{{', $html, "{$key} left a placeholder unrendered.");
        }
    }

    public function test_preview_shows_what_the_tenants_learners_receive(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sample = email_context::get_sample(self::TPL);
        $preview = fn(int $branding, ?int $overridetenant = null): string => email_renderer::render_preview(
            'local_sentientia_emails/' . self::TPL, $sample, $branding, $overridetenant);

        // No override: the file.
        $this->assertStringContainsString('Hi <strong>Priya</strong>', $preview(1));

        template_manager::save_override(self::TPL, 0, 'Global', '<p>GLOBAL-BODY {{firstname}}</p>');
        template_manager::save_override(self::TPL, 177, 'ZEEA', '<p>ZEEA-BODY {{firstname}}</p>');

        $this->assertStringContainsString('ZEEA-BODY Priya', $preview(177));
        $this->assertStringNotContainsString('GLOBAL-BODY', $preview(177));
        $this->assertStringContainsString('GLOBAL-BODY Priya', $preview(1), 'Tenant 1 has no override: global.');
        // The editor on the global scope: Airpay branding, the global override.
        $this->assertStringContainsString('GLOBAL-BODY Priya', $preview(1, 0));
        $this->assertStringContainsString('ZEEA-BODY Priya', $preview(1, 177));

        // A broken override is not delivered (render() falls back to the file); the preview says the same.
        template_manager::save_override(self::TPL, 77, 'Broken', '<p>{{#unclosed}}</p>');
        $html = $preview(77);
        $this->assertDebuggingCalled();
        $this->assertStringContainsString('Hi <strong>Priya</strong>', $html);
    }

    public function test_preview_reports_the_body_it_actually_rendered(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sample = email_context::get_sample(self::TPL);
        // preview.php labels the preview from this ("Showing this tenant's edited version" etc.).
        $source = function(int $tenant) use ($sample): ?string {
            $rendered = 'not set';
            email_renderer::render_preview('local_sentientia_emails/' . self::TPL, $sample, $tenant, $tenant, $rendered);
            return $rendered;
        };

        $this->assertSame('file', $source(1));
        template_manager::save_override(self::TPL, 0, 'Global', '<p>GLOBAL-BODY {{firstname}}</p>');
        template_manager::save_override(self::TPL, 177, 'ZEEA', '<p>ZEEA-BODY {{firstname}}</p>');
        $this->assertSame('global_override', $source(1));
        $this->assertSame('tenant_override', $source(177));

        // The reviewer's case: tenant 77 has an override, but it does not render, so the file is shown
        // (and is what its learners receive). The preview must not say the edited version is showing.
        template_manager::save_override(self::TPL, 77, 'Broken', '<p>{{#unclosed}}</p>');
        $this->assertSame('tenant_override', template_manager::get_override(self::TPL, 77)->source);
        $this->assertSame('file', $source(77));
        $this->assertDebuggingCalled();
    }

    public function test_an_edited_body_renders_this_plugins_partials(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $engine = mustache_factory::email_engine();
        $context = email_context::get_sample(self::TPL);

        $footer = $engine->render('{{> local_sentientia_emails/partials/footer_note}}', $context);
        $this->assertStringContainsString(self::FOOTER_MARK, $footer);
        $card = $engine->render('{{> local_sentientia_emails/partials/course_info_box}}', $context);
        $this->assertStringContainsString('Anti Money Laundering', $card);

        // The plain engine is unchanged: it has no partials.
        $this->assertSame('', trim(mustache_factory::engine()->render(
            '{{> local_sentientia_emails/partials/footer_note}}', $context)));

        // What a delivered override renders (email_renderer::render, recipient tenant 0 = global override).
        template_manager::save_override(self::TPL, 0, 'Global',
            '<p>EDITED {{firstname}}</p>{{> local_sentientia_emails/partials/footer_note}}');
        $sent = email_renderer::render('local_sentientia_emails/' . self::TPL, $context);
        $this->assertStringContainsString('EDITED Priya', $sent);
        $this->assertStringContainsString(self::FOOTER_MARK, $sent,
            'Saving a template without changes must not take the support footer out of the e-mail.');
    }

    public function test_an_edited_body_cannot_include_anything_else(): void {
        $this->resetAfterTest();
        $engine = mustache_factory::email_engine();
        foreach ([
            'local_sentientia_emails/manage/editor',
            'local_sentientia_emails/compliance/welcome_enrolled',
            'local_sentientia_emails/partials/../manage/page',
            'local_sentientia_emails/partials/sub/dir',
            'core/email_html',
            'core/loginform',
        ] as $name) {
            $this->assertFalse(partials_loader::is_allowed($name), "{$name} must not be includable.");
            $this->assertSame('X|', $engine->render('X{{> ' . $name . '}}|', ['firstname' => 'Priya']),
                "{$name} must render as nothing.");
        }
        $this->assertTrue(partials_loader::is_allowed('local_sentientia_emails/partials/cta_button'));
        // An allowed name with no file renders as nothing too (Mustache's own missing-partial behaviour).
        $this->assertTrue(partials_loader::is_allowed('local_sentientia_emails/partials/no_such_partial'));
        $this->assertSame('X|', $engine->render('X{{> local_sentientia_emails/partials/no_such_partial}}|', []));
    }

    public function test_template_web_services_refuse_a_key_outside_the_registry(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $calls = [
            'get_template (read templates/{key}.mustache)' =>
                fn() => external\template_api::get_template('../../../lib/templates/notification_error', 0),
            'save_template' =>
                fn() => external\template_api::save_template('manage/editor', 0, 'X', '<p>x</p>'),
            'revert_template' =>
                fn() => external\template_api::revert_template('compliance/../manage/editor', 0),
            'preview_template' =>
                fn() => external\template_api::preview_template('core/email_html', 1, '', ''),
        ];
        foreach ($calls as $label => $call) {
            try {
                $call();
                $this->fail("{$label} accepted a key outside the registry.");
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString('Unknown e-mail template', $e->getMessage());
            }
        }
        global $DB;
        $this->assertFalse($DB->record_exists('local_sentientia_email_overrides', ['template_key' => 'manage/editor']));

        // A registry key still works: the file body comes back for editing.
        $data = json_decode(external\template_api::get_template(self::TPL, 0)['data'], true);
        $this->assertSame('file', $data['source']);
        $this->assertStringContainsString('partials/course_info_box', $data['body_html']);
    }
}
