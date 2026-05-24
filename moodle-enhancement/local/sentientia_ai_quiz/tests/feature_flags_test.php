<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * PHPUnit — feature-flag, capability, language parity, and confirm-gate
 * coverage for the Phase G.1 scaffold.
 *
 * @package    local_sentientia_ai_quiz
 * @covers     \local_sentientia_ai_quiz\anthropic_client
 */
final class feature_flags_test extends \advanced_testcase {

    /**
     * Path to the plugin root — resolved once for path-based tests.
     */
    private static function plugin_root(): string {
        global $CFG;
        return $CFG->dirroot . '/local/sentientia_ai_quiz';
    }

    /**
     * The feature flag must be registered + default OFF. Direct read of
     * the db/feature_flags.php file — does not depend on local_airpay_core
     * being installed in the test environment.
     */
    public function test_feature_flag_registered_and_default_off(): void {
        $file = self::plugin_root() . '/db/feature_flags.php';
        $this->assertFileExists($file);

        $flags = [];
        require $file;

        $this->assertIsArray($flags);
        $this->assertArrayHasKey('sentientia_ai_quiz_enabled', $flags);
        $this->assertSame(false, $flags['sentientia_ai_quiz_enabled']['default'],
            'sentientia_ai_quiz_enabled MUST default OFF per CLAUDE.md section 13.');
        $this->assertNotEmpty($flags['sentientia_ai_quiz_enabled']['description']);
    }

    /**
     * The local_airpay_core resolver — when present — sees the flag and
     * returns the registered default. Skipped when local_airpay_core is
     * not installed (so the suite still runs in a stand-alone container).
     */
    public function test_resolver_sees_flag_default_when_core_installed(): void {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            $this->markTestSkipped('local_airpay_core not installed.');
        }
        $this->resetAfterTest();

        $registry = \local_airpay_core\feature_flags::load_registry();
        $this->assertArrayHasKey('sentientia_ai_quiz_enabled', $registry,
            'local_airpay_core registry must pick up the plugin flag.');
        $this->assertFalse((bool) $registry['sentientia_ai_quiz_enabled']['default']);

        $this->assertFalse(
            \local_airpay_core\feature_flags::is_enabled('sentientia_ai_quiz_enabled'),
            'Flag must resolve to false by default with no overrides set.'
        );
    }

    /**
     * Per-customer + per-tenant override path — when the core resolver
     * supports the 5-level precedence, we can flip the flag for a
     * specific (customer, tenant) pair and read it back.
     */
    public function test_per_customer_override_when_layer_enabled(): void {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            $this->markTestSkipped('local_airpay_core not installed.');
        }
        $this->resetAfterTest();

        // Enable the customer-layer gate.
        \local_airpay_core\feature_flags::set(
            \local_airpay_core\feature_flags::CUSTOMER_LEVEL_FLAG, 0, true);

        // Per-customer override: ON for customer 1, OFF (registry) elsewhere.
        \local_airpay_core\feature_flags::set('sentientia_ai_quiz_enabled', 0, true,
            null, '', 1);

        $this->assertTrue(\local_airpay_core\feature_flags::is_enabled_for(
            'sentientia_ai_quiz_enabled', 1, 0));
        $this->assertFalse(\local_airpay_core\feature_flags::is_enabled_for(
            'sentientia_ai_quiz_enabled', 0, 0));
    }

    /**
     * The plugin declares a single capability and it defaults to no
     * archetypes — admins explicitly grant once the live wiring lands.
     */
    public function test_capability_registered_and_default_deny(): void {
        $file = self::plugin_root() . '/db/access.php';
        $this->assertFileExists($file);

        $capabilities = [];
        require $file;

        $this->assertArrayHasKey('local/sentientia_ai_quiz:generate', $capabilities);
        $cap = $capabilities['local/sentientia_ai_quiz:generate'];
        $this->assertSame('write', $cap['captype']);
        $this->assertSame(CONTEXT_SYSTEM, $cap['contextlevel']);
        $this->assertSame([], $cap['archetypes'],
            'Phase G.1 scaffold ships with no archetype grants — default deny.');
    }

    /**
     * generate_quiz() throws confirm_required on every call in Phase G.1.
     * Sourcetext content is irrelevant; the throw must fire regardless.
     */
    public function test_anthropic_client_throws_confirm_required(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/confirm_required/i');

        $client = new anthropic_client();
        $client->generate_quiz('Some training material about AML protocols.');
    }

    /**
     * The default language is Hindi — task contract.
     */
    public function test_anthropic_client_default_lang_is_hindi(): void {
        $reflect = new \ReflectionMethod(anthropic_client::class, 'generate_quiz');
        $params = $reflect->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('lang', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertSame('hi', $params[1]->getDefaultValue());
    }

    /**
     * Unsupported language codes are rejected BEFORE the confirm gate so
     * callers see the validation error rather than the gate message.
     */
    public function test_anthropic_client_rejects_unsupported_lang(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/(error_invalid_lang|fr)/i');

        $client = new anthropic_client();
        $client->generate_quiz('Source text', 'fr');
    }

    /**
     * The prompt_hash helper returns a 64-char lowercase hex SHA-256.
     */
    public function test_prompt_hash_is_64_char_sha256(): void {
        $hash = anthropic_client::prompt_hash('template', 'source');
        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        // Determinism — identical inputs yield identical digests.
        $this->assertSame($hash, anthropic_client::prompt_hash('template', 'source'));
        // Different inputs yield different digests.
        $this->assertNotSame($hash,
            anthropic_client::prompt_hash('template', 'different source'));
    }

    /**
     * Hindi pack parity — every EN key MUST have an HI key. The drive-
     * enforced 100% Hindi parity rule (CLAUDE.md) is verified per chip.
     */
    public function test_lang_pack_parity_en_hi(): void {
        $en_strings = [];
        $hi_strings = [];

        // Both files declare $string[...] keys. Require them in
        // independent scopes so they don't clobber each other.
        $en_file = self::plugin_root() . '/lang/en/local_sentientia_ai_quiz.php';
        $hi_file = self::plugin_root() . '/lang/hi/local_sentientia_ai_quiz.php';
        $this->assertFileExists($en_file);
        $this->assertFileExists($hi_file);

        $load = function (string $path): array {
            $string = [];
            require $path;
            return $string;
        };
        $en_strings = $load($en_file);
        $hi_strings = $load($hi_file);

        $missing_in_hi = array_diff_key($en_strings, $hi_strings);
        $extra_in_hi   = array_diff_key($hi_strings, $en_strings);

        $this->assertSame([], $missing_in_hi,
            'Every EN string key must exist in the HI pack: missing keys = '
            . implode(', ', array_keys($missing_in_hi)));
        $this->assertSame([], $extra_in_hi,
            'HI pack must not introduce keys absent from EN: extras = '
            . implode(', ', array_keys($extra_in_hi)));
    }

    /**
     * The plugin requires a confirm_required string so moodle_exception
     * can render its message — verify the key exists in both packs.
     */
    public function test_confirm_required_string_exists_in_both_packs(): void {
        $load = function (string $path): array {
            $string = [];
            require $path;
            return $string;
        };
        $en = $load(self::plugin_root() . '/lang/en/local_sentientia_ai_quiz.php');
        $hi = $load(self::plugin_root() . '/lang/hi/local_sentientia_ai_quiz.php');

        $this->assertArrayHasKey('confirm_required', $en);
        $this->assertArrayHasKey('confirm_required', $hi);
        $this->assertNotEmpty($en['confirm_required']);
        $this->assertNotEmpty($hi['confirm_required']);
    }
}
