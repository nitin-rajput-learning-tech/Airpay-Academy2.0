<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_assistant\agent;

defined('MOODLE_INTERNAL') || die();

/**
 * Central registry of agent tools.
 *
 * The LLM is told ONLY about the tools registered here (via schemas()),
 * and may ONLY propose a tool whose name resolves through get(). A tool
 * name the model invents that isn't registered returns null and is
 * audit-logged as denied_invalid — the model cannot reach an unregistered
 * code path.
 *
 * Registration is static + explicit (no auto-discovery) so the attack
 * surface is a closed, reviewable set.
 *
 * @package local_sentientia_assistant
 */
class tool_registry {

    /** @var array<string, tool>|null Lazily-built name => tool map. */
    private static ?array $tools = null;

    /**
     * Build (once) the registered tool set.
     *
     * @return array<string, tool>
     */
    private static function build(): array {
        if (self::$tools !== null) {
            return self::$tools;
        }
        $instances = [
            new tool\enrol_course(),
            new tool\book_ilt_session(),
            new tool\recommend_content(),
        ];
        $map = [];
        foreach ($instances as $t) {
            $map[$t->name()] = $t;
        }
        self::$tools = $map;
        return self::$tools;
    }

    /**
     * Resolve a proposed (untrusted) tool name to a registered tool.
     *
     * @param string $name Untrusted tool name from the LLM.
     * @return tool|null Null when the name isn't registered.
     */
    public static function get(string $name): ?tool {
        $map = self::build();
        return $map[$name] ?? null;
    }

    /**
     * All registered tools.
     *
     * @return array<string, tool>
     */
    public static function all(): array {
        return self::build();
    }

    /**
     * Tool schemas to hand to the LLM, filtered to the tools the given
     * user actually holds the capability for. The model is never even
     * shown a tool the user can't run — defence in depth on top of the
     * per-call capability check in tool::authorise_and_run().
     *
     * @param int $userid Acting user id.
     * @return array List of schema arrays.
     */
    public static function schemas_for_user(int $userid): array {
        $context = \context_system::instance();
        $out = [];
        foreach (self::build() as $t) {
            if (has_capability($t->capability(), $context, $userid)) {
                $out[] = $t->schema();
            }
        }
        return $out;
    }

    /**
     * Reset the static cache — test helper only.
     */
    public static function reset_cache(): void {
        self::$tools = null;
    }
}
