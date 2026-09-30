<?php
// This file is part of Sentientia LMS. GNU GPL v3 or later.

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Step 1b of cli/relabel_plugin.php: the message-provider preference keys of a
 * renamed plugin (2026-09-30). The logic used to sit inline in the CLI with no
 * test; it now lives in message_pref_relabel.
 *
 * Every test uses its own provider ('zzrelabel') under made-up components, so no
 * real provider's keys are touched. Config rows are written and read through $DB,
 * the same way the class does, so nothing here depends on the config cache.
 *
 * @package    local_sentientia_core
 * @covers     \local_sentientia_core\message_pref_relabel
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class message_pref_relabel_test extends \advanced_testcase {

    private const FROM = 'local_zzold';
    private const TO = 'local_zznew';
    private const PROV = 'zzrelabel';

    /** @var string[] two installed message processors */
    private array $procs;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $names = $DB->get_fieldset_select('message_processors', 'name', '1 = 1');
        sort($names);
        $this->assertGreaterThanOrEqual(2, count($names), 'The test needs two message processors.');
        $this->procs = array_slice($names, 0, 2);
        // The provider row is still under the old component at step 1b (step 2 moves it).
        $DB->insert_record('message_providers',
            (object) ['name' => self::PROV, 'component' => self::FROM, 'capability' => null]);
    }

    private function lockkey(string $component, string $proc): string {
        return "{$proc}_provider_{$component}_" . self::PROV . '_locked';
    }

    private function enabledkey(string $component): string {
        return "message_provider_{$component}_" . self::PROV . '_enabled';
    }

    private function put(string $name, string $value): void {
        global $DB;
        $DB->insert_record('config_plugins', (object) ['plugin' => 'message', 'name' => $name, 'value' => $value]);
    }

    /** @return string|false the stored value, false when the key does not exist */
    private function get(string $name) {
        global $DB;
        return $DB->get_field('config_plugins', 'value', ['plugin' => 'message', 'name' => $name]);
    }

    /** @return array<string,string> every 'message' config row, by name */
    private function snapshot(): array {
        global $DB;
        return $DB->get_records_menu('config_plugins', ['plugin' => 'message'], 'name', 'name, value');
    }

    private function relabel(bool $run, ?array &$lines = null): array {
        $lines = [];
        return message_pref_relabel::relabel(self::FROM, self::TO, $run, function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
    }

    /** Legacy locks for both processors and an _enabled key naming both. */
    private function seed_full_legacy(): void {
        [$p1, $p2] = $this->procs;
        $this->put($this->lockkey(self::FROM, $p1), '1');
        $this->put($this->lockkey(self::FROM, $p2), '0');
        $this->put($this->enabledkey(self::FROM), "{$p1},{$p2}");
    }

    public function test_every_locked_processor_moves_and_the_whole_enabled_key_follows(): void {
        [$p1, $p2] = $this->procs;
        $this->seed_full_legacy();

        $counts = $this->relabel(true);

        $this->assertFalse($this->get($this->lockkey(self::FROM, $p1)));
        $this->assertFalse($this->get($this->lockkey(self::FROM, $p2)));
        $this->assertSame('1', $this->get($this->lockkey(self::TO, $p1)));
        $this->assertSame('0', $this->get($this->lockkey(self::TO, $p2)));
        $this->assertFalse($this->get($this->enabledkey(self::FROM)), 'Every member moved, so the old key is renamed.');
        $this->assertSame("{$p1},{$p2}", $this->get($this->enabledkey(self::TO)));
        $this->assertSame(3, $counts['config']);
    }

    public function test_partial_carry_writes_only_the_processors_whose_lock_moved(): void {
        [$p1, $p2] = $this->procs;
        // Only the first processor has a legacy lock; the enabled list names both.
        $this->put($this->lockkey(self::FROM, $p1), '1');
        $this->put($this->enabledkey(self::FROM), "{$p1},{$p2}");

        $lines = [];
        $counts = $this->relabel(true, $lines);

        $this->assertSame('1', $this->get($this->lockkey(self::TO, $p1)));
        $this->assertSame($p1, $this->get($this->enabledkey(self::TO)),
            'Only the processor whose lock moved is carried into the new enabled key.');
        $this->assertSame("{$p1},{$p2}", $this->get($this->enabledkey(self::FROM)), 'The old key is left alone.');
        $this->assertSame(2, $counts['config']);
        $this->assertStringContainsString('only, the processors whose lock moved', implode("\n", $lines));
    }

    public function test_no_lock_moved_leaves_the_enabled_key_alone(): void {
        [$p1, $p2] = $this->procs;
        $this->put($this->enabledkey(self::FROM), "{$p1},{$p2}");

        $lines = [];
        $counts = $this->relabel(true, $lines);

        $this->assertFalse($this->get($this->enabledkey(self::TO)));
        $this->assertSame("{$p1},{$p2}", $this->get($this->enabledkey(self::FROM)));
        $this->assertSame(0, $counts['config']);
        $this->assertStringContainsString('none of its processors had a lock moved', implode("\n", $lines));
    }

    public function test_an_existing_new_enabled_key_gets_the_moved_processors_merged_in(): void {
        [$p1, $p2] = $this->procs;
        $this->put($this->lockkey(self::FROM, $p1), '1');
        $this->put($this->enabledkey(self::FROM), "{$p1},{$p2}");
        $this->put($this->enabledkey(self::TO), $p2);

        $this->relabel(true);

        $this->assertSame("{$p2},{$p1}", $this->get($this->enabledkey(self::TO)));
        $this->assertSame("{$p1},{$p2}", $this->get($this->enabledkey(self::FROM)), 'The old key is left alone.');
    }

    public function test_an_existing_new_lock_is_kept_and_its_processor_is_not_carried(): void {
        [$p1] = $this->procs;
        $this->put($this->lockkey(self::FROM, $p1), '1');
        $this->put($this->lockkey(self::TO, $p1), '0');
        $this->put($this->enabledkey(self::FROM), $p1);

        $lines = [];
        $counts = $this->relabel(true, $lines);

        $this->assertSame('0', $this->get($this->lockkey(self::TO, $p1)), 'The new value wins.');
        $this->assertSame('1', $this->get($this->lockkey(self::FROM, $p1)), 'The old key is left.');
        $this->assertFalse($this->get($this->enabledkey(self::TO)), 'Nothing moved, so nothing is carried.');
        $this->assertSame(0, $counts['config']);
        $this->assertStringContainsString('already set (kept;', implode("\n", $lines));
    }

    public function test_the_site_wide_disable_key_moves(): void {
        $old = self::FROM . '_' . self::PROV . '_disable';
        $new = self::TO . '_' . self::PROV . '_disable';
        $this->put($old, '1');

        $counts = $this->relabel(true);

        $this->assertFalse($this->get($old));
        $this->assertSame('1', $this->get($new));
        $this->assertSame(1, $counts['config']);
    }

    public function test_a_dry_run_reports_and_writes_nothing(): void {
        $this->seed_full_legacy();
        $before = $this->snapshot();

        $lines = [];
        $counts = $this->relabel(false, $lines);

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(3, $counts['config'], 'The dry run counts what a real run would do.');
        $text = implode("\n", $lines);
        $this->assertStringContainsString('_locked', $text);
        $this->assertStringContainsString(' -> ', $text);
    }

    public function test_user_rows_move_unless_the_user_already_has_the_new_name(): void {
        global $DB;
        $olduser = $this->enabledkey(self::FROM);
        $newuser = $this->enabledkey(self::TO);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_preferences', (object) ['userid' => $u1->id, 'name' => $olduser, 'value' => 'email']);
        $DB->insert_record('user_preferences', (object) ['userid' => $u2->id, 'name' => $olduser, 'value' => 'email']);
        $DB->insert_record('user_preferences', (object) ['userid' => $u2->id, 'name' => $newuser, 'value' => 'popup']);

        $lines = [];
        $counts = $this->relabel(true, $lines);

        $this->assertSame(1, $counts['users']);
        $this->assertSame('email', $DB->get_field('user_preferences', 'value', ['userid' => $u1->id, 'name' => $newuser]));
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $u1->id, 'name' => $olduser]));
        $this->assertSame('popup', $DB->get_field('user_preferences', 'value', ['userid' => $u2->id, 'name' => $newuser]),
            'A user who already has the new name keeps it.');
        $this->assertTrue($DB->record_exists('user_preferences', ['userid' => $u2->id, 'name' => $olduser]),
            'Their legacy row is left.');
        $this->assertStringContainsString('row(s) left', implode("\n", $lines));
    }

    /**
     * The provider is one transaction: a run that dies after its _locked keys have
     * been renamed but before the _enabled key is handled must put the _locked keys
     * back, not leave the provider with locks under the new name and its enabled
     * membership stranded under the old one.
     */
    public function test_a_failure_part_way_through_a_provider_rolls_it_back(): void {
        [$p1, $p2] = $this->procs;
        $this->seed_full_legacy();
        $before = $this->snapshot();

        $caught = null;
        try {
            message_pref_relabel::relabel(self::FROM, self::TO, true, function (string $line): void {
                // The _enabled key is handled after every _locked key has been renamed.
                if (strpos($line, 'message default message_provider_') !== false) {
                    throw new \RuntimeException('simulated failure');
                }
            });
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'The simulated failure must reach the caller.');
        $this->assertSame($before, $this->snapshot(), 'Nothing of the half-done provider may survive.');
        $this->assertSame('1', $this->get($this->lockkey(self::FROM, $p1)));
        $this->assertSame('0', $this->get($this->lockkey(self::FROM, $p2)));
        $this->assertFalse($this->get($this->lockkey(self::TO, $p1)));
        $this->assertSame("{$p1},{$p2}", $this->get($this->enabledkey(self::FROM)));
    }
}
