<?php
// This file is part of Sentientia LMS.

/**
 * D-12 (post-demo decision, 2026-11-20): editing a rule in the rule editor keeps every stored value.
 *
 * The edit form pre-selected nothing in Rule Type, Channel and Audience, showed a NULL Trigger Days as 0, had no
 * field for the trigger event, the conditions or the reminder cadence, and manage.php?action=saverule defaulted
 * what it did not receive. So re-saving a seeded rule set its channel to "Email + Popup", its audience to
 * "Learner" (the manager rules then e-mailed learners), its trigger event to 'cron', cleared its conditions,
 * turned a NULL Trigger Days into 0, and an unticked Enabled box saved the rule as enabled. The Rule Type list
 * also lacked five types the plugin processes, so editing the course-completed certificate rule forced it onto
 * another type.
 *
 * These tests render the real form (tab_rules.mustache with manage_controller::rule_form_context()), submit it
 * the way a browser does (selected options, ticked boxes, the hidden 0 before each box, nothing from a
 * disabled fieldset), feed the values to rule_manager::rule_from_form() as manage.php reads them, save, and
 * compare every column.
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
 * Rule editor round trip: load every stored value, save it back unchanged unless edited.
 */
final class rule_form_roundtrip_test extends \advanced_testcase {

    /** @var string[] The columns the editor may write; each must survive an unchanged re-save. */
    private const COMPARED = [
        'rule_name', 'rule_type', 'trigger_event', 'trigger_days', 'channel', 'audience', 'template_key',
        'tenant_id', 'enabled', 'priority', 'conditions_json', 'cadence_days_json', 'max_reminders_per_user',
        'auto_stop_on_completion',
    ];

    /** @var string Conditions the form does not edit. */
    private const CONDITIONS = '{"courseids":[12,34],"minprogress":10}';

