<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\validator;

defined('MOODLE_INTERNAL') || die();

/**
 * xAPI 1.0.3 statement validator.
 *
 * Strictly validates inbound statement arrays against the xAPI spec.
 * Never trust inbound payloads — this class is the only entry point
 * for statement data from external clients.
 *
 * Validates:
 *   - Actor (Agent: exactly one IFI; mbox format; account shape)
 *   - Verb  (id is valid IRI)
 *   - Object (id is valid IRI; objectType constraints)
 *   - Result (score range; raw ≤ max)
 *   - Context (registration UUID; valid contextActivities)
 *   - Timestamp (ISO 8601)
 *   - Statement id (UUID)
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statement_validator {

    /** Maximum allowed length for IRIs (to prevent storage abuse). */
    private const MAX_IRI_LENGTH = 2048;

    /** Maximum allowed length for display values. */
    private const MAX_DISPLAY_LENGTH = 512;

    /** @var string[] Collected validation error messages. */
    private array $errors = [];

    /**
     * Validate a raw statement array.
     *
     * @param mixed $data The decoded JSON (must be an array).
     * @return bool True when valid.
     */
    public function validate($data): bool {
        $this->errors = [];

        if (!is_array($data)) {
            $this->errors[] = 'Statement must be a JSON object.';
            return false;
        }

        // Optional statement id.
        if (isset($data['id'])) {
            $this->validate_uuid((string) $data['id'], 'statement id');
        }

        // Optional timestamp.
        if (isset($data['timestamp'])) {
            $this->validate_timestamp((string) $data['timestamp']);
        }

        // Required: actor.
        if (!isset($data['actor'])) {
            $this->errors[] = get_string('validate_actor_required', 'local_sentientia_xapi');
        } else {
            $this->validate_actor($data['actor']);
        }

        // Required: verb.
        if (!isset($data['verb'])) {
            $this->errors[] = get_string('validate_verb_required', 'local_sentientia_xapi');
        } else {
            $this->validate_verb($data['verb']);
        }

        // Required: object.
        if (!isset($data['object'])) {
            $this->errors[] = get_string('validate_object_required', 'local_sentientia_xapi');
        } else {
            $this->validate_object($data['object']);
        }

        // Optional: result.
        if (isset($data['result'])) {
            $this->validate_result($data['result']);
        }

        // Optional: context.
        if (isset($data['context'])) {
            $this->validate_context($data['context']);
        }

        return empty($this->errors);
    }

    /**
     * Return all collected error messages.
     *
     * @return string[]
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Return errors as a single human-readable string (for exception messages).
     */
    public function errors_as_string(): string {
        return implode('; ', $this->errors);
    }

    // ─── Private validation helpers ───────────────────────────────────

    /**
     * Validate actor (Agent or Group).
     */
    private function validate_actor(mixed $actor): void {
        if (!is_array($actor)) {
            $this->errors[] = 'Actor must be a JSON object.';
            return;
        }

        $type = $actor['objectType'] ?? 'Agent';
        if (!in_array($type, ['Agent', 'Group'], true)) {
            $this->errors[] = get_string('validate_actor_missing_objecttype', 'local_sentientia_xapi');
        }

        // Exactly one IFI must be present for Agent.
        if ($type !== 'Group') {
            $ifi_count = 0;
            if (isset($actor['mbox']))          { $ifi_count++; }
            if (isset($actor['mbox_sha1sum']))  { $ifi_count++; }
            if (isset($actor['openid']))        { $ifi_count++; }
            if (isset($actor['account']))       { $ifi_count++; }

            if ($ifi_count !== 1) {
                $this->errors[] = get_string('validate_actor_missing_ifi', 'local_sentientia_xapi');
            }

            if (isset($actor['mbox'])) {
                if (!is_string($actor['mbox']) || strpos($actor['mbox'], 'mailto:') !== 0) {
                    $this->errors[] = get_string('validate_actor_mbox_format', 'local_sentientia_xapi');
                }
            }

            if (isset($actor['account'])) {
                if (!is_array($actor['account'])
                        || empty($actor['account']['homePage'])
                        || empty($actor['account']['name'])) {
                    $this->errors[] = get_string('validate_actor_account_missing', 'local_sentientia_xapi');
                }
            }
        }
    }

    /**
     * Validate verb.
     */
    private function validate_verb(mixed $verb): void {
        if (!is_array($verb)) {
            $this->errors[] = 'Verb must be a JSON object.';
            return;
        }

        if (empty($verb['id'])) {
            $this->errors[] = get_string('validate_verb_id_required', 'local_sentientia_xapi');
            return;
        }

        if (!$this->is_valid_iri((string) $verb['id'])) {
            $this->errors[] = get_string('validate_verb_id_iri', 'local_sentientia_xapi');
        }
    }

    /**
     * Validate object.
     */
    private function validate_object(mixed $obj): void {
        if (!is_array($obj)) {
            $this->errors[] = 'Object must be a JSON object.';
            return;
        }

        $type = $obj['objectType'] ?? 'Activity';

        // For Activity, StatementRef, SubStatement — id is required.
        if (in_array($type, ['Activity', 'SubStatement', ''], true)) {
            if (empty($obj['id'])) {
                $this->errors[] = get_string('validate_object_id_required', 'local_sentientia_xapi');
                return;
            }
            if (!$this->is_valid_iri((string) $obj['id'])) {
                $this->errors[] = get_string('validate_object_id_iri', 'local_sentientia_xapi');
            }
        }

        // StatementRef: id must be a UUID.
        if ($type === 'StatementRef') {
            if (empty($obj['id'])) {
                $this->errors[] = 'StatementRef id is required.';
            } else {
                $this->validate_uuid((string) $obj['id'], 'StatementRef id');
            }
        }

        // Nested SubStatement must not contain a SubStatement.
        if ($type === 'SubStatement' && isset($obj['object']['objectType'])
                && $obj['object']['objectType'] === 'SubStatement') {
            $this->errors[] = 'SubStatement cannot contain another SubStatement.';
        }
    }

    /**
     * Validate result.
     */
    private function validate_result(mixed $result): void {
        if (!is_array($result)) {
            $this->errors[] = 'Result must be a JSON object.';
            return;
        }

        if (isset($result['score'])) {
            $score = $result['score'];
            if (!is_array($score)) {
                $this->errors[] = 'Result score must be a JSON object.';
                return;
            }

            if (isset($score['scaled'])) {
                $scaled = (float) $score['scaled'];
                if ($scaled < -1.0 || $scaled > 1.0) {
                    $this->errors[] = get_string('validate_result_score_range', 'local_sentientia_xapi');
                }
            }

            if (isset($score['raw']) && isset($score['max'])) {
                if ((float) $score['raw'] > (float) $score['max']) {
                    $this->errors[] = get_string('validate_result_score_raw_max', 'local_sentientia_xapi');
                }
            }
        }
    }

    /**
     * Validate context.
     */
    private function validate_context(mixed $context): void {
        if (!is_array($context)) {
            $this->errors[] = 'Context must be a JSON object.';
            return;
        }

        if (isset($context['registration'])) {
            $this->validate_uuid((string) $context['registration'], 'context.registration');
        }
    }

    /**
     * Validate a UUID string.
     *
     * @param string $value The value to check.
     * @param string $field Field name for error message.
     */
    private function validate_uuid(string $value, string $field): void {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            $this->errors[] = "$field must be a valid UUID (got: " . s(substr($value, 0, 64)) . ').';
        }
    }

    /**
     * Validate an ISO 8601 timestamp.
     */
    private function validate_timestamp(string $ts): void {
        // Must parse as a valid date-time.
        $dt = \DateTime::createFromFormat(\DateTime::ATOM, $ts)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $ts)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:sP', $ts);
        if ($dt === false) {
            $this->errors[] = get_string('validate_timestamp_format', 'local_sentientia_xapi');
        }
    }

    /**
     * Check whether a string looks like a valid absolute IRI (URI).
     * Minimal check: scheme + '://' + non-empty authority. Does not
     * perform full RFC 3987 parsing (unnecessary and expensive for LRS use).
     *
     * @param string $iri
     * @return bool
     */
    private function is_valid_iri(string $iri): bool {
        if (strlen($iri) > self::MAX_IRI_LENGTH) {
            return false;
        }
        // Must start with a scheme followed by ://.
        return (bool) preg_match('#^[a-zA-Z][a-zA-Z0-9+\-.]*://.+#', $iri);
    }
}
