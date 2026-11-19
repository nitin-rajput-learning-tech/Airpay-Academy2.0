<?php
// This file is part of Sentientia LMS.

/**
 * G1 (post-demo plan, 2026-11-19): the rule editor accepts only a template from the registry.
 *
 * manage.php?action=saverule read template_key with PARAM_RAW and rule_manager::save_rule() stored it
 * unchecked; notification_sender::send() then renders local_sentientia_emails/{template_key} for every
 * recipient of the rule. So a crafted form could point a rule at this plugin's own page templates, a
 * partial or a ../ path: the same gap the preview, the template editor and the template web services had
 * until QW-EMAIL (2026111900, tests/template_registry_test.php). The rule editor now goes through the
 * same registry check (email_renderer::known_template_key()).
 *
 * Also pinned: the edit form pre-selects the rule's template. It pre-selected nothing, so re-saving a rule
 * (to change its priority, say) quietly stored "None (plain text)" over its template.
 *
 * @package    local_sentientia_emails
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_emails\rule_manager
 * @covers     \local_sentientia_emails\manage_controller
 */

namespace local_sentientia_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule template keys: registry only, stored as the registry key, pre-selected in the form.
 */
final class rule_template_key_test extends \advanced_testcase {

    /** @var string A registry key. */
    private const TPL = 'compliance/deadline_warning';

    /** @var string[] Names a rule must not be able to point at. */
    private const REFUSED = [
        'manage/page',                         // This plugin's own page templates.
        'manage/tab_rules',
        'manage/editor',
        'partials/footer_note',                // A partial is not an e-mail.
        'core/email_html',
        'core/loginform',
        '../../../config',
        'compliance/../manage/editor',
        '/compliance/deadline_warning',
        'compliance/deadline_warning.mustache',
        'Compliance/Deadline_Warning',
        'compliancedeadline_warning',
        'bizlms/course_enrol_12',              // Legacy BizLMS templates are read-only previews, not rule bodies.
        'reminders/not_started',               // A key the imported history carries; no template of that name.
    ];

    /**
     * A rule as the editor posts it.
     *
     * @param array $overrides
     * @return \stdClass
     */
    private function rule(array $overrides = []): \stdClass {
        return (object) array_merge([
            'rule_name'       => 'G1 rule',
            'rule_type'       => 'custom',
            'trigger_event'   => 'cron',
            'trigger_days'    => 7,
            'channel'         => 'email',
            'audience'        => 'learner',
            'template_key'    => self::TPL,
            'tenant_id'       => 0,
            'enabled'         => 1,
            'priority'        => 50,
            'conditions_json' => '',
        ], $overrides);
    }

    /**
     * Assert that save_rule() refuses the rule with template_unknown.
     *
     * @param \stdClass $rule
     * @param string $label
     */
    private function assert_refused(\stdClass $rule, string $label): void {
        try {
            rule_manager::save_rule($rule);
            $this->fail("{$label}: save_rule() accepted a template key outside the registry.");
        } catch (\moodle_exception $e) {
            $this->assertSame('template_unknown', $e->errorcode, $label);
            $this->assertSame('local_sentientia_emails', $e->module, $label);
        }
    }

    public function test_rule_template_key_accepts_none_and_registry_keys_only(): void {
        $this->assertSame('', rule_manager::rule_template_key(''), 'None (plain text) stays allowed.');
        $this->assertSame('', rule_manager::rule_template_key('   '));
        $this->assertSame('', rule_manager::rule_template_key(null));

        foreach (email_renderer::template_keys() as $key) {
            $this->assertSame($key, rule_manager::rule_template_key($key));
            $this->assertSame($key, rule_manager::rule_template_key(" {$key} "));
            // The legacy spelling with "_" for "/" is stored as the registry key.
            $this->assertSame($key, rule_manager::rule_template_key(str_replace('/', '_', $key)));
        }
        foreach (self::REFUSED as $requested) {
            $this->assertNull(rule_manager::rule_template_key($requested), "'{$requested}' must be refused.");
        }
    }

    public function test_save_rule_refuses_a_new_rule_naming_no_registry_template(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach (self::REFUSED as $requested) {
            $this->assert_refused($this->rule(['rule_name' => 'Refused ' . $requested, 'template_key' => $requested]),
                "new rule, '{$requested}'");
        }
        foreach (self::REFUSED as $requested) {
            $this->assertFalse($DB->record_exists('local_sentientia_email_rules', ['template_key' => $requested]),
                "No rule row may carry '{$requested}'.");
        }
        $this->assertSame(0, $DB->count_records_select('local_sentientia_email_rules',
            $DB->sql_like('rule_name', ':n'), ['n' => 'Refused %']), 'A refused rule writes nothing.');
    }

