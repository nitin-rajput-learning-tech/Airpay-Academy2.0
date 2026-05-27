<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recommendations;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system + user prompts handed to Anthropic Claude for the
 * Sentientia LMS AI Course Recommendations feature.
 *
 * The prompts are versioned via {@see VERSION} so future changes can A/B
 * against the recorded baseline. Each recommendation row carries its
 * `prompt_version` column so we can reproduce exactly what Claude saw.
 *
 * Output contract — Claude must reply with a JSON object of shape:
 *
 *     {
 *       "recommendations": [
 *         {
 *           "course_id": 42,
 *           "score": 87,
 *           "reasoning": "Builds directly on the AML basics this learner just completed."
 *         },
 *         ...
 *       ]
 *     }
 *
 * The recommendation_engine validates this shape strictly. Anything
 * malformed is dropped (logged at debugging level) — we never approximate.
 *
 * Phase H.0 only ships the 'v1' prompt. Phase H.1 introduces 'v2-hindi'
 * which asks for Hindi-language reasoning strings; H.2 introduces
 * 'v2-cohort' which adds cohort completion patterns to the context.
 *
 * @package local_sentientia_recommendations
 */
class prompt_builder {

    /** Current prompt version. Bump when wording materially changes. */
    public const VERSION = 'v1';

    /** Per-call upper bound on requested recommendations. */
    public const MAX_RECOMMENDATIONS = 10;

    /** Per-call lower bound on requested recommendations. */
    public const MIN_RECOMMENDATIONS = 1;

    /** Hard cap on completion-history entries fed into the prompt. */
    public const MAX_HISTORY_ITEMS = 50;

    /** Hard cap on candidate-course entries fed into the prompt. */
    public const MAX_CANDIDATE_COURSES = 100;

    /**
     * Build the system prompt that conditions Claude to return strict JSON.
     *
     * @return string
     */
    public static function build_system_prompt(): string {
        return <<<PROMPT
You are an expert L&D recommendation engine for a corporate compliance + skills LMS used by 3,500+ employees at a fintech in India.

Your job is to read a learner's profile (role, recent completions, current skills) and a list of candidate courses, then recommend the top N courses that are the best next step for THIS learner.

RULES (non-negotiable):
1. Output ONLY a single JSON object. No prose before or after. No markdown fences.
2. The JSON object MUST have exactly one top-level key: "recommendations" — an array.
3. Each item in "recommendations" is an object with EXACTLY these keys:
     - "course_id": an integer matching one of the candidate course IDs given in the user message
     - "score": an integer 0..100 representing your confidence this course is a good next step
     - "reasoning": one short sentence (max 200 chars) explaining why this course is the right next step for this learner, in plain English
4. Recommendations MUST refer to course IDs that appear in the candidate list. Do not invent course IDs.
5. Do NOT recommend a course the learner has already completed (it will be in the "completed" list).
6. Recommendations MUST NOT contain personally identifiable information (employee names, ID numbers, salary, customer data) in the reasoning, even if it appears in the profile.
7. Order recommendations from MOST relevant (highest score) to LEAST.
8. Language: English. Use formal corporate register. Indian English spelling is acceptable.

If the candidate list is too short or the learner profile is too thin to produce N quality recommendations, return fewer — never invent.
PROMPT;
    }

