<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * A PROPOSED tool call — exactly the shape the LLM (or the mock client)
 * hands back to the platform.
 *
 * SECURITY: every field here is UNTRUSTED. The LLM picked the tool name
 * and arguments; the platform is responsible for verifying the name maps
 * to a registered tool, casting + validating each argument, checking the
 * capability and tenant scope, and only THEN executing. A tool_call is a
 * request, never an authorisation.
 *
 * @package local_sentientia_assistant
 */
class tool_call {

    /** @var string Tool name proposed by the LLM (untrusted). */
    public string $tool;

    /** @var array Raw proposed arguments (untrusted; each tool validates its own). */
    public array $args;

    /** @var string Where the proposal came from: 'llm' or 'mock'. */
    public string $proposedby;

    /** @var string Optional assistant-authored explanation shown to the learner before confirm. */
    public string $rationale;

    /**
     * @param string $tool       Untrusted tool name.
     * @param array  $args        Untrusted argument map.
     * @param string $proposedby 'llm' | 'mock'.
     * @param string $rationale  Optional natural-language rationale (untrusted text).
     */
    public function __construct(string $tool, array $args = [], string $proposedby = 'mock', string $rationale = '') {
        $this->tool = $tool;
        $this->args = $args;
        $this->proposedby = $proposedby;
        $this->rationale = $rationale;
    }

    /**
     * Build from a decoded JSON object (LLM output). Defensive: tolerates
     * missing / wrong-typed fields, coercing to safe empties. NEVER trusts
     * the shape.
     *
     * @param array  $decoded    Decoded JSON associative array.
     * @param string $proposedby 'llm' | 'mock'.
     * @return self|null Null when no usable tool name is present (chat-only turn).
     */
    public static function from_decoded(array $decoded, string $proposedby): ?self {
        $tool = isset($decoded['tool']) && is_string($decoded['tool']) ? trim($decoded['tool']) : '';
        if ($tool === '') {
            return null;
        }
        $args = (isset($decoded['args']) && is_array($decoded['args'])) ? $decoded['args'] : [];
        $rationale = (isset($decoded['rationale']) && is_string($decoded['rationale']))
            ? $decoded['rationale'] : '';
        return new self($tool, $args, $proposedby, $rationale);
    }
}