    /**
     * Insert a rule row directly, as db/upgrade.php seeds them.
     *
     * @param array $fields
     * @return \stdClass the stored row
     */
    private function insert_rule(array $fields = []): \stdClass {
        global $DB;
        $row = (object) array_merge([
            'rule_name'               => 'Fixture rule',
            'rule_type'               => 'custom',
            'trigger_event'           => 'cron',
            'trigger_days'            => 7,
            'channel'                 => 'email',
            'audience'                => 'learner',
            'template_key'            => 'compliance/deadline_warning',
            'tenant_id'               => 0,
            'enabled'                 => 1,
            'priority'                => 50,
            'conditions_json'         => null,
            'cadence_days_json'       => null,
            'max_reminders_per_user'  => 0,
            'auto_stop_on_completion' => 1,
            'usermodified'            => 2,
            'timecreated'             => 1700000000,
            'timemodified'            => 1700000000,
        ], $fields);
        $id = $DB->insert_record(rule_manager::TABLE, $row);
        return $DB->get_record(rule_manager::TABLE, ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The seeded rules (db/upgrade.php) plus rows an older editor or upgrade could have left.
     *
     * @return array label => fields
     */
    private function fixtures(): array {
        return [
            'new_course (event, NULL days, popup, all)' => [
                'rule_name' => 'New course available', 'rule_type' => 'new_course',
                'trigger_event' => '\\core\\event\\course_created', 'trigger_days' => null, 'channel' => 'popup',
                'audience' => 'all', 'template_key' => 'notifications/new_course_available', 'priority' => 40,
            ],
            'weekly_escalation (manager audience)' => [
                'rule_name' => 'Compliance: Weekly manager escalation', 'rule_type' => 'weekly_escalation',
                'trigger_days' => 7, 'channel' => 'email', 'audience' => 'manager',
                'template_key' => 'compliance/weekly_escalation', 'priority' => 90,
            ],
            'course_completed (Sprint B, max 1, no auto-stop)' => [
                'rule_name' => 'Course completed: congratulations + certificate', 'rule_type' => 'course_completed',
                'trigger_event' => '\\core\\event\\course_completed', 'trigger_days' => null,
                'template_key' => 'enrollment/course_completed', 'priority' => 95,
                'max_reminders_per_user' => 1, 'auto_stop_on_completion' => 0,
            ],
            'course_incomplete (cadence)' => [
                'rule_name' => 'Course incomplete: ramping reminders (1-3-7-14-21)', 'rule_type' => 'course_incomplete',
                'trigger_days' => null, 'template_key' => 'notifications/course_not_started', 'priority' => 60,
                'cadence_days_json' => '[1,3,7,14,21]', 'max_reminders_per_user' => 5,
            ],
            'deadline_approaching (conditions, disabled, negative days)' => [
                'rule_name' => 'Compliance: Deadline warning (7 days left)', 'rule_type' => 'deadline_approaching',
                'trigger_days' => -7, 'channel' => 'email,popup', 'conditions_json' => self::CONDITIONS,
                'enabled' => 0, 'priority' => 85,
            ],
            'manager_course_completed (parity type, no template)' => [
                'rule_name' => 'Course completed: copy to the learner\'s manager', 'rule_type' => 'manager_course_completed',
                'trigger_event' => '\\core\\event\\course_completed', 'trigger_days' => null, 'audience' => 'manager',
                'template_key' => '', 'priority' => 60,
            ],
            'values the lists do not offer, a name with & and quotes, odd cadence spacing' => [
                'rule_name' => 'Ops & "Audit" reminder', 'rule_type' => 'legacy_digest', 'trigger_event' => '',
                'channel' => 'teams', 'audience' => 'hr', 'template_key' => null, 'trigger_days' => 0,
                'cadence_days_json' => '[2, 4,8]', 'priority' => 33,
            ],
        ];
    }

    /**
     * The rule form as manage.php renders it.
     *
     * @param \stdClass|null $rule the rule being edited; null for a new rule
     * @return string HTML
     */
    private function render_form(?\stdClass $rule): string {
        global $OUTPUT;
        $context = [
            'show_form'          => true,
            'editrule_readonly'  => false,
            'rule_scope_options' => manage_controller::rule_scope_options(0, $rule ? (int) $rule->tenant_id : null),
            'template_options'   => manage_controller::rule_template_options($rule ? (string) ($rule->template_key ?? '') : ''),
            'tabdata'            => ['rule_count' => 0, 'rules' => []],
            'sesskey'            => sesskey(),
            'manage_url'         => 'https://example.invalid/local/sentientia_emails/manage.php',
            'tenant_id'          => 0,
        ] + manage_controller::rule_form_context($rule);
        return $OUTPUT->render_from_template('local_sentientia_emails/manage/tab_rules', $context);
    }

    /**
     * Parse the form's HTML.
     *
     * @param string $html
     * @return \DOMXPath
     */
    private function dom(string $html): \DOMXPath {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($doc);
    }

    /**
     * What a browser submits for the form: later fields of the same name win, as in PHP's $_POST.
     *
     * @param string $html
     * @return array name => value
     */
    private function submit(string $html): array {
        $xpath = $this->dom($html);
        $form = $xpath->query('//form')->item(0);
        $this->assertNotNull($form, 'The rule form is rendered.');
        $post = [];
        foreach ($xpath->query('.//input|.//select|.//textarea', $form) as $el) {
            $name = $el->getAttribute('name');
            if ($name === '' || $el->hasAttribute('disabled')
                    || $xpath->query('ancestor::fieldset[@disabled]', $el)->length > 0) {
                continue;
            }
            if ($el->nodeName === 'select') {
                $value = null;
                $options = $xpath->query('.//option', $el);
                foreach ($options as $option) {
                    if ($option->hasAttribute('selected')) {
                        $value = $option->getAttribute('value');
                    }
                }
                if ($value === null && $options->length > 0) {
                    $value = $options->item(0)->getAttribute('value');
                }
                if ($value !== null) {
                    $post[$name] = $value;
                }
                continue;
            }
            if ($el->nodeName === 'textarea') {
                $post[$name] = $el->textContent;
                continue;
            }
            $type = strtolower($el->getAttribute('type') ?: 'text');
            if (in_array($type, ['submit', 'button', 'reset'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$el->hasAttribute('checked')) {
                continue;
            }
            $post[$name] = $el->hasAttribute('value') ? $el->getAttribute('value') : 'on';
        }
        return $post;
    }

    /**
     * The values manage.php?action=saverule hands to rule_from_form() for a submission.
     *
     * @param array $post
     * @return array
     */
    private function form_values(array $post): array {
        $raw = fn(string $k): ?string => array_key_exists($k, $post) ? trim((string) $post[$k]) : null;
        $int = fn(string $k): ?int => array_key_exists($k, $post) ? (int) clean_param($post[$k], PARAM_INT) : null;
        return [
            'rule_name'               => clean_param($post['rule_name'] ?? '', PARAM_TEXT),
            'rule_type'               => $raw('rule_type'),
            'trigger_days'            => $raw('trigger_days'),
            'channel'                 => $raw('channel'),
            'audience'                => $raw('audience'),
            'template_key'            => $raw('template_key'),
            'tenant_id'               => $raw('rule_tenant'),
            'enabled'                 => $int('rule_enabled'),
            'priority'                => $raw('priority'),
            'cadence_days_json'       => $raw('cadence_days_json'),
            'max_reminders_per_user'  => $raw('max_reminders_per_user'),
            'auto_stop_on_completion' => $int('auto_stop_on_completion'),
        ];
    }

    /**
     * Every column the editor may write, as the database returns it.
     *
     * @param int $id
     * @return array
     */
    private function snapshot(int $id): array {
        global $DB;
        $row = $DB->get_record(rule_manager::TABLE, ['id' => $id], '*', MUST_EXIST);
        $snapshot = [];
        foreach (self::COMPARED as $field) {
            $snapshot[$field] = $row->$field === null ? null : (string) $row->$field;
        }
        return $snapshot;
    }

    /**
     * Assert that rule_from_form() refuses the values with $errorcode.
     *
     * @param array $form
     * @param \stdClass|null $stored
     * @param string $errorcode
     * @param string $label
     */
    private function assert_refused(array $form, ?\stdClass $stored, string $errorcode, string $label): void {
        try {
            rule_manager::rule_from_form($form, $stored);
            $this->fail("{$label}: accepted.");
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $label);
            $this->assertSame('local_sentientia_emails', $e->module, $label);
        }
    }

    public function test_every_stored_value_is_loaded_and_survives_an_unchanged_resave(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach ($this->fixtures() as $label => $fields) {
            $stored = $this->insert_rule($fields);
            $before = $this->snapshot((int) $stored->id);
            $html = $this->render_form($stored);
            $xpath = $this->dom($html);

            // Each select has exactly the rule's value selected.
            foreach (['rule_type', 'channel', 'audience'] as $field) {
                $selected = $xpath->query("//select[@name='{$field}']/option[@selected]");
                $this->assertSame(1, $selected->length, "{$label}: one {$field} option is selected.");
                $this->assertSame((string) $stored->$field, $selected->item(0)->getAttribute('value'), "{$label}: {$field}");
            }
            // Trigger Days shows the stored number, blank for NULL.
            $days = $xpath->query("//input[@name='trigger_days']")->item(0)->getAttribute('value');
            $this->assertSame($stored->trigger_days === null ? '' : (string) (int) $stored->trigger_days, $days, $label);
            // The trigger event and the conditions are shown, not posted.
            if ((string) $stored->trigger_event !== '') {
                $this->assertStringContainsString(s($stored->trigger_event), $html, "{$label}: trigger event shown");
            }
            if (!empty($stored->conditions_json)) {
                $this->assertStringContainsString(s($stored->conditions_json), $html, "{$label}: conditions shown");
            }
            $this->assertStringNotContainsString('[[', $html, "{$label}: every label is a lang string");

            $post = $this->submit($html);
            $this->assertArrayNotHasKey('trigger_event', $post, $label);
            $this->assertArrayNotHasKey('conditions_json', $post, $label);

            $record = rule_manager::rule_from_form($this->form_values($post), $stored);
            $this->assertEquals((object) ['id' => (int) $stored->id], $record, "{$label}: an unchanged edit writes nothing");
            rule_manager::save_rule($record);
            $this->assertSame($before, $this->snapshot((int) $stored->id), "{$label}: every column is unchanged");
        }
    }

    public function test_a_tenant_rule_keeps_its_scope(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roots = array_values(array_filter(array_column(manage_controller::rule_scope_options(0), 'id')));
        if (!$roots) {
            $this->markTestSkipped('No tenant root is registered on this site.');
        }
        $stored = $this->insert_rule(['tenant_id' => $roots[0], 'audience' => 'manager', 'channel' => 'popup']);
        $before = $this->snapshot((int) $stored->id);

        $post = $this->submit($this->render_form($stored));
        $this->assertSame((string) $roots[0], $post['rule_tenant']);
        rule_manager::save_rule(rule_manager::rule_from_form($this->form_values($post), $stored));
        $this->assertSame($before, $this->snapshot((int) $stored->id));
    }

    public function test_a_rule_for_an_unregistered_tenant_keeps_its_scope(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        // A suspended, retired or legacy tenant: tenant_registry::valid_roots() does not list it.
        $tenant = 999;
        $offered = array_column(manage_controller::rule_scope_options(0), 'id');
        $this->assertNotContains($tenant, $offered, 'Tenant 999 is not a registered root on this site.');
        $stored = $this->insert_rule(['tenant_id' => $tenant, 'audience' => 'manager', 'channel' => 'popup']);
        $before = $this->snapshot((int) $stored->id);

        // The scope select offers it as a saved value, selected, for that rule only.
        $options = manage_controller::rule_scope_options(0, $tenant);
        $this->assertSame(array_merge($offered, [$tenant]), array_column($options, 'id'));
        $this->assertSame([$tenant], array_values(array_column(array_filter($options, fn($o) => $o['selected']), 'id')));
        $this->assertSame(get_string('rule_option_saved', 'local_sentientia_emails',
            get_string('rule_scope_tenant', 'local_sentientia_emails', manage_controller::tenant_name($tenant))),
            end($options)['name']);
        $this->assertSame($offered, array_column(manage_controller::rule_scope_options(0, 0), 'id'));
        $this->assertSame($offered, array_column(manage_controller::rule_scope_options($tenant), 'id'),
            'A new rule is offered the registered scopes only.');
        $this->assertNotContains($tenant, array_column(manage_controller::tenant_selector_options($tenant), 'id'),
            'The page tenant selector is unchanged.');

        // The browser posts the saved scope, not Global, and an unchanged re-save writes nothing.
        $html = $this->render_form($stored);
        $this->assertSame(1, $this->dom($html)->query("//select[@name='rule_tenant']/option[@selected]")->length);
        $post = $this->submit($html);
        $this->assertSame((string) $tenant, $post['rule_tenant'], 'The re-save must not turn the rule global.');
        $record = rule_manager::rule_from_form($this->form_values($post), $stored);
        $this->assertEquals((object) ['id' => (int) $stored->id], $record, 'An unchanged edit writes nothing.');
        rule_manager::save_rule($record);
        $this->assertSame($before, $this->snapshot((int) $stored->id));

        // Changing only the priority keeps the scope.
        $stored = rule_manager::get_rule((int) $stored->id);
        rule_manager::save_rule(rule_manager::rule_from_form($this->form_values(['priority' => '61'] + $post), $stored));
        $this->assertSame(array_merge($before, ['priority' => '61']), $this->snapshot((int) $stored->id));

        // The admin can still move it to an offered scope on purpose.
        $stored = rule_manager::get_rule((int) $stored->id);
        $post = $this->submit($this->render_form($stored));
        rule_manager::save_rule(rule_manager::rule_from_form($this->form_values(['rule_tenant' => '0'] + $post), $stored));
        $this->assertSame(array_merge($before, ['priority' => '61', 'tenant_id' => '0']), $this->snapshot((int) $stored->id));
    }

    public function test_an_edit_writes_only_the_fields_that_changed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixtures = $this->fixtures();
        $stored = $this->insert_rule($fixtures['new_course (event, NULL days, popup, all)']
            + ['conditions_json' => self::CONDITIONS]);
        $before = $this->snapshot((int) $stored->id);
        $post = $this->submit($this->render_form($stored));

        // Priority only.
        $record = rule_manager::rule_from_form($this->form_values(['priority' => '41'] + $post), $stored);
        $this->assertEquals((object) ['priority' => 41, 'id' => (int) $stored->id], $record);
        rule_manager::save_rule($record);
        $this->assertSame(array_merge($before, ['priority' => '41']), $this->snapshot((int) $stored->id));

        // Channel and audience, from the offered lists.
        $stored = rule_manager::get_rule((int) $stored->id);
        rule_manager::save_rule(rule_manager::rule_from_form(
            $this->form_values(['channel' => 'email', 'audience' => 'manager', 'priority' => '41'] + $post), $stored));
        $this->assertSame(array_merge($before, ['channel' => 'email', 'audience' => 'manager', 'priority' => '41']),
            $this->snapshot((int) $stored->id));

        // A new rule type takes that type's trigger event; the conditions stay.
        $stored = rule_manager::get_rule((int) $stored->id);
        rule_manager::save_rule(rule_manager::rule_from_form(
            $this->form_values(['rule_type' => 'custom'] + $this->submit($this->render_form($stored))), $stored));
        $after = $this->snapshot((int) $stored->id);
        $this->assertSame('custom', $after['rule_type']);
        $this->assertSame('cron', $after['trigger_event']);
        $this->assertSame(self::CONDITIONS, $after['conditions_json']);
        $this->assertNull($after['trigger_days'], 'A blank Trigger Days stays NULL.');
    }

    public function test_the_enabled_box_switches_a_rule_off_and_on(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $stored = $this->insert_rule(['enabled' => 1]);

        // The form carries a hidden 0 before the box, so unticking it submits 0.
        $html = $this->render_form($stored);
        $this->assertSame(1, $this->dom($html)->query("//input[@type='hidden'][@name='rule_enabled'][@value='0']")->length);
        $post = $this->submit($html);
        $this->assertSame('1', $post['rule_enabled'], 'Ticked: the box overrides the hidden 0.');
        $unticked = array_merge($post, ['rule_enabled' => '0']);
        rule_manager::save_rule(rule_manager::rule_from_form($this->form_values($unticked), $stored));
        $this->assertSame('0', $this->snapshot((int) $stored->id)['enabled'], 'The rule is switched off.');

        // A disabled rule renders unticked, and an unchanged re-save keeps it off.
        $stored = rule_manager::get_rule((int) $stored->id);
        $post = $this->submit($this->render_form($stored));
        $this->assertSame('0', $post['rule_enabled']);
        rule_manager::save_rule(rule_manager::rule_from_form($this->form_values($post), $stored));
        $this->assertSame('0', $this->snapshot((int) $stored->id)['enabled']);

        // Ticking it again.
        rule_manager::save_rule(rule_manager::rule_from_form($this->form_values(['rule_enabled' => '1'] + $post), $stored));
        $this->assertSame('1', $this->snapshot((int) $stored->id)['enabled']);

        // A request without the field (a form from before this fix) keeps the stored value.
        $stored = rule_manager::get_rule((int) $stored->id);
        $values = $this->form_values($post);
        $values['enabled'] = null;
        $this->assertFalse(property_exists(rule_manager::rule_from_form($values, $stored), 'enabled'));
    }

    public function test_trigger_days_null_and_zero_are_different(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $base = ['rule_name' => 'Days', 'rule_type' => 'course_not_started', 'channel' => 'email', 'audience' => 'learner'];

        $id = rule_manager::save_rule(rule_manager::rule_from_form($base + ['trigger_days' => '']));
        $this->assertNull($this->snapshot($id)['trigger_days'], 'Blank is NULL (process_rules applies its default).');

        $stored = rule_manager::get_rule($id);
        $this->assertFalse(property_exists(rule_manager::rule_from_form(['trigger_days' => ''], $stored), 'trigger_days'));
        rule_manager::save_rule(rule_manager::rule_from_form(['trigger_days' => '0'], $stored));
        $this->assertSame('0', $this->snapshot($id)['trigger_days'], '0 is stored as 0, not NULL.');

        $stored = rule_manager::get_rule($id);
        rule_manager::save_rule(rule_manager::rule_from_form(['trigger_days' => '+14'], $stored));
        $this->assertSame('14', $this->snapshot($id)['trigger_days']);
    }

    public function test_values_the_editor_does_not_offer_are_refused_and_nothing_is_saved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $stored = $this->insert_rule();
        $before = $this->snapshot((int) $stored->id);
        $valid = $this->form_values($this->submit($this->render_form($stored)));

        $refusals = [
            'rule_field_invalid' => [
                ['rule_type' => 'bogus'], ['rule_type' => 'manage/page'], ['rule_type' => ''],
                ['rule_type' => 'CUSTOM'], ['channel' => 'teams'], ['channel' => 'email, popup'],
                ['channel' => 'all'], ['audience' => 'root'], ['audience' => 'Learner'],
                ['priority' => '0'], ['priority' => '101'], ['priority' => 'abc'], ['priority' => ''],
                ['priority' => '5.5'], ['trigger_days' => '366'], ['trigger_days' => '-366'],
                ['trigger_days' => '1e3'], ['trigger_days' => '7 days'], ['max_reminders_per_user' => '1000'],
                ['max_reminders_per_user' => '-1'], ['tenant_id' => '-1'], ['tenant_id' => 'x'],
                ['rule_name' => ''], ['rule_name' => str_repeat('n', 256)],
            ],
            'rule_cadence_invalid' => [
                ['cadence_days_json' => '[0]'], ['cadence_days_json' => '[1,"3"]'], ['cadence_days_json' => '[]'],
                ['cadence_days_json' => 'not json'], ['cadence_days_json' => '{"d":[1]}'],
                ['cadence_days_json' => '[1,2,3,4,5,6,7,8,9,10,11]'],
            ],
            'template_unknown' => [['template_key' => 'manage/page'], ['template_key' => '../../../config']],
        ];
        foreach ($refusals as $errorcode => $cases) {
            foreach ($cases as $case) {
                $this->assert_refused($case + $valid, $stored, $errorcode, json_encode($case));
            }
        }
        $this->assertSame($before, $this->snapshot((int) $stored->id), 'A refused edit changes nothing.');

        // A new rule needs a name, a type, a channel and an audience.
        $new = ['rule_name' => 'New', 'rule_type' => 'custom', 'channel' => 'email', 'audience' => 'learner'];
        foreach (array_keys($new) as $required) {
            $case = $new;
            unset($case[$required]);
            $this->assert_refused($case, null, 'rule_field_invalid', "new rule without {$required}");
        }
    }

    public function test_a_stored_value_outside_the_lists_is_kept_for_that_rule_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $odd = $this->insert_rule(['rule_type' => 'legacy_digest', 'channel' => 'teams', 'audience' => 'hr']);
        $plain = $this->insert_rule();

        foreach (['rule_type' => 'legacy_digest', 'channel' => 'teams', 'audience' => 'hr'] as $field => $value) {
            $this->assertContains($value, rule_manager::choices($field, $odd));
            $this->assertNotContains($value, rule_manager::choices($field, $plain));
            $this->assertNotContains($value, rule_manager::choices($field));
        }
        // The option is labelled as the saved value.
        $html = $this->render_form($odd);
        $this->assertStringContainsString(s(get_string('rule_option_saved', 'local_sentientia_emails', 'teams')), $html);

        // Another rule cannot be moved onto it.
        $valid = $this->form_values($this->submit($this->render_form($plain)));
        $this->assert_refused(['channel' => 'teams'] + $valid, $plain, 'rule_field_invalid', 'teams on another rule');

        // The odd rule can still be moved onto an offered value.
        $values = $this->form_values($this->submit($html));
        rule_manager::save_rule(rule_manager::rule_from_form(['channel' => 'email'] + $values, $odd));
        $this->assertSame('email', $this->snapshot((int) $odd->id)['channel']);
        $this->assertSame('hr', $this->snapshot((int) $odd->id)['audience']);
    }

