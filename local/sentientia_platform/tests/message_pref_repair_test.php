<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/messagelib.php');
require_once($CFG->dirroot . '/message/lib.php');

/**
 * message_pref_repair: check + repair of message-provider preferences
 * stranded by the ADR-025 relabel (local_airpay_X -> local_sentientia_X).
 *
 * These tests assert the config keys directly. Under PHPUnit, message_send()
 * returns from message_handle_phpunit_redirection() BEFORE it looks up the
 * <processor>_provider_<component>_<name>_locked preference, so a test using
 * redirectMessages() cannot see this defect - which is why the cart tests
 * stayed green while cart_manager::mark_paid() threw on a relabelled copy.
 *
 * @package local_sentientia_platform
 * @group local_sentientia_platform
 * @covers \local_sentientia_platform\message_pref_repair
 */
final class message_pref_repair_test extends \advanced_testcase {

    /** @return string[] every installed processor name, sorted */
    private static function procs(): array {
        global $DB;
        $procs = array_values($DB->get_fieldset_select('message_processors', 'name', '1 = 1'));
        sort($procs);
        return $procs;
    }

    /** First provider name of a component, from {message_providers}. */
    private function first_provider(string $component): string {
        global $DB;
        $rows = $DB->get_records('message_providers', ['component' => $component], 'name', 'id, name', 0, 1);
        $this->assertNotEmpty($rows, "{$component} has no message provider installed");
        return reset($rows)->name;
    }

    /** A line collector for repair()'s $out. */
    private static function collector(array &$lines): callable {
        return function (string $line) use (&$lines): void {
            $lines[] = $line;
        };
    }

    /** Sorted csv, for order-insensitive _enabled comparisons. */
    private static function sorted_csv($value): string {
        $parts = array_filter(explode(',', (string) $value), 'strlen');
        sort($parts);
        return implode(',', $parts);
    }

    /**
     * (i) A fresh install is healthy: every Sentientia provider has a _locked
     * default for every processor, check() is empty, and repair() has nothing
     * to do.
     */
    public function test_fresh_install_is_healthy(): void {
        global $DB;
        $this->resetAfterTest();

        $config = get_config('message');
        $procs = self::procs();
        $this->assertContains('email', $procs);
        $providers = $DB->get_records_select('message_providers',
            $DB->sql_like('component', ':c'), ['c' => 'local\_sentientia\_%']);
        $this->assertNotEmpty($providers);
        foreach ($providers as $p) {
            foreach ($procs as $proc) {
                $key = "{$proc}_provider_{$p->component}_{$p->name}_locked";
                $this->assertTrue(isset($config->{$key}), "missing {$key}");
            }
        }

        $this->assertSame([], message_pref_repair::check());

        $lines = [];
        $counts = message_pref_repair::repair(false, self::collector($lines));
        foreach (['copied', 'moved', 'shadowed', 'defaulted', 'unresolved', 'errors', 'unmapped'] as $k) {
            $this->assertSame(0, $counts[$k], "{$k}: " . implode("\n", $lines));
        }
    }

