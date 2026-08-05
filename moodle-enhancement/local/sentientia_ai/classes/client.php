<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Public facade of the Sentientia AI Gateway — the ONE entry point every
 * Sentientia AI feature calls instead of carrying its own Anthropic
 * client (ADR-028 Phase 2.3).
 *
 * Usage (consumer plugin):
 *
 *     $result = \local_sentientia_ai\client::complete([
 *         'component' => 'local_sentientia_aiquiz',   // required
 *         'purpose'   => 'quiz_generation',           // required slug
 *         'usertext'  => $usermessage,                // required
 *         'system'    => $systemprompt,               // optional
 *         'model'     => 'claude-sonnet-4-6',         // optional
 *         'max_tokens'=> 4096,                        // optional, hard-capped
 *         'mock'      => fn($req) => $mockbody,       // optional: the
 *             // component's own deterministic mock (callable or string).
 *             // Keeps component-specific mock fidelity (e.g. aiquiz's
 *             // Devanagari mock questions) while the gateway owns routing.
 *         'legacy_component' => 'local_sentientia_aiquiz', // optional:
 *             // pre-gateway api_key setting to fall back on.
 *     ]);
 *     // => ['body' =>, 'tokens_in' =>, 'tokens_out' =>,
 *     //     'mode' => 'mock'|'live'|'failed'|'denied',
 *     //     'error' => ?string, 'ledgerid' => int]
 *
 * Contract notes for consumers:
 *   - 'mode' semantics are IDENTICAL to the historical per-plugin
 *     clients, plus 'denied' (quota) — treat 'denied' like 'failed'
 *     (persist the failed attempt, tell the user, never retry-loop).
 *   - The gateway NEVER degrades a live-intent call to mock: when both
 *     live flags are ON, failures surface as failed/denied so fake
 *     content cannot impersonate a real generation.
 *   - Component-level gates (per-feature flags, [CONFIRM] UI, per-plugin
 *     caps — the ADR-012 layers) remain the CALLER's job on top of this.
 *
 * @package local_sentientia_ai
 */
class client {

    /**
     * Run a completion through the gateway.
     *
     * @param array $options See class docblock.
     * @return array {body, tokens_in, tokens_out, mode, error, ledgerid}
     * @throws \coding_exception on a malformed request (programming error,
     *         not a runtime condition — fail loudly in dev).
     */
    public static function complete(array $options): array {
        $req = self::normalise($options);
        return gateway::execute($req);
    }

    /**
     * True when a live call could currently succeed (both flags + a key).
     * Consumers use this for "Live API" badges; it deliberately does NOT
     * check quotas (headroom changes per-call; the badge shouldn't flap).
     *
     * @param string $legacycomponent Optional legacy api_key namespace.
     * @return bool
     */
    public static function is_live_ready(string $legacycomponent = ''): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return false;
        }
        try {
            $ff = '\\local_sentientia_platform\\feature_flags';
            if (!$ff::is_enabled(gateway::FLAG_GATEWAY) || !$ff::is_enabled(gateway::FLAG_LIVE)) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }
        $key = (string) get_config('local_sentientia_ai', 'api_key');
        if ($key === '' && $legacycomponent !== '') {
            $key = (string) get_config($legacycomponent, 'api_key');
        }
        return $key !== '';
    }

    /**
     * Validate + normalise the request array; resolve identity scoping.
     *
     * @param array $options
     * @return array
     * @throws \coding_exception
     */
    protected static function normalise(array $options): array {
        global $USER;

        foreach (['component', 'purpose', 'usertext'] as $required) {
            if (empty($options[$required]) || !is_string($options[$required])) {
                throw new \coding_exception(
                    "sentientia_ai client::complete() missing required '{$required}'");
            }
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $options['component'])) {
            throw new \coding_exception(
                'sentientia_ai component must be a frankenstyle name, got: '
                . clean_param($options['component'], PARAM_ALPHANUMEXT));
        }

        // Tenant via the ADR-019 seam when available; 0 (unscoped) otherwise.
        $tenantid = 0;
        if (class_exists('\\local_sentientia_core\\tenant_identity')) {
            try {
                $tenantid = (int) \local_sentientia_core\tenant_identity::root_for_current_user();
            } catch (\Throwable $e) {
                $tenantid = 0;
            }
        }

        // Customer via the platform layer (Phase 0: hardwired AIRPAY=1).
        $customerid = 1;
        if (class_exists('\\local_sentientia_platform\\customer')) {
            try {
                $customerid = (int) \local_sentientia_platform\customer::current();
            } catch (\Throwable $e) {
                $customerid = 1;
            }
        }

        return [
            'component'        => $options['component'],
            'purpose'          => substr(clean_param($options['purpose'], PARAM_ALPHANUMEXT), 0, 100),
            'usertext'         => (string) $options['usertext'],
            'system'           => isset($options['system']) ? (string) $options['system'] : '',
            'model'            => isset($options['model']) ? trim((string) $options['model']) : '',
            'max_tokens'       => isset($options['max_tokens'])
                                    ? (int) $options['max_tokens'] : gateway::DEFAULT_MAX_TOKENS,
            'mock'             => $options['mock'] ?? null,
            'legacy_component' => isset($options['legacy_component'])
                                    ? (string) $options['legacy_component'] : '',
            'userid'           => isset($options['userid'])
                                    ? (int) $options['userid'] : (int) ($USER->id ?? 0),
            'customerid'       => $customerid,
            'tenantid'         => $tenantid,
        ];
    }
}
