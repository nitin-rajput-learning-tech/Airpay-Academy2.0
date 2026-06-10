<?php
/**
 * AI Client — calls Claude API with user learning context.
 *
 * Uses Claude Haiku for simple queries (fast, cheap) and Sonnet for complex analysis.
 * Rate limited to 20 queries/user/day. Caches frequent responses.
 *
 * @package    local_sentientia_assistant
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_assistant;

defined('MOODLE_INTERNAL') || die();

class ai_client {

    /** Rate limit: max queries per user per day. */
    const RATE_LIMIT = 20;

    /** Cache TTL: 1 hour for common queries. */
    const CACHE_TTL = 3600;

    /** Model IDs. */
    const MODEL_FAST = 'claude-haiku-4-5-20251001';
    const MODEL_SMART = 'claude-sonnet-4-6';

    /**
     * Process a user query — build context, call Claude, return response.
     *
     * @param int    $userid  User ID
     * @param string $query   User's question
     * @return array {response: string, model: string, cached: bool, tokens_in: int, tokens_out: int}
     */
    public static function ask(int $userid, string $query): array {
        global $DB;

        // Phase A0 (2026-05-14) — Switchboard gate. When the super
        // admin turns off `ai.assistant.enabled` (e.g. during a vendor
        // outage or to comply with a temporary regulatory pause), the
        // assistant returns a polite "temporarily unavailable" response
        // rather than calling Claude. The drawer UI hides itself
        // separately via the template's feature-flag helper.
        if (class_exists('\\local_sentientia_platform\\feature_flags')
                && !\local_sentientia_platform\feature_flags::is_enabled('ai.assistant.enabled')) {
            return [
                'response'  => "The AI assistant is temporarily disabled by your site administrator. "
                             . "Please try again later, or contact your L&D team.",
                'model'     => 'flag_disabled',
                'cached'    => false,
                'tokens_in' => 0,
                'tokens_out' => 0,
            ];
        }

        // Rate limit check.
        $today_start = strtotime('today');
        $count = $DB->count_records_select('local_sentientia_chat_log',
            "userid = :uid AND role = 'user' AND timecreated >= :today",
            ['uid' => $userid, 'today' => $today_start]);

        if ($count >= self::RATE_LIMIT) {
            return [
                'response'  => "You've reached your daily limit of " . self::RATE_LIMIT .
                               " questions. Come back tomorrow! In the meantime, browse the course catalog.",
                'model'     => 'rate_limited',
                'cached'    => false,
                'tokens_in' => 0,
                'tokens_out' => 0,
            ];
        }

        // Check cache.
        $cache_key = hash('sha256', strtolower(trim($query)));
        $cached = $DB->get_record_select('local_sentientia_chat_cache',
            "cache_key = :key AND timeexpires > :now",
            ['key' => $cache_key, 'now' => time()]);

        if ($cached) {
            $DB->set_field('local_sentientia_chat_cache', 'hit_count', $cached->hit_count + 1,
                ['id' => $cached->id]);

            // Log the query.
            self::log_message($userid, 'user', $query, 'cached', 0, 0);
            self::log_message($userid, 'assistant', $cached->response, 'cached', 0, 0);

            return [
                'response'  => $cached->response,
                'model'     => 'cached',
                'cached'    => true,
                'tokens_in' => 0,
                'tokens_out' => 0,
            ];
        }

        // Build learning context.
        $context = self::build_context($userid);

        // Choose model based on query complexity.
        $model = self::choose_model($query);

        // Call Claude API.
        $api_key = get_config('local_sentientia_assistant', 'api_key');
        if (empty($api_key)) {
            return [
                'response'  => "The AI assistant is not configured yet. Please ask your administrator to set up the API key in Site Admin > Plugins > Local plugins > Airpay AI Assistant.",
                'model'     => 'not_configured',
                'cached'    => false,
                'tokens_in' => 0,
                'tokens_out' => 0,
            ];
        }

        $result = self::call_claude($api_key, $model, $context, $query);

        // Log messages.
        self::log_message($userid, 'user', $query, $model, $result['tokens_in'], 0);
        self::log_message($userid, 'assistant', $result['response'], $model, 0, $result['tokens_out']);

        // Cache the response if it's a common query pattern.
        if (self::is_cacheable($query)) {
            $DB->insert_record('local_sentientia_chat_cache', (object)[
                'cache_key'   => $cache_key,
                'query'       => $query,
                'response'    => $result['response'],
                'hit_count'   => 0,
                'timecreated' => time(),
                'timeexpires' => time() + self::CACHE_TTL,
            ]);
        }

        return $result;
    }

    /**
     * Build learning context for the user — their role, courses, deadlines, team data.
     */
    private static function build_context(int $userid): string {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid],
            'id, firstname, lastname, open_path, open_designation, open_employeeid');

        // Basic info.
        $context = "User: {$user->firstname} {$user->lastname}\n";
        $context .= "Role/Designation: " . ($user->open_designation ?: 'Employee') . "\n";

        // Org path.
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        $orgid = (int)($parts[0] ?? 0);
        $org = $DB->get_field('local_costcenter', 'fullname', ['id' => $orgid]);
        $context .= "Organization: " . ($org ?: 'Unknown') . "\n\n";

        // Enrolled courses with progress.
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.enddate,
                    CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                         WHEN cc.id IS NOT NULL THEN 'in_progress'
                         ELSE 'enrolled' END as status
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
          LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = :uid
              WHERE ue.userid = :uid2 AND c.visible = 1 AND c.id > 1
           ORDER BY c.fullname LIMIT 20",
            ['uid' => $userid, 'uid2' => $userid]);

        $enrolled = 0;
        $completed = 0;
        $overdue = 0;
        $courselist = [];

        foreach ($courses as $c) {
            $enrolled++;
            if ($c->status === 'completed') {
                $completed++;
            }
            if ($c->enddate > 0 && $c->enddate < time() && $c->status !== 'completed') {
                $overdue++;
            }
            $courselist[] = "- {$c->fullname} ({$c->shortname}): {$c->status}" .
                ($c->enddate > 0 ? " [due: " . userdate($c->enddate, '%d %b %Y') . "]" : "");
        }

        $context .= "Courses: {$enrolled} enrolled, {$completed} completed, {$overdue} overdue\n";
        $context .= implode("\n", array_slice($courselist, 0, 10)) . "\n\n";

        // Certificates.
        $certs = $DB->count_records('tool_certificate_issues', ['userid' => $userid]);
        $context .= "Certificates earned: {$certs}\n\n";

        // Team data (if manager).
        $teamsize = $DB->count_records_select('user',
            'open_supervisorid = :uid AND deleted = 0 AND suspended = 0',
            ['uid' => $userid]);
        if ($teamsize > 0) {
            $context .= "Team: {$teamsize} direct reports (you are a manager)\n";
        }

        // Gamification (if available).
        if ($DB->get_manager()->table_exists('local_sentientia_points_log')) {
            $points = $DB->get_field_sql(
                "SELECT COALESCE(SUM(points), 0) FROM {local_sentientia_points_log} WHERE userid = :uid",
                ['uid' => $userid]);
            $context .= "Gamification points: {$points}\n";
        }

        return $context;
    }

    /**
     * Choose model based on query complexity.
     * Simple queries (status, recommendations) → Haiku (fast/cheap).
     * Complex queries (analysis, quiz generation) → Sonnet (smart).
     */
    private static function choose_model(string $query): string {
        $complex_patterns = [
            'summarize', 'summary', 'analyze', 'analysis', 'explain',
            'quiz me', 'test me', 'generate questions', 'compare',
            'how is my team', 'team performance', 'report',
        ];

        $lower = strtolower($query);
        foreach ($complex_patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return self::MODEL_SMART;
            }
        }

        return self::MODEL_FAST;
    }

    /**
     * Call Claude API.
     */
    private static function call_claude(string $api_key, string $model, string $context, string $query): array {
        // White-label (D4): default prompt carries the configured site name; a
        // per-customer prompt override (Wave C3) replaces this wholesale.
        $sitename = format_string(get_site()->fullname);
        $system_prompt = "You are the {$sitename} learning assistant. You help learners with their learning journey on {$sitename}.\n\n" .
            "You know about the user's courses, progress, deadlines, and organization. Be helpful, concise, and encouraging.\n\n" .
            "For course recommendations, suggest courses from the user's catalog that match their role and skill gaps.\n" .
            "For compliance questions, check their mandatory course deadlines.\n" .
            "For quiz requests, generate 3-5 multiple choice questions from the course topic.\n\n" .
            "USER CONTEXT:\n{$context}";

        $payload = [
            'model'      => $model,
            'max_tokens' => 1024,
            'system'     => $system_prompt,
            'messages'   => [
                ['role' => 'user', 'content' => $query],
            ],
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200 || empty($response)) {
            return [
                'response'   => "I'm having trouble connecting right now. Please try again in a moment.",
                'model'      => $model,
                'cached'     => false,
                'tokens_in'  => 0,
                'tokens_out' => 0,
            ];
        }

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? 'I could not generate a response.';
        $tokens_in = $data['usage']['input_tokens'] ?? 0;
        $tokens_out = $data['usage']['output_tokens'] ?? 0;

        return [
            'response'   => $text,
            'model'      => $model,
            'cached'     => false,
            'tokens_in'  => $tokens_in,
            'tokens_out' => $tokens_out,
        ];
    }

    /**
     * Check if a query is cacheable (common patterns that produce stable responses).
     */
    private static function is_cacheable(string $query): bool {
        $cacheable = ['compliance status', 'what should i learn', 'my deadlines', 'what courses'];
        $lower = strtolower($query);
        foreach ($cacheable as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Log a chat message.
     */
    private static function log_message(int $userid, string $role, string $message,
                                         string $model, int $tokens_in, int $tokens_out): void {
        global $DB;
        $DB->insert_record('local_sentientia_chat_log', (object)[
            'userid'      => $userid,
            'role'        => $role,
            'message'     => $message,
            'model'       => $model,
            'tokens_in'   => $tokens_in,
            'tokens_out'  => $tokens_out,
            'timecreated' => time(),
        ]);
    }

    /**
     * Get recent chat history for a user.
     */
    public static function get_history(int $userid, int $limit = 20): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT id, role, message, model, timecreated
               FROM {local_sentientia_chat_log}
              WHERE userid = :uid
           ORDER BY timecreated DESC",
            ['uid' => $userid], 0, $limit));
    }

    /**
     * Get usage stats for admin dashboard.
     */
    public static function get_usage_stats(): array {
        global $DB;
        $today = strtotime('today');
        return [
            'queries_today' => $DB->count_records_select('local_sentientia_chat_log',
                "role = 'user' AND timecreated >= :today", ['today' => $today]),
            'unique_users_today' => $DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid) FROM {local_sentientia_chat_log}
                  WHERE role = 'user' AND timecreated >= :today",
                ['today' => $today]),
            'cache_hits' => $DB->get_field_sql(
                "SELECT COALESCE(SUM(hit_count), 0) FROM {local_sentientia_chat_cache}"),
            'total_tokens' => $DB->get_field_sql(
                "SELECT COALESCE(SUM(tokens_in + tokens_out), 0) FROM {local_sentientia_chat_log}"),
        ];
    }
}
