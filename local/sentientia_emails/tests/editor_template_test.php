<?php
// This file is part of Sentientia LMS.

/**
 * UI fixes on the e-mail template editor (editor.php / templates/manage/editor.mustache), 2026-10-15.
 *
 * Found by the scripted visual pass of 2026-10-14 on Moodle 5.1:
 *  - the live preview iframe was blank on first load: its srcdoc was written with triple braces, so the
 *    first double quote in the preview HTML (the XHTML doctype's PUBLIC "..." id) ended the attribute and
 *    only 22 characters (`<!DOCTYPE html PUBLIC `) reached the frame;
 *  - the editor was 16 px wider than its container: `.ap-studio` carried margin: -1rem -1rem 0 to cancel the
 *    padding of the standard page container, but editor.php uses the "embedded" layout, which has none.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the real editor template through Moodle's renderer (so the real Mustache escaping applies) and
 * reads the result back the way a browser does: the srcdoc attribute value is what the iframe document is.
 */
final class editor_template_test extends \advanced_testcase {

    /** @var string The template under test. */
    private const TEMPLATE = 'local_sentientia_emails/manage/editor';

    /**
     * A realistic wrapped e-mail preview, built to contain everything that used to end the attribute early:
     * double quotes (doctype, attributes), single quotes, ampersands, angle brackets in text, a named entity
     * and non-ASCII text. No numeric character references: s() deliberately leaves those unescaped, so the
     * browser decodes them one step early, which is visually identical in the preview.
     *
     * @return string
     */
    private static function realistic_preview(): string {
        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" '
            . '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" lang="en"><head>'
            . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
            . '<title>Reminder &amp; next steps</title></head>'
            . '<body style="margin:0; font-family:\'Montserrat\',Arial,sans-serif;">'
            . '<p>Hi O\'Brien, 5 &gt; 3 and 2 &lt; 4 &nbsp; नमस्ते</p>'
            . '<a href="https://example.invalid/course?id=7&amp;lang=en" class="btn">Start the course</a>'
            . '</body></html>';
    }

    /**
     * Render the editor template with the smallest context it needs.
     *
     * @param string $previewhtml what editor.php passes as preview_html
     * @return string
     */
    private function render_editor(string $previewhtml): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template(self::TEMPLATE, [
            'template_key'     => 'compliance/reminder_start',
            'template_label'   => 'Reminder: Start Course',
            'category'         => 'Compliance',
            'tenant_id'        => 1,
            'tenants'          => [['id' => 1, 'name' => 'Airpay', 'selected' => true]],
            'source'           => 'file',
            'is_override'      => false,
            'has_overrides'    => false,
            'overrides'        => [],
            'current_subject'  => 'Reminder: Start Anti Money Laundering',
            'current_body_raw' => '<p>Hello {{firstname}}</p>',
            'preview_html'     => $previewhtml,
            'placeholders'     => [],
            'smartblocks'      => [],
            'sesskey'          => sesskey(),
            'save_url'         => 'https://example.invalid/local/sentientia_emails/editor.php?template=x&tenant=1',
            'back_url'         => 'https://example.invalid/local/sentientia_emails/manage.php',
            'preview_url'      => 'https://example.invalid/local/sentientia_emails/preview.php',
            'wwwroot'          => 'https://example.invalid',
        ]);
    }

    /**
     * Parse rendered markup the way a browser does and return the live preview iframe.
     *
     * @param string $html rendered editor markup
     * @return \DOMXPath xpath over the parsed document
     */
    private function parse(string $html): \DOMXPath {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The xml prolog makes libxml read the markup as UTF-8, which is what Moodle emits.
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($dom);
    }

    /**
     * Fetch the preview iframe element out of a parsed editor.
     *
     * @param \DOMXPath $xpath
     * @return \DOMElement
     */
    private function preview_frame(\DOMXPath $xpath): \DOMElement {
        $frames = $xpath->query('//iframe[@id="preview-frame"]');
        $this->assertSame(1, $frames->length, 'The editor renders exactly one live preview iframe.');
        return $frames->item(0);
    }

    public function test_srcdoc_carries_the_whole_preview_document(): void {
        $this->resetAfterTest();
        $preview = self::realistic_preview();

        $frame = $this->preview_frame($this->parse($this->render_editor($preview)));
        $srcdoc = $frame->getAttribute('srcdoc');

        // Before the fix the attribute stopped at the first double quote: 22 characters.
        $this->assertGreaterThan(22, strlen($srcdoc));
        $this->assertSame($preview, $srcdoc, 'The iframe document is the whole preview, byte for byte.');
        $this->assertStringContainsString('</html>', $srcdoc);
    }

    public function test_srcdoc_cannot_be_broken_out_of_by_the_preview_html(): void {
        $this->resetAfterTest();
        // Template HTML is admin-authored but still data: it must stay inside the attribute whatever it holds.
        $hostile = '"><script>window.studioXss = 1;</script><img src="x" onerror="window.studioXss = 2"><p x="';

        $xpath = $this->parse($this->render_editor($hostile));
        $frame = $this->preview_frame($xpath);

        $this->assertSame($hostile, $frame->getAttribute('srcdoc'));
        foreach ($xpath->query('//script') as $script) {
            $this->assertStringNotContainsString('studioXss', $script->textContent,
                'No script from the preview HTML reaches the editor page.');
        }
        $this->assertSame(0, $xpath->query('//img[@onerror]')->length,
            'No element from the preview HTML reaches the editor page.');
    }

    public function test_preview_frame_stays_sandboxed_without_scripts(): void {
        $this->resetAfterTest();

        $frame = $this->preview_frame($this->parse($this->render_editor(self::realistic_preview())));

        $this->assertTrue($frame->hasAttribute('sandbox'), 'The preview iframe keeps its sandbox attribute.');
        $tokens = preg_split('/\s+/', trim($frame->getAttribute('sandbox')));
        // allow-scripts together with allow-same-origin would let the framed document lift its own sandbox.
        $this->assertNotContains('allow-scripts', $tokens);
        $this->assertNotContains('allow-top-navigation', $tokens);
        $this->assertNotContains('allow-popups', $tokens);
    }

    public function test_studio_container_has_no_negative_margin(): void {
        $source = file_get_contents(__DIR__ . '/../templates/manage/editor.mustache');
        $this->assertNotFalse($source);

        $this->assertSame(1, preg_match('/^\.ap-studio\s*\{([^}]*)\}/m', $source, $rule),
            'The .ap-studio container rule exists.');
        // A negative margin on a container with no padding to cancel makes it wider than its parent
        // (width:auto grows by the margin on each side): the 16 px horizontal overflow.
        $this->assertDoesNotMatchRegularExpression('/margin[a-z-]*\s*:[^;]*-\s*\d/', $rule[1]);
    }
}