    public function test_save_rule_refuses_an_edit_naming_no_registry_template_and_keeps_the_stored_one(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $id = rule_manager::save_rule($this->rule());

        foreach (self::REFUSED as $requested) {
            $this->assert_refused($this->rule(['id' => $id, 'priority' => 99, 'template_key' => $requested]),
                "edit, '{$requested}'");
        }
        $stored = $DB->get_record('local_sentientia_email_rules', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(self::TPL, $stored->template_key, 'The refused edits changed nothing.');
        $this->assertEquals(50, $stored->priority, 'Nothing else of a refused edit was saved either.');
    }

    public function test_save_rule_stores_the_registry_key_or_none(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $stored = fn(int $id): ?string => $DB->get_field('local_sentientia_email_rules', 'template_key', ['id' => $id]);

        // Every registry key can be chosen, as the form's select offers it.
        foreach (email_renderer::template_keys() as $i => $key) {
            $id = rule_manager::save_rule($this->rule(['rule_name' => "Rule {$i}", 'template_key' => $key]));
            $this->assertSame($key, $stored($id));
        }

        // The legacy spelling and stray whitespace are stored as the registry key.
        $id = rule_manager::save_rule($this->rule(['template_key' => ' compliance_deadline_warning ']));
        $this->assertSame(self::TPL, $stored($id));

        // "None (plain text)".
        $id = rule_manager::save_rule($this->rule(['template_key' => '']));
        $this->assertSame('', $stored($id));

        // An edit that does not send a template_key keeps the rule's template.
        $id = rule_manager::save_rule($this->rule());
        rule_manager::save_rule((object) ['id' => $id, 'priority' => 60]);
        $this->assertSame(self::TPL, $stored($id));
        $this->assertEquals(60, $DB->get_field('local_sentientia_email_rules', 'priority', ['id' => $id]));
    }

    public function test_built_in_rules_name_registry_templates(): void {
        // The rules upgrade.php seeds (2026041001 defaults, Sprint B rules) must stay saveable from the editor:
        // a seeded key outside the registry would make every edit of that rule fail with template_unknown.
        $upgrade = (string) file_get_contents(\core_component::get_component_directory('local_sentientia_emails')
            . '/db/upgrade.php');
        $this->assertGreaterThanOrEqual(12, preg_match_all("~'template_key'\s*=>\s*'([^']*)'~", $upgrade, $m));
        foreach ($m[1] as $key) {
            $this->assertSame($key, rule_manager::rule_template_key($key), "upgrade.php seeds '{$key}'.");
        }

        // parity_senders falls back to its defaults when no rule row exists (or the row has no template).
        $defaults = (new \ReflectionClass(parity_senders::class))->getConstant('DEFAULTS');
        $this->assertNotEmpty($defaults);
        foreach ($defaults as $type => $default) {
            $this->assertSame($default['template'], rule_manager::rule_template_key($default['template']),
                "parity_senders::DEFAULTS['{$type}']");
        }
    }

    public function test_rule_template_options_offer_the_registry_and_preselect_the_rules_template(): void {
        $this->resetAfterTest();
        $selectedkeys = function(array $options): array {
            $keys = [];
            foreach ($options as $cat) {
                foreach ($cat['templates'] as $tpl) {
                    if ($tpl['selected']) {
                        $keys[] = $tpl['key'];
                    }
                }
            }
            return $keys;
        };
        $allkeys = function(array $options): array {
            $keys = [];
            foreach ($options as $cat) {
                $this->assertNotSame('', (string) $cat['category']);
                foreach ($cat['templates'] as $tpl) {
                    $this->assertNotSame('', $tpl['label']);
                    $keys[] = $tpl['key'];
                }
            }
            return $keys;
        };

        // A new rule: the registry, nothing selected (the form's "None" option comes first).
        $options = manage_controller::rule_template_options();
        $this->assertSame(email_renderer::template_keys(), $allkeys($options),
            'The select offers exactly what the save accepts.');
        $this->assertSame([], $selectedkeys($options));

        // An edited rule: its own template, also when stored in the legacy spelling.
        $this->assertSame([self::TPL], $selectedkeys(manage_controller::rule_template_options(self::TPL)));
        $this->assertSame([self::TPL],
            $selectedkeys(manage_controller::rule_template_options('compliance_deadline_warning')));

        // A stored key outside the registry selects nothing.
        foreach (['', 'manage/page', '../../../config'] as $stored) {
            $this->assertSame([], $selectedkeys(manage_controller::rule_template_options($stored)), "'{$stored}'");
        }
    }

    public function test_rule_form_preselects_the_rules_template(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = fn(string $stored): array => [
            'show_form'          => true,
            'has_editrule'       => true,
            'editrule_readonly'  => false,
            'editrule'           => ['id' => 42, 'rule_name' => 'Deadline warning', 'enabled' => true,
                                     'trigger_days' => -7, 'priority' => 85, 'template_key' => $stored],
            'rule_scope_options' => [['id' => 0, 'name' => 'Global', 'selected' => false]],
            'template_options'   => manage_controller::rule_template_options($stored),
            'tabdata'            => ['rule_count' => 0, 'rules' => []],
            'sesskey'            => sesskey(),
            'manage_url'         => 'https://example.invalid/local/sentientia_emails/manage.php',
            'tenant_id'          => 0,
        ];
        $selected = function(string $html): array {
            preg_match_all('~<option value="([a-z]+/[a-z_]+)" selected>~', $html, $m);
            return $m[1];
        };

        $html = $OUTPUT->render_from_template('local_sentientia_emails/manage/tab_rules', $context(self::TPL));
        $this->assertSame([self::TPL], $selected($html), 'Re-saving the rule keeps its template.');
        $this->assertSame(count(email_renderer::template_keys()),
            preg_match_all('~<option value="[a-z]+/[a-z_]+"~', $html), 'Every registry template is offered.');
        $this->assertStringContainsString('<option value="">', $html, '"None (plain text)" is still offered.');

        // A rule with no template: no template option is selected, so "None" (the first option) is.
        $html = $OUTPUT->render_from_template('local_sentientia_emails/manage/tab_rules', $context(''));
        $this->assertSame([], $selected($html));
    }
}
