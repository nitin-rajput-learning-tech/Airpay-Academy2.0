<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi\model;

defined('MOODLE_INTERNAL') || die();

/**
 * xAPI Statement model.
 *
 * Represents a single xAPI 1.0.3 statement. Provides a typed builder
 * that constructs the JSON structure required by the spec and understood
 * by the validator and LRS store.
 *
 * xAPI spec reference: https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md
 *
 * Usage:
 *   $stmt = statement::build_course_completed($user, $course, $courseid);
 *   $lrs  = new \local_sentientia_xapi\lrs\store();
 *   $lrs->put($stmt);
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statement {

    // ─── Common ADL verb IRIs ─────────────────────────────────────────
    public const VERB_COMPLETED   = 'http://adlnet.gov/expapi/verbs/completed';
    public const VERB_EXPERIENCED = 'http://adlnet.gov/expapi/verbs/experienced';
    public const VERB_PASSED      = 'http://adlnet.gov/expapi/verbs/passed';
    public const VERB_FAILED      = 'http://adlnet.gov/expapi/verbs/failed';
    public const VERB_ATTEMPTED   = 'http://adlnet.gov/expapi/verbs/attempted';
    public const VERB_ANSWERED    = 'http://adlnet.gov/expapi/verbs/answered';

    // cmi5 verb IRIs (https://github.com/AICC/CMI-5_Spec_Current/blob/quartz/cmi5_spec.md)
    public const VERB_LAUNCHED     = 'http://adlnet.gov/expapi/verbs/launched';
    public const VERB_INITIALIZED  = 'http://adlnet.gov/expapi/verbs/initialized';
    public const VERB_TERMINATED   = 'http://adlnet.gov/expapi/verbs/terminated';
    public const VERB_SUSPENDED    = 'http://adlnet.gov/expapi/verbs/suspended';
    public const VERB_RESUMED      = 'http://adlnet.gov/expapi/verbs/resumed';
    public const VERB_SATISFIED    = 'https://w3id.org/xapi/adl/verbs/satisfied';
    public const VERB_WAIVED       = 'https://w3id.org/xapi/adl/verbs/waived';
    public const VERB_ABANDONED    = 'https://w3id.org/xapi/adl/verbs/abandoned';

    // Common activity type IRIs.
    public const TYPE_COURSE   = 'http://adlnet.gov/expapi/activities/course';
    public const TYPE_QUIZ     = 'http://adlnet.gov/expapi/activities/assessment';
    public const TYPE_MODULE   = 'http://adlnet.gov/expapi/activities/module';
    public const TYPE_PLATFORM = 'http://adlnet.gov/expapi/activities/system';

    /** @var array Raw statement array (maps directly to JSON). */
    private array $data;

    /**
     * Construct from a raw array (caller is responsible for validity).
     * Use the static factory methods below for safe construction.
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /** Return the raw array (for JSON serialisation / DB storage). */
    public function to_array(): array {
        return $this->data;
    }

    /** Return as JSON string. */
    public function to_json(): string {
        return json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Get the statement UUID (may be null if not yet assigned). */
    public function get_id(): ?string {
        return $this->data['id'] ?? null;
    }

    /** Assign a UUID to the statement (called by the LRS store if absent). */
    public function set_id(string $uuid): void {
        $this->data['id'] = $uuid;
    }

    /** Get the verb IRI. */
    public function get_verb_id(): string {
        return $this->data['verb']['id'] ?? '';
    }

    /** Get object id (IRI). */
    public function get_object_id(): string {
        return $this->data['object']['id'] ?? '';
    }

    /** Get context.registration UUID (cmi5 session UUID). */
    public function get_registration(): ?string {
        return $this->data['context']['registration'] ?? null;
    }

    // ─── Factory methods ──────────────────────────────────────────────

    /**
     * Build an actor Agent from a Moodle user object.
     *
     * Uses the account IFI with the platform's home page so the actor
     * can be resolved back to a Moodle user without exposing the raw
     * email (xAPI privacy best practice).
     */
    public static function build_actor(\stdClass $user, string $platform_url): array {
        return [
            'objectType' => 'Agent',
            'name'       => format_string($user->firstname . ' ' . $user->lastname),
            'account'    => [
                'homePage' => rtrim($platform_url, '/'),
                'name'     => (string) $user->id,
            ],
        ];
    }

    /**
     * Build a verb object.
     *
     * @param string $iri     The verb IRI.
     * @param string $display The English display label.
     */
    public static function build_verb(string $iri, string $display): array {
        return [
            'id'      => $iri,
            'display' => ['en-US' => $display],
        ];
    }

    /**
     * Build an activity object (the most common xAPI object type).
     *
     * @param string $iri      Activity IRI.
     * @param string $name     Activity name (English).
     * @param string $type_iri Activity type IRI.
     */
    public static function build_activity(string $iri, string $name, string $type_iri = ''): array {
        $obj = [
            'objectType' => 'Activity',
            'id'         => $iri,
            'definition' => [
                'name' => ['en-US' => $name],
            ],
        ];
        if ($type_iri !== '') {
            $obj['definition']['type'] = $type_iri;
        }
        return $obj;
    }

    /**
     * Build a complete xAPI statement for course completion.
     *
     * @param \stdClass $user     Moodle user.
     * @param \stdClass $course   Moodle course record (id, fullname).
     * @param string    $wwwroot  Site wwwroot (used to build activity IRI).
     */
    public static function build_course_completed(
        \stdClass $user,
        \stdClass $course,
        string $wwwroot
    ): self {
        $platform_url = rtrim($wwwroot, '/');
        $course_iri   = $platform_url . '/course/view.php?id=' . (int) $course->id;

        return new self([
            'id'        => self::generate_uuid(),
            'actor'     => self::build_actor($user, $platform_url),
            'verb'      => self::build_verb(self::VERB_COMPLETED, 'completed'),
            'object'    => self::build_activity($course_iri, format_string($course->fullname), self::TYPE_COURSE),
            'result'    => ['completion' => true, 'success' => true],
            'context'   => self::build_platform_context($platform_url),
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Build an xAPI statement for a quiz attempt result.
     *
     * @param \stdClass $user     Moodle user.
     * @param \stdClass $quiz     Quiz record (id, name, course).
     * @param float     $score_raw  Raw score achieved.
     * @param float     $score_max  Maximum possible raw score.
     * @param bool      $passed   True if grade >= pass mark.
     * @param string    $wwwroot  Site wwwroot.
     */
    public static function build_quiz_submitted(
        \stdClass $user,
        \stdClass $quiz,
        float $score_raw,
        float $score_max,
        bool $passed,
        string $wwwroot
    ): self {
        $platform_url = rtrim($wwwroot, '/');
        $quiz_iri     = $platform_url . '/mod/quiz/view.php?id=' . (int) $quiz->id;

        $score_scaled = $score_max > 0 ? round($score_raw / $score_max, 4) : 0;
        $verb_iri     = $passed ? self::VERB_PASSED : self::VERB_FAILED;
        $verb_display = $passed ? 'passed' : 'failed';

        return new self([
            'id'     => self::generate_uuid(),
            'actor'  => self::build_actor($user, $platform_url),
            'verb'   => self::build_verb($verb_iri, $verb_display),
            'object' => self::build_activity($quiz_iri, format_string($quiz->name), self::TYPE_QUIZ),
            'result' => [
                'score'      => [
                    'scaled' => $score_scaled,
                    'raw'    => $score_raw,
                    'min'    => 0.0,
                    'max'    => $score_max,
                ],
                'success'    => $passed,
                'completion' => true,
            ],
            'context'   => self::build_platform_context($platform_url),
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Build an xAPI statement for a course module view.
     *
     * @param \stdClass $user    Moodle user.
     * @param \stdClass $cm      Course module record (id, modname, instance, course).
     * @param string    $cmname  Module name (display).
     * @param string    $wwwroot Site wwwroot.
     */
    public static function build_module_viewed(
        \stdClass $user,
        \stdClass $cm,
        string $cmname,
        string $wwwroot
    ): self {
        $platform_url = rtrim($wwwroot, '/');
        $module_iri   = $platform_url . '/mod/' . $cm->modname . '/view.php?id=' . (int) $cm->id;

        return new self([
            'id'        => self::generate_uuid(),
            'actor'     => self::build_actor($user, $platform_url),
            'verb'      => self::build_verb(self::VERB_EXPERIENCED, 'experienced'),
            'object'    => self::build_activity($module_iri, $cmname, self::TYPE_MODULE),
            'context'   => self::build_platform_context($platform_url),
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Build an xAPI statement for user login.
     *
     * @param \stdClass $user    Moodle user.
     * @param string    $wwwroot Site wwwroot.
     */
    public static function build_user_loggedin(\stdClass $user, string $wwwroot): self {
        $platform_url = rtrim($wwwroot, '/');

        return new self([
            'id'      => self::generate_uuid(),
            'actor'   => self::build_actor($user, $platform_url),
            'verb'    => self::build_verb(self::VERB_EXPERIENCED, 'experienced'),
            'object'  => self::build_activity(
                $platform_url,
                'Sentientia LMS',
                self::TYPE_PLATFORM
            ),
            'context'   => self::build_platform_context($platform_url),
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Build a minimal platform context with the Sentientia LRS as parent.
     */
    public static function build_platform_context(string $platform_url): array {
        return [
            'platform' => 'Sentientia LMS',
            'language' => 'en-US',
            'contextActivities' => [
                'category' => [
                    [
                        'objectType' => 'Activity',
                        'id'         => 'https://sentientia.io/context/lms',
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate a version-4 UUID.
     */
    public static function generate_uuid(): string {
        $data = random_bytes(16);
        // Set version to 4 and variant to 10xxxxxx.
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Validate that a string looks like a UUID v4.
     */
    public static function is_valid_uuid(string $uuid): bool {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }
}