    public function test_a_new_rule_gets_the_column_defaults_and_its_types_trigger_event(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The new-rule form: no type chosen yet, Email + Popup to learners, enabled, auto-stop ticked.
        $html = $this->render_form(null);
        $xpath = $this->dom($html);
        $this->assertSame(0, $xpath->query("//select[@name='rule_type']/option[@selected]")->length);
        $this->assertSame('email,popup', $xpath->query("//select[@name='channel']/option[@selected]")->item(0)->getAttribute('value'));
        $this->assertSame('learner', $xpath->query("//select[@name='audience']/option[@selected]")->item(0)->getAttribute('value'));
        $this->assertStringNotContainsString('[[', $html);
        $post = $this->submit($html);
        $this->assertSame('1', $post['rule_enabled']);
        $this->assertSame('1', $post['auto_stop_on_completion']);
        $this->assertSame('7', $post['trigger_days']);
        $this->assertSame('', $post['cadence_days_json']);

        $id = rule_manager::save_rule(rule_manager::rule_from_form(
            $this->form_values(['rule_name' => 'Certificate', 'rule_type' => 'course_completed', 'trigger_days' => ''] + $post)));
        $this->assertSame([
            'rule_name' => 'Certificate', 'rule_type' => 'course_completed',
            'trigger_event' => '\\core\\event\\course_completed', 'trigger_days' => null, 'channel' => 'email,popup',
            'audience' => 'learner', 'template_key' => '', 'tenant_id' => '0', 'enabled' => '1', 'priority' => '50',
            'conditions_json' => '', 'cadence_days_json' => null, 'max_reminders_per_user' => '0',
            'auto_stop_on_completion' => '1',
        ], $this->snapshot($id));

        $this->assertSame('cron', rule_manager::default_trigger_event('custom'));
        $this->assertSame('\\core\\event\\course_created', rule_manager::default_trigger_event('new_course'));
        $this->assertSame('\\core\\event\\user_enrolment_created', rule_manager::default_trigger_event('course_enrolled'));
        $this->assertSame(1, $DB->count_records(rule_manager::TABLE));
    }

