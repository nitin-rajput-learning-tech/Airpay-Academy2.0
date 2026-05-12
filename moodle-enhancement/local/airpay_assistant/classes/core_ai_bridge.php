<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_assistant;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridge between airpay_assistant and Moodle 5's core_ai subsystem.
 *
 * Why a bridge: the existing ai_client.php in this plugin talks directly
 * to the Claude API with our key. The core_ai subsystem (Moodle 5) lets
 * admins pick from multiple providers (Anthropic / OpenAI / Azure /
 * Gemini) and handles user-policy acceptance + rate limiting centrally.
 *
 * This bridge wraps core_ai actions so airpay_assistant features can use
 * either backend transparently. Admins control the choice via
 * /local/airpay_assistant/settings.php (provider toggle).
 *
 * Three high-value actions implemented:
 *   - summarise_course(courseid)
 *   - generate_quiz_question(topic, difficulty)
 *   - translate_text(text, target_language)
 *
 * Phase 6 F.6 (2026-05-11).
 *
 * @package local_airpay_assistant
 */
class core_ai_bridge {

    /**
     * Is the core_ai subsystem available + an admin-enabled provider exists?
     */
    public static function is_available(): bool {
        if (!class_exists('\\core_ai\\manager')) {
            return false;  // Moodle < 5
        }
        try {
            $manager = \core\di::get(\core_ai\manager::class);
            $providers = $manager->get_providers_for_actions(
                [\core_ai\aiactions\generate_text::class], true);
            return !empty($providers);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Summarise a course's description + topic content into a TL;DR.
     *
     * Useful on the catalog tile + email previews. Output is plain text,
     * 3-5 sentences max.
     */
    public static function summarise_course(int $courseid): string {
        global $DB;
        if (!self::is_available()) {
            return '';
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        // Concatenate description + first 3 section summaries for context.
        $text = format_string($course->fullname) . "\n\n"
              . format_text($course->summary ?? '', $course->summaryformat ?? FORMAT_HTML);

        $sections = $DB->get_records('course_sections',
            ['course' => $courseid], 'section ASC', 'name, summary', 0, 3);
        foreach ($sections as $s) {
            $text .= "\n" . ($s->name ?: '') . ': '
                  . strip_tags($s->summary ?? '');
        }

        // Bound input to keep tokens reasonable.
        $text = substr($text, 0, 8000);
        return self::run_generate_text(
            "Summarise the following Airpay Academy course in 3-5 plain "
            . "English sentences for a learner deciding whether to enrol. "
            . "Focus on what they will learn and who it's for. "
            . "Don't repeat the course title.\n\n" . $text);
    }

    /**
     * Generate a multiple-choice quiz question on a topic.
     *
     * Returns a stdClass with:
     *   ->question   (string)
     *   ->options    (array of 4 strings)
     *   ->correct    (int — 0-indexed)
     *   ->explanation (string)
     */
    public static function generate_quiz_question(string $topic,
                                                   string $difficulty = 'medium'): \stdClass {
        if (!self::is_available()) {
            return (object) ['question' => '', 'options' => [],
                             'correct' => 0, 'explanation' => ''];
        }

        $prompt = "Generate a multiple-choice question on '$topic' at "
            . "$difficulty difficulty for Airpay employees. "
            . "Output as JSON with keys: question, options (array of 4), "
            . "correct (0-3 index), explanation. "
            . "Only JSON, no surrounding prose.";

        $raw = self::run_generate_text($prompt);
        // Try to extract JSON. AI sometimes wraps in code fence.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['question'])) {
            return (object) ['question' => '', 'options' => [],
                             'correct' => 0, 'explanation' => 'AI returned malformed output'];
        }
        return (object) [
            'question'    => (string) ($data['question'] ?? ''),
            'options'     => array_values((array) ($data['options'] ?? [])),
            'correct'     => (int) ($data['correct'] ?? 0),
            'explanation' => (string) ($data['explanation'] ?? ''),
        ];
    }

    /**
     * Translate text to a target language. Returns plain text.
     *
     * Used by airpay_emails to send mailers to non-English-speaking learners
     * in the Public tenant.
     */
    public static function translate_text(string $text, string $target_lang): string {
        if (!self::is_available()) return '';
        $text = substr($text, 0, 6000);
        return self::run_generate_text(
            "Translate the following English text to $target_lang. "
            . "Preserve formatting + punctuation. Return only the translation:\n\n"
            . $text);
    }

    /**
     * Run a generate_text action through the core_ai manager.
     *
     * Returns the generated text, or empty string on failure.
     */
    private static function run_generate_text(string $prompt): string {
        global $USER;
        try {
            $manager  = \core\di::get(\core_ai\manager::class);
            $context  = \context_system::instance();
            $action   = new \core_ai\aiactions\generate_text(
                contextid: $context->id,
                userid:    (int) $USER->id,
                prompttext: $prompt);

            // Check policy + action availability.
            if (!$manager->is_action_available(\core_ai\aiactions\generate_text::class)) {
                return '';
            }
            if (!\core_ai\manager::user_policy_accepted((int) $USER->id, $context->id)) {
                return '';  // user hasn't accepted AI usage policy
            }

            $response = $manager->process_action($action);
            $data = $response->get_response_data();
            return (string) ($data['generatedcontent'] ?? '');
        } catch (\Throwable $e) {
            debugging('core_ai_bridge::run_generate_text: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return '';
        }
    }
}
