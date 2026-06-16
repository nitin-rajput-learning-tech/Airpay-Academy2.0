<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_talent;

defined('MOODLE_INTERNAL') || die();

/**
 * Skills taxonomy bridge — single chokepoint for every skills lookup the
 * talent suite makes.
 *
 * Why this exists: P2.1 is built ON TOP of a skills taxonomy, but the
 * richer AI-driven taxonomy (`local_sentientia_skillsai`) is built in a
 * PARALLEL session and may not be installed when this plugin runs. Every
 * skills call therefore goes through this bridge, which:
 *
 *   1. Prefers `local_sentientia_skillsai` when it is installed AND
 *      enabled (class_exists + get_config guarded), then
 *   2. Falls back to the manual taxonomy in `local_sentientia_skills`
 *      (`local_sentientia_role_skills` / `local_sentientia_user_skills`),
 *      which is a hard dependency and always present.
 *
 * Both branches return the same shape so callers never branch on source.
 * If the skillsai contract differs from what we expect, the bridge fails
 * SAFE: it logs a developer-debug note and degrades to the manual source
 * rather than throwing — talent mobility must keep working without AI.
 *
 * @package local_sentientia_talent
 */
class skills_bridge {

    /** Config flag the parallel skillsai plugin sets when its taxonomy is live. */
    private const SKILLSAI_COMPONENT = 'local_sentientia_skillsai';

    /**
     * Is the AI skills taxonomy installed AND switched on?
     *
     * Guarded by both class_exists (plugin physically present + a public
     * façade class) and a get_config flag (plugin's own enable switch).
     * Either being absent/false routes us to the manual fallback.
     */
    public static function skillsai_active(): bool {
        // The parallel plugin is expected to expose a façade class
        // `\local_sentientia_skillsai\taxonomy`. Until it ships, this is
        // always false and we use the manual taxonomy.
        if (!class_exists('\\local_sentientia_skillsai\\taxonomy')) {
            return false;
        }
        // Respect the skillsai plugin's own enable switch when present.
        $enabled = get_config(self::SKILLSAI_COMPONENT, 'enabled');
        // get_config returns false when the setting is unset — treat unset
        // as "installed but not explicitly enabled" → fall back to manual.
        return !empty($enabled);
    }

    /**
     * Which taxonomy source is in effect right now? For display + audit.
     *
     * @return string 'skillsai' | 'manual'
     */
    public static function source(): string {
        return self::skillsai_active() ? 'skillsai' : 'manual';
    }

    /**
     * Required skills for a designation (target role), as a map of
     * skillid => required_level.
     *
     * @param string $designation
     * @return array<int,int> skillid => required level
     */
    public static function required_skills_for_designation(string $designation): array {
        global $DB;
        if ($designation === '') {
            return [];
        }

        if (self::skillsai_active()) {
            try {
                $rows = \local_sentientia_skillsai\taxonomy::required_skills($designation);
                // Expected shape: list of objects/arrays with skillid + level.
                $out = [];
                foreach ($rows as $r) {
                    $r = (object) $r;
                    if (isset($r->skillid)) {
                        $out[(int) $r->skillid] = (int) ($r->required_level ?? $r->level ?? 0);
                    }
                }
                return $out;
            } catch (\Throwable $e) {
                debugging('local_sentientia_talent: skillsai required_skills failed, '
                    . 'falling back to manual taxonomy: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
                // fall through to manual.
            }
        }

        // Manual fallback — read directly from local_sentientia_skills.
        return $DB->get_records_menu('local_sentientia_role_skills',
            ['designation' => $designation], '', 'skillid, required_level');
    }

    /**
     * A user's current skill levels, as a map of skillid => current_level.
     *
     * @param int $userid
     * @return array<int,int>
     */
    public static function user_skill_levels(int $userid): array {
        global $DB;

        if (self::skillsai_active()) {
            try {
                $rows = \local_sentientia_skillsai\taxonomy::user_levels($userid);
                $out = [];
                foreach ($rows as $r) {
                    $r = (object) $r;
                    if (isset($r->skillid)) {
                        $out[(int) $r->skillid] = (int) ($r->current_level ?? $r->level ?? 0);
                    }
                }
                return $out;
            } catch (\Throwable $e) {
                debugging('local_sentientia_talent: skillsai user_levels failed, '
                    . 'falling back to manual taxonomy: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        return $DB->get_records_menu('local_sentientia_user_skills',
            ['userid' => $userid], '', 'skillid, current_level');
    }

    /**
     * Compute how well a user matches the skills required for a target
     * designation, 0-100. A simple coverage metric:
     *   sum(min(current, required)) / sum(required) * 100
     *
     * Returns 0 when the target role has no defined required skills (no
     * data to match against — caller can show "no skills mapped").
     *
     * @param int    $userid
     * @param string $designation Target role designation
     * @return int 0-100
     */
    public static function match_percentage(int $userid, string $designation): int {
        $required = self::required_skills_for_designation($designation);
        if (empty($required)) {
            return 0;
        }
        $current = self::user_skill_levels($userid);

        $needed = 0;
        $have   = 0;
        foreach ($required as $skillid => $reqlevel) {
            $reqlevel = max(0, (int) $reqlevel);
            if ($reqlevel === 0) {
                continue;
            }
            $needed += $reqlevel;
            $have   += min((int) ($current[$skillid] ?? 0), $reqlevel);
        }
        if ($needed === 0) {
            return 0;
        }
        return (int) round(($have / $needed) * 100);
    }
}
