<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_ai_quiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Anthropic client — Phase G.1 SCAFFOLD ONLY.
 *
 * generate_quiz() unconditionally throws \moodle_exception('confirm_required').
 * No live POST to api.anthropic.com is wired up. The class exists so the
 * downstream chips (live wiring, response parser, draft persistence) can
 * land additively against a stable signature.
 *
 * Contract for the live-wiring chip:
 *
 *   - Resolve the prompt template from local_sentientia_ai_quiz |
 *     prompt_template, then apply per-customer overrides via
 *     local_airpay_core's customer-config hook (planned API:
 *     local_airpay_core\customer::get_customer_config()). The Phase G.1
 *     scaffold does not call that hook yet — it does not exist on master.
 *
 *   - The API key is read from $CFG (PHP-side .env loader) at call
 *     time. NEVER persist it in $DB, NEVER log it, NEVER include it in
 *     error_detail.
 *
 *   - Every call must pass the per-user-action [CONFIRM] gate at the
 *     UI layer before reaching this class. This client treats that
 *     gate as a caller invariant; it does not re-check.
 *
 *   - Every call records one row in {local_sentientia_ai_quiz_log}
 *     (success or failure). prompt_hash = sha256(prompt_template ||
 *     source_text) so two identical requests collide for dedup
 *     analytics; source_text is never persisted.
 *
 * @package local_sentientia_ai_quiz
 */
class anthropic_client {

    /** Default Anthropic model — overridable per-customer in future chip. */
    public const DEFAULT_MODEL = 'claude-sonnet-4-6';

    /** Supported language codes for generate_quiz(). */
    public const SUPPORTED_LANGS = ['en', 'hi'];

    /**
     * Generate a quiz from source text.
     *
     * Phase G.1 SCAFFOLD: unconditionally throws confirm_required. The
     * downstream live-wiring chip replaces the throw with the real call
     * site (per-customer prompt resolution + API POST + log insert +
     * response parsing) gated behind a fresh per-call [CONFIRM] gate.
     *
     * Returns shape (live-wiring chip contract — not produced today):
     *   [
     *     'questions'    => array<int, object{qtype, qtext, qoptions, qanswer_index, qexplanation}>,
     *     'model'        => string,
     *     'lang'         => string,
     *     'tokens_in'    => int,
     *     'tokens_out'   => int,
     *     'prompt_hash'  => string  // 64 hex chars
     *   ]
     *
     * @param string $sourcetext Trainer-supplied source content.
     * @param string $lang       Target language code. Default 'hi'.
     * @return array See contract above.
     *
     * @throws \moodle_exception confirm_required — every call in Phase G.1.
     */
    public function generate_quiz(string $sourcetext, string $lang = 'hi'): array {
        // Defensive: validate the language code before throwing the
        // confirm gate so callers passing a bogus code see the
        // immediate validation error rather than the gate message.
        if (!in_array($lang, self::SUPPORTED_LANGS, true)) {
            throw new \moodle_exception('error_invalid_lang',
                'local_sentientia_ai_quiz', '', $lang);
        }

        // Phase G.1 scaffold — live wiring is a separate chip. No POST
        // happens until that chip ships with its own [CONFIRM] gate.
        // Suppress the unused-param notice: the signature is the
        // contract, the live chip will use both.
        unset($sourcetext);

        throw new \moodle_exception('confirm_required',
            'local_sentientia_ai_quiz');
    }

    /**
     * Compute the deterministic prompt-hash that the log table stores.
     *
     * Exposed as a public helper so the live-wiring chip and the cost
     * analytics dashboard can compute the same digest without re-deriving
     * it. SHA-256 hex digest of the prompt template concatenated with
     * the source text. 64 lowercase hex chars.
     *
     * @param string $prompt_template
     * @param string $sourcetext
     * @return string 64-char hex digest.
     */
    public static function prompt_hash(string $prompt_template, string $sourcetext): string {
        return hash('sha256', $prompt_template . $sourcetext);
    }
}