    public function test_the_request_cannot_set_the_trigger_event_or_the_conditions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $smuggled = ['trigger_event' => '\\core\\event\\user_loggedin', 'conditions_json' => '{"all":true}'];

        $id = rule_manager::save_rule(rule_manager::rule_from_form($smuggled
            + ['rule_name' => 'New', 'rule_type' => 'custom', 'channel' => 'email', 'audience' => 'learner']));
        $this->assertSame('cron', $this->snapshot($id)['trigger_event']);
        $this->assertSame('', $this->snapshot($id)['conditions_json']);

        $stored = $this->insert_rule(['conditions_json' => self::CONDITIONS, 'trigger_event' => '\\core\\event\\course_created']);
        $record = rule_manager::rule_from_form($smuggled + ['priority' => '51'], $stored);
        $this->assertEquals((object) ['priority' => 51, 'id' => (int) $stored->id], $record);
    }

    public function test_the_cadence_is_checked_and_stored_compact_unless_unchanged(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $stored = $this->insert_rule(['rule_type' => 'course_incomplete', 'cadence_days_json' => '[2, 4,8]']);

        // Unchanged (even with its own spacing): not written.
        $this->assertFalse(property_exists(
            rule_manager::rule_from_form(['cadence_days_json' => ' [2, 4,8] '], $stored), 'cadence_days_json'));
        // Changed: stored compact.
        rule_manager::save_rule(rule_manager::rule_from_form(['cadence_days_json' => '[1, 3, 7]'], $stored));
        $this->assertSame('[1,3,7]', $this->snapshot((int) $stored->id)['cadence_days_json']);
        // Cleared: NULL, the site default.
        $stored = rule_manager::get_rule((int) $stored->id);
        rule_manager::save_rule(rule_manager::rule_from_form(['cadence_days_json' => ''], $stored));
        $this->assertNull($this->snapshot((int) $stored->id)['cadence_days_json']);

        // The shared check: the admin setting's rules.
        $this->assertNull(rule_manager::cadence_error(''));
        $this->assertNull(rule_manager::cadence_error('[1,3,7,14,21]'));
        $this->assertNotNull(rule_manager::cadence_error('[1,"3"]'));
        $this->assertNotNull(rule_manager::cadence_error(json_encode(range(1, rule_manager::CADENCE_MAX_ENTRIES + 1))));
        $this->assertNull(rule_manager::cadence_error(json_encode(range(1, rule_manager::CADENCE_MAX_ENTRIES))));
    }

    public function test_the_lists_cover_every_rule_type_the_plugin_uses_and_have_en_and_hi_labels(): void {
        $this->resetAfterTest();
        $dir = \core_component::get_component_directory('local_sentientia_emails');

        // Every type db/upgrade.php seeds and every parity sender type is offered.
        $upgrade = (string) file_get_contents($dir . '/db/upgrade.php');
        $this->assertGreaterThanOrEqual(12, preg_match_all("~'rule_type'\s*=>\s*'([a-z_]+)'~", $upgrade, $m));
        $used = array_merge($m[1], [parity_senders::RULE_COURSE_ENROLLED, parity_senders::RULE_PATH_ENROLLED,
            parity_senders::RULE_MANAGER_COPY]);
        foreach (array_unique($used) as $type) {
            $this->assertContains($type, rule_manager::RULE_TYPES, "Rule type '{$type}' is offered.");
        }
        $this->assertSame(count(rule_manager::RULE_TYPES), count(array_unique(rule_manager::RULE_TYPES)));

        // Every option and form label exists in en and hi.
        $load = function(string $lang) use ($dir): array {
            $string = [];
            include($dir . "/lang/{$lang}/local_sentientia_emails.php");
            return $string;
        };
        $en = $load('en');
        $hi = $load('hi');
        $ids = ['rule_form_title_edit', 'rule_form_title_new', 'rule_field_name', 'rule_field_type',
            'rule_field_channel', 'rule_field_audience', 'rule_field_priority', 'rule_field_trigger_days',
            'rule_field_template', 'rule_field_scope', 'rule_field_enabled', 'rule_field_trigger_event',
            'rule_field_conditions', 'rule_field_cadence', 'rule_field_max_reminders', 'rule_field_auto_stop',
            'rule_option_saved', 'rule_field_invalid', 'rule_cadence_invalid'];
        foreach (rule_manager::RULE_TYPES as $type) {
            $ids[] = 'rule_type_' . $type;
        }
        foreach (rule_manager::CHANNELS as $channel) {
            $ids[] = 'rule_channel_' . str_replace(',', '_', $channel);
        }
        foreach (rule_manager::AUDIENCES as $audience) {
            $ids[] = 'rule_audience_' . $audience;
        }
        foreach ($ids as $id) {
            $this->assertNotEmpty($en[$id] ?? '', "en '{$id}'");
            $this->assertNotEmpty($hi[$id] ?? '', "hi '{$id}'");
        }

        // The rendered options carry those labels, not the raw values.
        $options = manage_controller::rule_form_context(null);
        $labels = array_column($options['rule_type_options'], 'label', 'value');
        $this->assertSame($en['rule_type_course_completed'], $labels['course_completed']);
        $this->assertSame(rule_manager::RULE_TYPES, array_keys($labels));
    }
}