    /**
     * (ii) A relabelled provider: new-name keys gone, legacy keys holding the
     * admin's choices, a user's choice under the legacy name.
     */
    public function test_relabelled_provider_is_repaired_from_legacy_keys(): void {
        global $DB;
        $this->resetAfterTest();

        $procs = self::procs();
        $this->assertContains('email', $procs);
        $this->assertContains('popup', $procs);
        $name = $this->first_provider('local_sentientia_cart');
        $base = "local_sentientia_cart_{$name}";
        $oldbase = "local_airpay_cart_{$name}";

        // What relabel_plugin.php step 2 left: no new-name defaults, except popup's,
        // as if that processor had been re-added after the rename, with a current
        // _enabled of 'popup'.
        foreach ($procs as $proc) {
            unset_config("{$proc}_provider_{$base}_locked", 'message');
        }
        unset_config("{$base}_disable", 'message');
        set_config("popup_provider_{$base}_locked", '0', 'message');
        set_config("message_provider_{$base}_enabled", 'popup', 'message');

        // The admin's choices, still under the pre-rename name.
        foreach ($procs as $proc) {
            set_config("{$proc}_provider_{$oldbase}_locked", in_array($proc, ['email', 'popup'], true) ? '1' : '0',
                'message');
        }
        set_config("message_provider_{$oldbase}_enabled", 'email,popup', 'message');
        set_config("{$oldbase}_disable", '1', 'message');

        // User 1 has only the legacy row (movable); user 2 also has the new name (shadowed).
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $olduser = "message_provider_{$oldbase}_enabled";
        $newuser = "message_provider_{$base}_enabled";
        $DB->insert_record('user_preferences', (object) ['userid' => $u1->id, 'name' => $olduser, 'value' => 'email']);
        $DB->insert_record('user_preferences', (object) ['userid' => $u2->id, 'name' => $olduser, 'value' => 'email']);
        set_user_preference($newuser, 'none', $u2);

        $problems = message_pref_repair::check();
        $this->assertNotEmpty($problems);
        $joined = implode("\n", $problems);
        $this->assertStringContainsString("{$base}_disable", $joined);
        $this->assertStringContainsString("1 user choice(s) still under {$olduser}", $joined);

        // Dry run: reports, writes nothing.
        $configbefore = (array) get_config('message');
        $prefsbefore = $DB->get_records('user_preferences', null, 'id', 'id, userid, name, value');
        $lines = [];
        $dry = message_pref_repair::repair(false, self::collector($lines));
        $expectedcopied = (count($procs) - 1) + 1 + 1;  // _locked (all but popup) + merged _enabled + _disable
        $this->assertSame($expectedcopied, $dry['copied'], implode("\n", $lines));
        $this->assertSame(1, $dry['moved']);
        $this->assertSame(1, $dry['shadowed']);
        $this->assertSame(0, $dry['defaulted']);
        $this->assertSame(0, $dry['errors']);
        $this->assertEquals($configbefore, (array) get_config('message'));
        $this->assertEquals($prefsbefore, $DB->get_records('user_preferences', null, 'id', 'id, userid, name, value'));

        // Apply.
        $lines = [];
        $done = message_pref_repair::repair(true, self::collector($lines));
        $this->assertSame($expectedcopied, $done['copied'], implode("\n", $lines));
        $this->assertSame(1, $done['moved']);
        $this->assertSame(1, $done['shadowed']);
        $this->assertSame(0, $done['defaulted']);
        $this->assertSame(0, $done['errors']);
        $this->assertSame(0, $done['unmapped']);

        foreach ($procs as $proc) {
            $got = get_config('message', "{$proc}_provider_{$base}_locked");
            if ($proc === 'popup') {
                $this->assertSame('0', $got, 'an existing new-name _locked key is never overwritten');
            } else {
                $this->assertSame($proc === 'email' ? '1' : '0', $got, "{$proc} lock copied from the legacy name");
            }
        }
        // email came across with its lock; popup's lock was not copied, so its legacy
        // membership is not what put it there - it was already in the current list.
        $this->assertSame('popup,email', get_config('message', "message_provider_{$base}_enabled"));
        $this->assertSame('1', get_config('message', "{$base}_disable"));
        // Copy-only: the legacy keys are still there.
        $this->assertSame('1', get_config('message', "email_provider_{$oldbase}_locked"));
        $this->assertSame('email,popup', get_config('message', "message_provider_{$oldbase}_enabled"));

        $this->assertSame('email', $DB->get_field('user_preferences', 'value', ['userid' => $u1->id, 'name' => $newuser]));
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $u1->id, 'name' => $olduser]));
        $this->assertSame('none', $DB->get_field('user_preferences', 'value', ['userid' => $u2->id, 'name' => $newuser]));
        $this->assertTrue($DB->record_exists('user_preferences', ['userid' => $u2->id, 'name' => $olduser]));

        $this->assertSame([], message_pref_repair::check());

        // Idempotent: a second run changes nothing (the shadowed row is still reported).
        $lines = [];
        $again = message_pref_repair::repair(true, self::collector($lines));
        $this->assertSame(0, $again['copied'], implode("\n", $lines));
        $this->assertSame(0, $again['moved']);
        $this->assertSame(0, $again['defaulted']);
        $this->assertSame(1, $again['shadowed']);
    }

    /**
     * (iii) A provider with no legacy keys at all gets Moodle's defaults from
     * its db/messages.php.
     */
    public function test_provider_without_legacy_keys_gets_file_defaults(): void {
        $this->resetAfterTest();

        $component = 'local_sentientia_courses';
        $name = $this->first_provider($component);
        $base = "{$component}_{$name}";
        $procs = self::procs();

        foreach ($procs as $proc) {
            unset_config("{$proc}_provider_{$base}_locked", 'message');
        }
        unset_config("message_provider_{$base}_enabled", 'message');
        $this->assertNotEmpty(message_pref_repair::check());

        // Dry run writes nothing.
        $lines = [];
        $dry = message_pref_repair::repair(false, self::collector($lines));
        $this->assertSame(1, $dry['defaulted'], implode("\n", $lines));
        $this->assertSame(0, $dry['copied']);
        foreach ($procs as $proc) {
            $this->assertFalse(get_config('message', "{$proc}_provider_{$base}_locked"));
        }

        $lines = [];
        $done = message_pref_repair::repair(true, self::collector($lines));
        $this->assertSame(1, $done['defaulted'], implode("\n", $lines));
        $this->assertSame(0, $done['copied']);
        $this->assertSame(0, $done['errors']);

        // What db/messages.php says, through core's own translation.
        $file = message_get_providers_from_file($component)[$name];
        $expectedenabled = [];
        foreach ($procs as $proc) {
            [$locked, $enabled] = translate_message_default_setting($file['defaults'][$proc] ?? 0, $proc);
            $got = get_config('message', "{$proc}_provider_{$base}_locked");
            $this->assertNotFalse($got, "{$proc} lock written");
            $this->assertSame((bool) $locked, (bool) $got, "{$proc} lock from db/messages.php");
            if ($enabled) {
                $expectedenabled[] = $proc;
            }
        }
        $gotenabled = get_config('message', "message_provider_{$base}_enabled");
        if ($expectedenabled) {
            $this->assertSame(self::sorted_csv(implode(',', $expectedenabled)), self::sorted_csv($gotenabled));
        } else {
            $this->assertFalse($gotenabled);
        }

        $this->assertSame([], message_pref_repair::check());
        $again = message_pref_repair::repair(true, function (string $line): void {
        });
        $this->assertSame(0, $again['defaulted']);
    }

    /**
     * (iv) A healthy provider with no _enabled key (nothing on by default, or
     * an admin turned every processor off) is neither reported nor touched.
     */
    public function test_healthy_provider_without_enabled_is_untouched(): void {
        $this->resetAfterTest();

        $name = $this->first_provider('local_sentientia_cart');
        $key = "message_provider_local_sentientia_cart_{$name}_enabled";
        unset_config($key, 'message');
        $before = (array) get_config('message');

        $this->assertSame([], message_pref_repair::check());
        $lines = [];
        $counts = message_pref_repair::repair(true, self::collector($lines));
        foreach (['copied', 'moved', 'defaulted', 'unresolved', 'errors'] as $k) {
            $this->assertSame(0, $counts[$k], "{$k}: " . implode("\n", $lines));
        }
        $this->assertFalse(get_config('message', $key));
        $this->assertEquals($before, (array) get_config('message'));
    }

    /**
     * (v) An otherwise healthy provider (every new _locked key present, as on a
     * fresh install or after the first-cut key copy) that still has its legacy
     * _disable switch and a legacy user row: both are reported, both are carried
     * over, and nothing else is written.
     */
    public function test_healthy_provider_with_stranded_disable_and_user_row_is_repaired(): void {
        global $DB;
        $this->resetAfterTest();

        $name = $this->first_provider('local_sentientia_cart');
        $base = "local_sentientia_cart_{$name}";
        $oldbase = "local_airpay_cart_{$name}";
        $olduser = "message_provider_{$oldbase}_enabled";
        $newuser = "message_provider_{$base}_enabled";

        $this->assertSame([], message_pref_repair::check(), 'Precondition: a fresh install is healthy');
        unset_config("{$base}_disable", 'message');
        set_config("{$oldbase}_disable", '1', 'message');
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_preferences', (object) ['userid' => $user->id, 'name' => $olduser, 'value' => 'email']);

        $joined = implode("\n", message_pref_repair::check());
        $this->assertStringContainsString("{$oldbase}_disable", $joined);
        $this->assertStringContainsString("not carried to {$base}_disable", $joined);
        $this->assertStringContainsString("1 user choice(s) still under {$olduser}", $joined);

        $lines = [];
        $done = message_pref_repair::repair(true, self::collector($lines));
        $this->assertSame(1, $done['copied'], 'only _disable is copied: ' . implode("\n", $lines));
        $this->assertSame(1, $done['moved']);
        $this->assertSame(0, $done['shadowed']);
        $this->assertSame(0, $done['defaulted'], 'every lock was already there');
        $this->assertSame(0, $done['unresolved']);
        $this->assertSame(0, $done['errors']);

        $this->assertSame('1', get_config('message', "{$base}_disable"));
        $this->assertSame('1', get_config('message', "{$oldbase}_disable"), 'copy-only: the legacy key stays');
        $this->assertSame('email', $DB->get_field('user_preferences', 'value',
            ['userid' => $user->id, 'name' => $newuser]));
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $user->id, 'name' => $olduser]));

        $this->assertSame([], message_pref_repair::check());
        $again = message_pref_repair::repair(true, function (string $line): void {
        });
        $this->assertSame(0, $again['copied']);
        $this->assertSame(0, $again['moved']);
    }

    /**
     * (vi) local_sentientia_platform was local_airpay_core, not local_airpay_platform:
     * a repair copies from the local_airpay_core_* keys and writes nothing under
     * a local_airpay_platform name.
     */
    public function test_platform_provider_is_repaired_from_the_local_airpay_core_keys(): void {
        global $DB;
        $this->resetAfterTest();

        $procs = self::procs();
        // The platform declares no message provider of its own today, so add a row.
        $DB->insert_record('message_providers', (object) [
            'name' => 'zzplatformtest', 'component' => 'local_sentientia_platform', 'capability' => null]);
        $base = 'local_sentientia_platform_zzplatformtest';
        $oldbase = 'local_airpay_core_zzplatformtest';
        foreach ($procs as $proc) {
            set_config("{$proc}_provider_{$oldbase}_locked", $proc === 'email' ? '1' : '0', 'message');
        }
        set_config("message_provider_{$oldbase}_enabled", 'email', 'message');
        set_config("{$oldbase}_disable", '1', 'message');
        $this->assertNotEmpty(message_pref_repair::check());

        $lines = [];
        $done = message_pref_repair::repair(true, self::collector($lines));
        $this->assertSame(count($procs) + 2, $done['copied'], implode("\n", $lines)); // locks + _enabled + _disable
        $this->assertSame(0, $done['defaulted']);
        $this->assertSame(0, $done['unresolved']);
        $this->assertSame(0, $done['unmapped'], implode("\n", $lines));
        $this->assertSame(0, $done['errors']);

        foreach ($procs as $proc) {
            $this->assertSame($proc === 'email' ? '1' : '0',
                get_config('message', "{$proc}_provider_{$base}_locked"), "{$proc} lock");
            $this->assertFalse(get_config('message', "{$proc}_provider_local_airpay_platform_zzplatformtest_locked"),
                "{$proc}: nothing is written under a local_airpay_platform name");
        }
        $this->assertSame('email', get_config('message', "message_provider_{$base}_enabled"));
        $this->assertSame('1', get_config('message', "{$base}_disable"));
        $this->assertSame([], message_pref_repair::check());
    }

    /**
     * (vii) A local_airpay_* message key or user preference that matches no
     * provider is counted and REPORTed, and left exactly as it is.
     */
    public function test_unmapped_legacy_keys_are_reported_and_left_alone(): void {
        global $DB;
        $this->resetAfterTest();

        $lockkey = 'email_provider_local_airpay_zzgone_thing_locked';
        $prefname = 'message_provider_local_airpay_zzgone_thing_enabled';
        set_config($lockkey, '0', 'message');
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_preferences', (object) ['userid' => $user->id, 'name' => $prefname, 'value' => 'email']);

        $this->assertSame([], message_pref_repair::check(), 'report-only: not a defect check() fails on');

        foreach ([false, true] as $apply) {
            $lines = [];
            $counts = message_pref_repair::repair($apply, self::collector($lines));
            $text = implode("\n", $lines);
            $this->assertSame(2, $counts['unmapped'], $text);
            $this->assertStringContainsString($lockkey, $text);
            $this->assertStringContainsString($prefname, $text);
            foreach (['copied', 'moved', 'shadowed', 'defaulted', 'unresolved', 'errors'] as $k) {
                $this->assertSame(0, $counts[$k], "{$k}: {$text}");
            }
        }
        $this->assertSame('0', get_config('message', $lockkey));
        $this->assertTrue($DB->record_exists('user_preferences', ['userid' => $user->id, 'name' => $prefname]));
    }

    /**
     * (viii) Legacy locks but no legacy _enabled (the admin turned every
     * processor off, so core had unset it): the locks are copied and the new
     * _enabled stays absent.
     */
    public function test_legacy_locks_without_legacy_enabled_leave_enabled_unset(): void {
        $this->resetAfterTest();

        $procs = self::procs();
        $name = $this->first_provider('local_sentientia_cart');
        $base = "local_sentientia_cart_{$name}";
        $oldbase = "local_airpay_cart_{$name}";
        foreach ($procs as $proc) {
            unset_config("{$proc}_provider_{$base}_locked", 'message');
            set_config("{$proc}_provider_{$oldbase}_locked", '0', 'message');
        }
        unset_config("message_provider_{$base}_enabled", 'message');
        unset_config("message_provider_{$oldbase}_enabled", 'message');
        unset_config("{$base}_disable", 'message');
        $this->assertNotEmpty(message_pref_repair::check());

        $lines = [];
        $done = message_pref_repair::repair(true, self::collector($lines));
        $this->assertSame(count($procs), $done['copied'], implode("\n", $lines)); // the locks, nothing else
        $this->assertSame(0, $done['defaulted'], 'legacy locks cover every processor, so no file default is needed');
        $this->assertSame(0, $done['errors']);
        foreach ($procs as $proc) {
            $this->assertSame('0', get_config('message', "{$proc}_provider_{$base}_locked"), $proc);
        }
        $this->assertFalse(get_config('message', "message_provider_{$base}_enabled"),
            'no legacy _enabled, so no new _enabled is invented');
        $this->assertSame([], message_pref_repair::check());
    }

    /**
     * (ix) A provider row its component no longer declares cannot be repaired;
     * check() and repair() both say what to do about it.
     */
    public function test_a_stale_provider_row_gets_an_explicit_remedy(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->insert_record('message_providers', (object) [
            'name' => 'zzstalerow', 'component' => 'local_sentientia_cart', 'capability' => null]);

        $joined = implode("\n", message_pref_repair::check());
        $this->assertStringContainsString('local_sentientia_cart/zzstalerow', $joined);
        $this->assertStringContainsString('message_send() throws', $joined);
        $this->assertStringContainsString('stale provider row', $joined);
        $this->assertStringContainsString('{message_providers}', $joined);
        $this->assertStringContainsString('admin/cli/upgrade.php', $joined);

        $lines = [];
        $done = message_pref_repair::repair(true, self::collector($lines));
        $text = implode("\n", $lines);
        $this->assertSame(1, $done['unresolved'], $text);
        $this->assertSame(0, $done['defaulted']);
        $this->assertStringContainsString('no entry in db/messages.php', $text);
        $this->assertStringContainsString('stale provider row', $text);
        $this->assertNotEmpty(message_pref_repair::check(), 'repair cannot fix a stale row, so check() still fails');
    }

    /**
     * (x) A db/messages.php default written for a provider outside the
     * Sentientia plugins is tagged in the output; one of ours is not.
     */
    public function test_defaults_written_outside_sentientia_plugins_are_tagged(): void {
        global $DB;
        $this->resetAfterTest();

        $procs = self::procs();
        $this->assertTrue($DB->record_exists('message_providers', ['component' => 'moodle', 'name' => 'instantmessage']));
        $ours = $this->first_provider('local_sentientia_courses');
        foreach ($procs as $proc) {
            unset_config("{$proc}_provider_moodle_instantmessage_locked", 'message');
            unset_config("{$proc}_provider_local_sentientia_courses_{$ours}_locked", 'message');
        }

        $lines = [];
        message_pref_repair::repair(false, self::collector($lines));
        $text = implode("\n", $lines);
        $this->assertStringContainsString('would write defaults (non-Sentientia) moodle/instantmessage', $text);
        $this->assertStringContainsString("would write defaults local_sentientia_courses/{$ours}", $text);
        $this->assertStringNotContainsString('(non-Sentientia) local_sentientia_courses', $text);

        $lines = [];
        message_pref_repair::repair(true, self::collector($lines));
        $text = implode("\n", $lines);
        $this->assertStringContainsString('wrote defaults (non-Sentientia) moodle/instantmessage', $text);
        $this->assertStringContainsString("wrote defaults local_sentientia_courses/{$ours}", $text);
        $this->assertSame([], message_pref_repair::check());
    }

    /**
     * Legacy names: explicit map first, then local_airpay_<suffix>.
     */
    public function test_legacy_component_names(): void {
        $this->assertSame('local_airpay_core', message_pref_repair::legacy_component('local_sentientia_platform'));
        $this->assertNull(message_pref_repair::legacy_component('local_sentientia_core'));
        $this->assertSame('local_airpay_cart', message_pref_repair::legacy_component('local_sentientia_cart'));
        $this->assertNull(message_pref_repair::legacy_component('moodle'));
        $this->assertNull(message_pref_repair::legacy_component('mod_forum'));
    }
}
