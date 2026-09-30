<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * What is personal inside an archive row's payload, and how to take it out.
 *
 * local_sentientia_recompletion_archive keeps the whole source row as JSON so that nothing the legacy plugin (or
 * the engine) archived is lost. The row names people in three ways: the learner (the userid column and the
 * payload's userid), the administrator who overrode an activity completion (payload overrideby), and, for the
 * free-text questionnaire answers, whatever the learner typed (payload response). The provider erases or
 * anonymises those and nothing else: the archive row itself is compliance evidence and survives.
 *
 * The functions are pure (a JSON string in, a JSON string out), so they are tested without a database.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive_privacy {

    /** Payload keys that name a person. */
    public const PERSON_KEYS = ['userid', 'overrideby'];

    /** Payload key that holds what a learner typed into a questionnaire. */
    public const FREE_TEXT_KEY = 'response';

    /** Item type of a questionnaire answer, the only type whose response key is free text. */
    public const ANSWER_TYPE = 'questionnaire_answer';

    /**
     * The payload of a row whose learner is erased: the learner and the overriding administrator become 0, and the
     * free text of an answer is emptied. Every other value, and every key, stays.
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
        foreach (self::PERSON_KEYS as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = is_int($data[$key]) ? 0 : '0';
            }
        }
        if ($itemtype === self::ANSWER_TYPE && array_key_exists(self::FREE_TEXT_KEY, $data)
                && $data[self::FREE_TEXT_KEY] !== null) {
            $data[self::FREE_TEXT_KEY] = '';
        }
        return self::encode($data);
    }

    /**
     * The payload of a row in which a person acted on SOMEBODY ELSE's record (overrode an activity completion):
     * that person becomes 0. The row's learner is untouched.
     *
     * @param string $json
     * @param int $actorid
     * @return string|null The new payload; null when the row does not name the actor (nothing to write).
     */
    public static function scrub_actor(string $json, int $actorid): ?string {
        $data = self::decode($json);
        if ($data === null || !array_key_exists('overrideby', $data) || $data['overrideby'] === null) {
            return null;
        }
        if (!is_scalar($data['overrideby']) || (string) $data['overrideby'] !== (string) $actorid) {
            return null;
        }
        $data['overrideby'] = is_int($data['overrideby']) ? 0 : '0';
        return self::encode($data);
    }

    /**
     * The people a payload names as actors, for a userlist.
     *
     * @param string $json
     * @return int[]
     */
    public static function actors(string $json): array {
        $data = self::decode($json);
        if ($data === null || !isset($data['overrideby']) || !is_scalar($data['overrideby'])
                || !preg_match('/^[1-9][0-9]*$/', (string) $data['overrideby'])) {
            return [];
        }
        return [(int) $data['overrideby']];
    }

    /**
     * LIKE patterns that select the rows whose payload might name a person as the overriding administrator.
     * A row they select is decoded and compared exactly; the patterns only keep the scan small. The JSON has the
     * id as a string ("12") or as a number (12), depending on the database driver that read the source row.
     *
     * @param int $actorid
     * @return string[]
     */
    public static function actor_patterns(int $actorid): array {
        return [
            '%"overrideby":"' . $actorid . '"%',
            '%"overrideby":' . $actorid . ',%',
            '%"overrideby":' . $actorid . '}%',
        ];
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
