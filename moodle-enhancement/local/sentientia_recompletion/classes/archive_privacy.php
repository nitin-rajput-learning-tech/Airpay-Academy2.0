<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_recompletion\bizlms\mapper;

/**
 * What is personal inside an archive row's payload, and how to take it out.
 *
 * local_sentientia_recompletion_archive keeps the whole source row as JSON so that nothing the legacy plugin (or
 * the engine) archived is lost. The row names people in four ways:
 *
 * - the learner: the userid column and the payload's userid;
 * - an ACTOR, a different person who acted on the learner's record: the administrator who overrode an activity
 *   completion (payload overrideby) and the grader who last changed a gradebook grade (payload usermodified);
 * - text somebody wrote ABOUT the learner: a questionnaire answer (response) and a grade's feedback and
 *   information note;
 * - text the learner typed into a SCORM package (suspend data, comments, interaction answers) and the name or id
 *   the package was given.
 *
 * The provider erases or anonymises those and nothing else: the archive row itself is compliance evidence and
 * survives.
 *
 * The functions are pure (a JSON string in, a JSON string out), so they are tested without a database.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive_privacy {

    /** Payload keys that name the learner (or a person on every kind of row), whatever the item type. */
    public const PERSON_KEYS = ['userid', 'overrideby'];

    /** Payload key that holds what a learner typed into a questionnaire. */
    public const FREE_TEXT_KEY = 'response';

    /** Item type of a questionnaire answer, the only type whose response key is free text. */
    public const ANSWER_TYPE = 'questionnaire_answer';

    /** Item type of an activity completion: the payload names the administrator who overrode it. */
    public const COMPLETION_TYPE = 'activity_completion';

    /** Item type of a gradebook grade: the payload names the grader and carries their written feedback. */
    public const GRADE_TYPE = 'gradebook_grade';

    /** Item type of a SCORM tracking row: the payload is one element and its value. */
    public const SCORM_TYPE = 'scorm_track';

    /**
     * The payload key that names the OTHER person who acted on the record, by item type. A row of a type that is
     * not listed names no actor.
     *
     * @var array<string, string>
     */
    public const ACTOR_KEYS = [
        self::COMPLETION_TYPE => 'overrideby',
        self::GRADE_TYPE => 'usermodified',
    ];

    /**
     * The payload keys that hold text somebody wrote about the learner, by item type. Emptied when the learner is
     * erased (the grader's own words about them are theirs only as far as they identify the learner).
     *
     * @var array<string, string[]>
     */
    public const FREE_TEXT_KEYS = [
        self::ANSWER_TYPE => [self::FREE_TEXT_KEY],
        self::GRADE_TYPE => ['feedback', 'information'],
    ];

    /**
     * The item types whose payload can name an actor.
     *
     * @return string[]
     */
    public static function actor_types(): array {
        return array_keys(self::ACTOR_KEYS);
    }

    /**
     * The payload key that names the actor of a row of an item type.
     *
     * @param string $itemtype
     * @return string|null Null when a row of that type names no actor.
     */
    public static function actor_key(string $itemtype): ?string {
        return self::ACTOR_KEYS[$itemtype] ?? null;
    }

    /**
     * The payload of a row whose learner is erased: the learner and the other people the row names (the
     * overriding administrator, the grader) become 0, and what was written about the learner is emptied (the free
     * text of an answer, the feedback of a grade, the text typed into a SCORM package). Every other value, and
     * every key, stays.
     *
     * @param string $json
     * @param string $itemtype The row's item type.
     * @return string The new payload; the input when it is not a JSON object.
     */
    public static function scrub_subject(string $json, string $itemtype): string {
        $data = self::decode($json);
        if ($data === null) {
            return $json;
        }
        $keys = self::PERSON_KEYS;
        if (self::actor_key($itemtype) !== null) {
            $keys[] = self::actor_key($itemtype);
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = is_int($data[$key]) ? 0 : '0';
            }
        }
        foreach (self::FREE_TEXT_KEYS[$itemtype] ?? [] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = '';
            }
        }
        if ($itemtype === self::SCORM_TYPE && isset($data['element']) && is_scalar($data['element'])
                && mapper::is_scorm_free_text((string) $data['element'])
                && array_key_exists('value', $data) && $data['value'] !== null) {
            $data['value'] = '';
        }
        return self::encode($data);
    }

    /**
     * The payload of a row in which a person acted on SOMEBODY ELSE's record (overrode an activity completion,
     * changed a grade): that person becomes 0. The row's learner is untouched.
     *
     * @param string $json
     * @param int $actorid
     * @param string $itemtype The row's item type.
     * @return string|null The new payload; null when the row does not name the actor (nothing to write).
     */
    public static function scrub_actor(string $json, int $actorid, string $itemtype = self::COMPLETION_TYPE): ?string {
        $key = self::actor_key($itemtype);
        $data = self::decode($json);
        if ($key === null || $data === null || !array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        if (!is_scalar($data[$key]) || (string) $data[$key] !== (string) $actorid) {
            return null;
        }
        $data[$key] = is_int($data[$key]) ? 0 : '0';
        return self::encode($data);
    }

    /**
     * The people a payload names as actors, for a userlist.
     *
     * @param string $json
     * @param string $itemtype The row's item type.
     * @return int[]
     */
    public static function actors(string $json, string $itemtype = self::COMPLETION_TYPE): array {
        $key = self::actor_key($itemtype);
        $data = self::decode($json);
        if ($key === null || $data === null || !isset($data[$key]) || !is_scalar($data[$key])
                || !preg_match('/^[1-9][0-9]*$/', (string) $data[$key])) {
            return [];
        }
        return [(int) $data[$key]];
    }

    /**
     * LIKE patterns that select the rows whose payload might name a person as the actor. A row they select is
     * decoded and compared exactly; the patterns only keep the scan small. The JSON has the id as a string ("12")
     * or as a number (12), depending on the database driver that read the source row.
     *
     * @param int $actorid
     * @param string $key The payload key that names the actor.
     * @return string[]
     */
    public static function actor_patterns(int $actorid, string $key = 'overrideby'): array {
        return [
            '%"' . $key . '":"' . $actorid . '"%',
            '%"' . $key . '":' . $actorid . ',%',
            '%"' . $key . '":' . $actorid . '}%',
        ];
    }

    /**
     * A payload as it is handed to the learner it is about. Their own data is theirs to have, exactly as
     * archived, except for the OTHER people it names: an overriding administrator's or a grader's id is that
     * person's data and is left out.
     *
     * @param string $json
     * @param string $itemtype The row's item type.
     * @return mixed The decoded payload without the actor, or the decoded JSON as it is when it names none.
     */
    public static function for_export(string $json, string $itemtype) {
        $data = self::decode($json);
        if ($data === null) {
            return json_decode($json, true);
        }
        $key = self::actor_key($itemtype);
        if ($key !== null) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * @param string $json
     * @return array|null Null when it is not a JSON object.
     */
    private static function decode(string $json): ?array {
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array $data
     * @return string
     */
    private static function encode(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new \coding_exception('the archive payload could not be encoded');
        }
        return $json;
    }
}