    /**
     * Build the user message for a recommendation request.
     *
     * @param \stdClass $profile      Learner profile: ->role, ->skills (array), ->completed (array of course ids), ->tenant
     * @param array     $candidates   Candidate courses: array of objects with ->id, ->fullname, ->shortname, ->summary
     * @param int       $numrequested 1..MAX_RECOMMENDATIONS
     * @return string
     */
    public static function build_user_message(\stdClass $profile, array $candidates, int $numrequested): string {
        $numrequested = max(self::MIN_RECOMMENDATIONS, min(self::MAX_RECOMMENDATIONS, $numrequested));

        $role        = isset($profile->role)    ? (string)$profile->role    : 'learner';
        $tenant      = isset($profile->tenant)  ? (string)$profile->tenant  : 'unknown';
        $skills      = isset($profile->skills)    && is_array($profile->skills)    ? $profile->skills    : [];
        $completed   = isset($profile->completed) && is_array($profile->completed) ? $profile->completed : [];

        // Cap completion history.
        if (count($completed) > self::MAX_HISTORY_ITEMS) {
            $completed = array_slice($completed, 0, self::MAX_HISTORY_ITEMS);
        }
        // Cap candidate list.
        if (count($candidates) > self::MAX_CANDIDATE_COURSES) {
            $candidates = array_slice($candidates, 0, self::MAX_CANDIDATE_COURSES);
        }

        $skillstr = empty($skills)    ? '(none on file)' : implode(', ', array_map('strval', $skills));
        $compstr  = empty($completed) ? '(none)'         : implode(', ', array_map('intval', $completed));

        $catalog = "----- CANDIDATE COURSES -----\n";
        foreach ($candidates as $c) {
            $id    = isset($c->id)        ? (int)$c->id            : 0;
            $name  = isset($c->fullname)  ? (string)$c->fullname   : '';
            $short = isset($c->shortname) ? (string)$c->shortname  : '';
            $sum   = isset($c->summary)   ? (string)$c->summary    : '';
            // Trim summary to a reasonable size.
            if (strlen($sum) > 300) {
                $sum = substr($sum, 0, 300) . '...';
            }
            $catalog .= "course_id={$id} | {$short} | {$name} | summary: {$sum}\n";
        }
        $catalog .= "----- END CANDIDATES -----\n";

        return "Generate exactly {$numrequested} course recommendations for the following learner. "
            . "Return only the JSON object as specified.\n\n"
            . "----- LEARNER PROFILE -----\n"
            . "role: {$role}\n"
            . "tenant: {$tenant}\n"
            . "current skills: {$skillstr}\n"
            . "completed course ids: {$compstr}\n"
            . "----- END PROFILE -----\n\n"
            . $catalog;
    }

    /**
     * Validate inputs to a recommendation request. Returns an array of
     * problem KEYS (empty array = clean) — the caller looks them up via
     * get_string() so the validator is i18n-friendly.
     *
     * @param \stdClass $profile
     * @param array     $candidates
     * @param int       $numrequested
     * @return string[]
     */
    public static function validate_request(\stdClass $profile, array $candidates, int $numrequested): array {
        $errors = [];

        if (empty($candidates)) {
            $errors[] = 'err_candidates_empty';
        }

        if ($numrequested < self::MIN_RECOMMENDATIONS || $numrequested > self::MAX_RECOMMENDATIONS) {
            $errors[] = 'err_invalid_count';
        }

        // Profile must at minimum exist (an empty stdClass is OK — Claude
        // will just return generic onboarding recommendations).
        if (!is_object($profile)) {
            $errors[] = 'err_profile_invalid';
        }

        if (self::profile_contains_pii_pattern($profile)) {
            $errors[] = 'err_profile_contains_pii';
        }

        return $errors;
    }

    /**
     * Heuristic PII detector for the learner profile object. Catches
     * Aadhaar + PAN patterns inside skill strings, role names, or any
     * other free-text field on the profile.
     *
     * @param \stdClass $profile
     * @return bool
     */
    public static function profile_contains_pii_pattern(\stdClass $profile): bool {
        $strings = [];
        foreach (get_object_vars($profile) as $val) {
            if (is_string($val)) {
                $strings[] = $val;
            } else if (is_array($val)) {
                foreach ($val as $v) {
                    if (is_string($v)) {
                        $strings[] = $v;
                    }
                }
            }
        }
        $joined = implode(' ', $strings);
        if (preg_match('/\b\d{4}\s?\d{4}\s?\d{4}\b/', $joined)) {
            return true;
        }
        if (preg_match('/\b[A-Z]{5}\d{4}[A-Z]\b/', $joined)) {
            return true;
        }
        return false;
    }
}
