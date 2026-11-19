<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills;

defined('MOODLE_INTERNAL') || die();

/**
 * Surface walk 2026-11-19 (QW-SKILLS-UI): "tight spaces in elements" on Skills Management.
 *
 * theme_sentientia vendors Bootstrap 4.6, which has no gap-* utilities (Bootstrap 5 only; the theme's
 * _bs5-compat.scss leaves them out on purpose). The gap-2/gap-3 classes in this plugin's templates therefore
 * rendered 0px: the admin header buttons touched each other, "New Skill" wrapped on its own, and each category
 * card's icon touched its name. The spacing now lives in styles.css on the --ap-space-* tokens. These checks
 * keep the Bootstrap 5 classes out and make sure every spacing class a template uses has a rule.
 *
 * The skills datatable also declared five columns while each row carried a sixth cell (the action icons), so
 * the header row stopped short of them; admin.php now declares the actions column (WF-023 pattern).
 *
 * @package    local_sentientia_skills
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class ui_spacing_test extends \basic_testcase {

    /** Plugin root (this file is tests/ui_spacing_test.php). */
    private function plugindir(): string {
        return dirname(__DIR__);
    }

    /**
     * Every class token used in class="..." attributes of the plugin templates, keyed by template file name.
     *
     * @return array<string, string[]>
     */
    private function template_classes(): array {
        $out = [];
        foreach (glob($this->plugindir() . '/templates/*.mustache') as $file) {
            $src = file_get_contents($file);
            preg_match_all('/class="([^"]*)"/', $src, $m);
            $tokens = [];
            foreach ($m[1] as $attr) {
                foreach (preg_split('/\s+/', trim($attr)) as $token) {
                    if ($token !== '') {
                        $tokens[] = $token;
                    }
                }
            }
            $out[basename($file)] = $tokens;
        }
        return $out;
    }

    public function test_templates_use_no_bootstrap5_gap_utilities(): void {
        $classes = $this->template_classes();
        $this->assertArrayHasKey('manage.mustache', $classes);
        foreach ($classes as $template => $tokens) {
            foreach ($tokens as $token) {
                $this->assertDoesNotMatchRegularExpression(
                    '/^(?:row-|column-)?gap-(?:(?:sm|md|lg|xl|xxl)-)?[0-5]$/',
                    $token,
                    "$template uses the Bootstrap 5 utility '$token', which the theme's Bootstrap 4.6 does not "
                        . 'define (it renders 0px). Put the spacing in styles.css on the --ap-space-* tokens.'
                );
            }
        }
    }

    public function test_every_spacing_class_used_has_a_rule(): void {
        $css = file_get_contents($this->plugindir() . '/styles.css');
        $prefixes = ['ap-skills-admin', 'ap-skills__chips', 'ap-skills-selfrate__', 'ap-skills-matrix__'];
        $checked = 0;
        foreach ($this->template_classes() as $template => $tokens) {
            foreach (array_unique($tokens) as $token) {
                $matches = false;
                foreach ($prefixes as $prefix) {
                    if (strpos($token, $prefix) === 0) {
                        $matches = true;
                    }
                }
                if (!$matches) {
                    continue;
                }
                $checked++;
                $this->assertMatchesRegularExpression(
                    '/\.' . preg_quote($token, '/') . '(?![\w-])/',
                    $css,
                    "$template uses class '$token' but styles.css has no rule for it."
                );
            }
        }
        // manage.mustache alone uses ten of them; a rename that drops them all must not pass silently.
        $this->assertGreaterThanOrEqual(10, $checked);
    }

    public function test_admin_header_and_cards_use_the_spacing_classes(): void {
        $src = file_get_contents($this->plugindir() . '/templates/manage.mustache');
        foreach (['ap-skills-admin__header', 'ap-skills-admin__actions', 'ap-skills-admin__cat-card',
                'ap-skills-admin__cat-icon', 'ap-skills-admin__cat-body', 'ap-skills-admin__filter-row'] as $class) {
            $this->assertStringContainsString($class, $src);
        }
        // The CSS uses gap on the 8px grid tokens, never a literal pixel gap.
        $css = file_get_contents($this->plugindir() . '/styles.css');
        $this->assertMatchesRegularExpression(
            '/\.ap-skills-admin \.ap-skills-admin__actions \{[^}]*gap: var\(--ap-space-2/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.ap-skills-admin \.ap-skills-admin__cat-card \{[^}]*gap: var\(--ap-space-3/',
            $css
        );
    }

    public function test_skills_datatable_declares_its_actions_column(): void {
        $src = file_get_contents($this->plugindir() . '/admin.php');
        $this->assertMatchesRegularExpression(
            "/\\['key' => 'actions',\\s+'label' => get_string\\('actions'\\), 'sortable' => false, 'format' => 'html'\\]/",
            $src,
            'admin.php must declare the actions column, or the row icons sit in a 6th cell under a 5-cell header.'
        );
    }
}
