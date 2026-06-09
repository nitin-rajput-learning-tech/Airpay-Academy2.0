<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_live\question_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Abstract base class for every Sentientia Live question type — Phase
 * E.4.scaffold (2026-05-24).
 *
 * One concrete subclass per question type lives in this directory:
 *   multichoice / word_cloud / open_ended / rating_scale / quiz / ranking.
 *
 * The registry (question_type_registry) maps the slug string (the value
 * stored in {local_sentientia_live_slides}.type) to the FQCN of the
 * concrete subclass. Code that needs to "do something with a slide" —
 * render it, persist a response, compute a tally — instantiates the
 * subclass for the slide's type and calls the methods below.
 *
 * Why a class hierarchy at all (vs the switch-on-type used inside
 * slide_manager + response_recorder today)?
 *
 *   1. Question-type code is balloons fast. Each type's render() needs
 *      template selection, JS bootstrap, aria announcements, mobile
 *      tweaks. Co-locating that with each type keeps the diff for
 *      "add a 7th type" tiny.
 *
 *   2. Type-by-type feature flags. Each type carries its own flag
 *      (live.questiontype.{slug}) — the registry consults the flag
 *      when listing enabled types for the slide picker. The class
 *      knows its own flag key, removing a magic string from callers.
 *
 *   3. Customers will add type plugins. Sentientia LMS's product
 *      pitch includes "plug a new poll type in two files". This base
 *      class IS that contract.
 *
 * Method contract (callers can rely on this — concrete subclasses
 * promise to honour it once implemented):
 *
 *   render(array $context): string
 *       Returns audience-facing HTML for this question. $context
 *       carries the slide row, the parsed settings, current participant
 *       context, and an `aria_id_prefix` for accessibility IDs. The
 *       returned HTML MUST be safe to echo directly (every dynamic
 *       value already escaped).
 *
 *   persist_response(int $userid, array $payload): int
 *       Validate + persist one response. Returns the response row ID.
 *       Implementations delegate to response_recorder::submit() once
 *       they've type-checked the payload. $payload is the decoded
 *       POST body — implementations pluck their own keys out
 *       (e.g. multichoice reads $payload['option_index']).
 *
 *   tally(int $sessionid, int $slideid): array
 *       Compute the tally shape this type renders into a chart. Each
 *       subclass owns its own tally semantics — see the class doc on
 *       the concrete subclass for the exact array shape returned.
 *
 *   validate_config(array $config): array
 *       Type-creation-time settings validation. Returns array of
 *       error messages (empty array means "valid"). Used by the slide
 *       editor form's server-side validate() hook. Never throws —
 *       returns errors so the form can re-render with field-level
 *       feedback.
 *
 *   get_aria_announcements(): array
 *       Returns the static screen-reader announcement strings this type
 *       needs registered with the aria-live region. Map: announcement
 *       key (e.g. 'response_recorded') → human-readable string. The
 *       AMD chart_updater emits these on response_added events.
 *
 * @package    local_sentientia_live
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_question_type {

    /**
     * The type slug stored in {local_sentientia_live_slides}.type.
     * Concrete subclasses override.
     */
    public const SLUG = '';

    /**
     * Per-type feature flag key (resolved through local_sentientia_platform
     * feature_flags). Default OFF — every type gates on its own flag
     * to let customers enable a subset without enabling the lot.
     */
    public const FEATURE_FLAG = '';

    /**
     * Lang string key for the display name shown in the type picker.
     * Resolved via get_string($key, 'local_sentientia_live').
     */
    public const NAME_STRING_KEY = '';

    /**
     * Lang string key for the short description on the type picker
     * card.
     */
    public const DESCRIPTION_STRING_KEY = '';

    /**
     * Render this question for the audience. Returns full HTML.
     *
     * @param array $context Must contain:
     *                       - 'slide'      : stdClass slide row
     *                       - 'settings'   : array of parsed settings
     *                       - 'aria_id_prefix' : string for id attrs
     *                       - 'participant' : stdClass|null
     *                       - 'session'   : stdClass session row
     * @return string HTML safe to echo.
     */
    abstract public function render(array $context): string;

    /**
     * Persist one response from a participant.
     *
     * @param int   $userid    The Moodle user ID submitting (or the
     *                          participant->userid for anonymous joins;
     *                          callers MUST resolve to participantid
     *                          via participant_manager).
     * @param array $payload   Decoded POST body. Keys are type-specific.
     * @return int Response row ID (from
     *             {local_sentientia_live_responses}).
     */
    abstract public function persist_response(int $userid,
                                                array $payload): int;

    /**
     * Compute the tally for this slide. Shape varies by type — see
     * the concrete class for the schema.
     */
    abstract public function tally(int $sessionid, int $slideid): array;

    /**
     * Validate creation-time settings. Returns array of error messages
     * (empty = valid). Used by the slide editor form's server-side
     * validate() hook.
     *
     * @param array $config Type-specific settings blob.
     * @return array Map of field-name → error message string, OR
     *               flat array of error strings if not field-scoped.
     */
    abstract public function validate_config(array $config): array;

    /**
     * Screen-reader announcement strings this type needs in its
     * aria-live region. Map: key → human-readable announcement.
     */
    abstract public function get_aria_announcements(): array;

    /**
     * Convenience: get the type's display name in the current language.
     */
    public function get_display_name(): string {
        if (static::NAME_STRING_KEY === '') {
            return static::SLUG;
        }
        return get_string(static::NAME_STRING_KEY, 'local_sentientia_live');
    }

    /**
     * Convenience: get the type's short description in the current
     * language.
     */
    public function get_description(): string {
        if (static::DESCRIPTION_STRING_KEY === '') {
            return '';
        }
        return get_string(static::DESCRIPTION_STRING_KEY,
            'local_sentientia_live');
    }

    /**
     * Convenience: get the type's slug — same as static::SLUG but
     * callable on an instance without late-binding gymnastics.
     */
    public function get_slug(): string {
        return static::SLUG;
    }

    /**
     * Is this type currently enabled? Default implementation consults
     * the feature_flags resolver; subclasses can override (e.g. to add
     * a per-customer override layer).
     */
    public function is_enabled(): bool {
        if (static::FEATURE_FLAG === '') {
            // No flag declared — treat as always-on for back-compat
            // with types that predate the flag layer.
            return true;
        }
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            // Core feature-flag resolver missing — fail closed.
            return false;
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled(
                static::FEATURE_FLAG);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
